<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CloseReason;
use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\UsageSession;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function sessions(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'sessions' => ['required', 'array', 'max:100'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('balilog_device');

        $results = [];

        foreach ($payload['sessions'] as $item) {
            $results[] = $this->processItem($device, is_array($item) ? $item : [], $request);
        }

        return ApiResponse::ok(['results' => $results]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function processItem(Device $device, array $item, Request $request): array
    {
        $validator = Validator::make($item, [
            'session_uuid' => ['required', 'uuid'],
            'user_type' => ['required', 'in:student,staff'],
            'nisn' => ['required_if:user_type,student', 'nullable', 'string', 'max:10'],
            'nip_id' => ['required_if:user_type,staff', 'nullable', 'string', 'max:30'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'usage_purpose' => ['required', 'string', 'max:500'],
            'started_at_client' => ['nullable', 'date'],
            'ended_at_client' => ['nullable', 'date'],
            'close_reason' => ['nullable', 'in:normal,recovery,shutdown,admin'],
        ]);

        if ($validator->fails()) {
            return [
                'session_uuid' => $item['session_uuid'] ?? null,
                'status' => 'error',
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $data = $validator->validated();

        $existing = UsageSession::query()->where('session_uuid', $data['session_uuid'])->first();

        if ($existing !== null) {
            return $this->existingItemResult($device, $existing, $data);
        }

        $student = null;
        $staff = null;

        if ($data['user_type'] === 'student') {
            $student = Student::query()->where('nisn', $data['nisn'])->first();

            if ($student === null || ! $student->is_active) {
                return [
                    'session_uuid' => $data['session_uuid'],
                    'status' => 'error',
                    'message' => 'Siswa tidak valid.',
                ];
            }
        } else {
            $staff = StaffMember::query()->where('nip_id', $data['nip_id'])->first();

            if ($staff === null || ! $staff->is_active) {
                return [
                    'session_uuid' => $data['session_uuid'],
                    'status' => 'error',
                    'message' => 'Guru/pegawai tidak valid.',
                ];
            }
        }

        if (! empty($data['subject_id'])) {
            $subjectActive = Subject::query()->whereKey($data['subject_id'])->where('is_active', true)->exists();

            if (! $subjectActive) {
                return [
                    'session_uuid' => $data['session_uuid'],
                    'status' => 'error',
                    'message' => 'Mata pelajaran tidak valid.',
                ];
            }
        }

        try {
            $session = DB::transaction(function () use ($device, $data, $student, $staff) {
                Device::query()->whereKey($device->id)->lockForUpdate()->first();

                $raced = UsageSession::query()->where('session_uuid', $data['session_uuid'])->first();

                if ($raced !== null) {
                    return $raced;
                }

                $session = UsageSession::query()->create([
                    'session_uuid' => $data['session_uuid'],
                    'device_id' => $device->id,
                    'user_type' => $data['user_type'],
                    'student_id' => $student?->id,
                    'staff_id' => $staff?->id,
                    'subject_id' => $data['subject_id'] ?? null,
                    'usage_purpose' => $data['usage_purpose'],
                    'started_at_client' => isset($data['started_at_client']) ? Carbon::parse($data['started_at_client']) : null,
                    'started_at_server' => null,
                    'last_heartbeat_at' => isset($data['ended_at_client']) ? null : now(),
                    'sync_source' => 'offline',
                ]);

                if (isset($data['ended_at_client'])) {
                    $this->close($session, $device, $data);
                }

                return $session;
            });
        } catch (QueryException $exception) {
            // Balapan tetap bisa lolos lewat unique session_uuid; perlakukan sebagai idempoten.
            $raced = UsageSession::query()->where('session_uuid', $data['session_uuid'])->first();

            if ($raced === null) {
                throw $exception;
            }

            $session = $raced;
        }

        if (! $session->wasRecentlyCreated) {
            return $this->existingItemResult($device, $session, $data);
        }

        Audit::log(
            action: 'session_synced_offline',
            entityType: UsageSession::class,
            entityId: $session->id,
            metadata: ['session_uuid' => $session->session_uuid],
            actorType: 'device',
            actorId: $device->id,
            request: $request,
        );

        return [
            'session_uuid' => $data['session_uuid'],
            'status' => 'created',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function existingItemResult(Device $device, UsageSession $existing, array $data): array
    {
        if ($existing->device_id !== $device->id) {
            return [
                'session_uuid' => $data['session_uuid'],
                'status' => 'error',
                'message' => 'Sesi milik perangkat lain.',
            ];
        }

        // Lengkapi data akhir jika sesi masih terbuka dan payload membawa penutupan.
        if ($existing->isActive() && isset($data['ended_at_client'])) {
            $this->close($existing, $device, $data);
        }

        return [
            'session_uuid' => $data['session_uuid'],
            'status' => 'skipped',
            'message' => 'Sesi sudah ada (idempoten).',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function close(UsageSession $session, Device $device, array $data): void
    {
        $reason = CloseReason::tryFrom($data['close_reason'] ?? '') ?? CloseReason::Recovery;
        $closedAt = isset($data['ended_at_client']) ? Carbon::parse($data['ended_at_client']) : now();
        $startedAt = $session->started_at_client ?? $session->started_at_server ?? $session->created_at;

        $session->forceFill([
            'closed_at' => $closedAt,
            'close_reason' => $reason,
            'duration_minutes' => $startedAt !== null ? max(0, (int) $startedAt->diffInMinutes($closedAt)) : 0,
        ])->save();

        if ($device->status === DeviceStatus::InUse && ! $device->sessions()->active()->exists()) {
            $device->forceFill(['status' => 'available'])->saveQuietly();
        }
    }
}
