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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_uuid' => ['required', 'uuid'],
            'user_type' => ['required', 'in:student,staff'],
            'nisn' => ['required_if:user_type,student', 'nullable', 'string', 'max:10'],
            'nip_id' => ['required_if:user_type,staff', 'nullable', 'string', 'max:30'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'usage_purpose' => ['required', 'string', 'max:500'],
            'started_at_client' => ['nullable', 'date'],
            'storage_total_gb' => ['nullable', 'integer', 'min:0'],
            'storage_used_gb' => ['nullable', 'integer', 'min:0'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('balilog_device');

        if ($device->status === DeviceStatus::Maintenance) {
            Audit::log(
                action: 'session_blocked_maintenance',
                entityType: Device::class,
                entityId: $device->id,
                metadata: ['session_uuid' => $data['session_uuid']],
                actorType: 'device',
                actorId: $device->id,
                request: $request,
            );

            return ApiResponse::error(
                'device_maintenance',
                'Laptop ini sedang dalam perawatan Admin IT. Penggunaan tidak dapat dimulai.',
                423,
            );
        }

        $existing = UsageSession::query()->where('session_uuid', $data['session_uuid'])->first();

        if ($existing !== null) {
            if ($existing->device_id !== $device->id) {
                return ApiResponse::error('session_device_mismatch', 'Sesi ini milik perangkat lain.', 409);
            }

            return ApiResponse::ok($this->sessionSummary($existing), 200);
        }

        if ($device->sessions()->active()->exists()) {
            return ApiResponse::error(
                'device_busy',
                'Perangkat ini masih memiliki sesi aktif. Selesaikan atau tutup sesi tersebut dulu.',
                409,
            );
        }

        $student = null;
        $staff = null;

        if ($data['user_type'] === 'student') {
            $student = Student::query()->where('nisn', $data['nisn'])->first();

            if ($student === null || ! $student->is_active) {
                return ApiResponse::error('student_not_found', 'NISN tidak terdaftar atau tidak aktif.', 422);
            }

            if (! $student->hasPin()) {
                return ApiResponse::error(
                    'pin_not_set',
                    'Siswa belum membuat PIN. PIN hanya dapat dibuat saat tersambung ke server.',
                    422,
                );
            }
        } else {
            $staff = StaffMember::query()->where('nip_id', $data['nip_id'])->first();

            if ($staff === null || ! $staff->is_active) {
                return ApiResponse::error('staff_not_found', 'NIP tidak terdaftar atau tidak aktif.', 422);
            }
        }

        if (! empty($data['subject_id'])) {
            $subjectActive = Subject::query()->whereKey($data['subject_id'])->where('is_active', true)->exists();

            if (! $subjectActive) {
                return ApiResponse::error('subject_invalid', 'Mata pelajaran tidak valid.', 422);
            }
        }

        $session = DB::transaction(function () use ($device, $data, $student, $staff) {
            $lockedDevice = Device::query()
                ->whereKey($device->id)
                ->lockForUpdate()
                ->first();

            if ($lockedDevice === null || $lockedDevice->sessions()->active()->exists()) {
                return null;
            }

            $session = UsageSession::query()->create([
                'session_uuid' => $data['session_uuid'],
                'device_id' => $lockedDevice->id,
                'user_type' => $data['user_type'],
                'student_id' => $student?->id,
                'staff_id' => $staff?->id,
                'subject_id' => $data['subject_id'] ?? null,
                'usage_purpose' => $data['usage_purpose'],
                'started_at_client' => isset($data['started_at_client'])
                    ? Carbon::parse($data['started_at_client'])
                    : null,
                'started_at_server' => now(),
                'last_heartbeat_at' => now(),
                'sync_source' => 'online',
            ]);

            $lockedDevice->forceFill([
                'status' => 'in_use',
                'storage_total_gb' => $data['storage_total_gb'] ?? $lockedDevice->storage_total_gb,
                'storage_used_gb' => $data['storage_used_gb'] ?? $lockedDevice->storage_used_gb,
            ])->save();

            return $session;
        });

        if ($session === null) {
            return ApiResponse::error(
                'device_busy',
                'Perangkat ini masih memiliki sesi aktif. Selesaikan atau tutup sesi tersebut dulu.',
                409,
            );
        }

        Audit::log(
            action: 'session_started',
            entityType: UsageSession::class,
            entityId: $session->id,
            metadata: [
                'session_uuid' => $session->session_uuid,
                'user_type' => $data['user_type'],
                'nisn' => $student?->nisn,
                'nip_id' => $staff?->nip_id,
            ],
            actorType: 'device',
            actorId: $device->id,
            request: $request,
        );

        return ApiResponse::ok($this->sessionSummary($session), 201);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_uuid' => ['required', 'uuid'],
            'storage_total_gb' => ['nullable', 'integer', 'min:0'],
            'storage_used_gb' => ['nullable', 'integer', 'min:0'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('balilog_device');

        $session = UsageSession::query()
            ->where('session_uuid', $data['session_uuid'])
            ->where('device_id', $device->id)
            ->first();

        if ($session === null) {
            return ApiResponse::error('session_not_found', 'Sesi tidak ditemukan.', 404);
        }

        if (! $session->isActive()) {
            return ApiResponse::ok([
                'active' => false,
                'closed_at' => $session->closed_at?->toIso8601String(),
                'close_reason' => $session->close_reason?->value,
            ]);
        }

        $session->forceFill(['last_heartbeat_at' => now()])->saveQuietly();

        if (isset($data['storage_total_gb']) || isset($data['storage_used_gb'])) {
            $device->forceFill([
                'storage_total_gb' => $data['storage_total_gb'] ?? $device->storage_total_gb,
                'storage_used_gb' => $data['storage_used_gb'] ?? $device->storage_used_gb,
            ])->saveQuietly();
        }

        return ApiResponse::ok([
            'active' => true,
        ]);
    }

    public function end(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_uuid' => ['required', 'uuid'],
            'close_reason' => ['nullable', 'in:normal,recovery,shutdown,admin'],
            'student_feedback' => ['nullable', 'string', 'max:2000'],
            'comprehension_level' => ['nullable', 'in:sangat_paham,paham,cukup,kurang'],
            'ended_at_client' => ['nullable', 'date'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('balilog_device');

        $session = UsageSession::query()
            ->where('session_uuid', $data['session_uuid'])
            ->where('device_id', $device->id)
            ->first();

        if ($session === null) {
            return ApiResponse::error('session_not_found', 'Sesi tidak ditemukan.', 404);
        }

        if ($session->isActive()) {
            $reason = CloseReason::tryFrom($data['close_reason'] ?? '') ?? CloseReason::Normal;
            $startedAt = $session->started_at_server ?? $session->started_at_client ?? $session->created_at;
            $closedAt = isset($data['ended_at_client'])
                ? Carbon::parse($data['ended_at_client'])
                : now();

            if ($startedAt !== null && $closedAt->lessThan($startedAt)) {
                $closedAt = $startedAt;
            }

            if ($closedAt->greaterThan(now())) {
                $closedAt = now();
            }

            $session->forceFill([
                'closed_at' => $closedAt,
                'close_reason' => $reason,
                'student_feedback' => $data['student_feedback'] ?? $session->student_feedback,
                'comprehension_level' => $data['comprehension_level'] ?? $session->comprehension_level,
                'duration_minutes' => $startedAt !== null
                    ? max(0, (int) $startedAt->diffInMinutes($closedAt))
                    : 0,
            ])->save();

            if ($device->status === DeviceStatus::InUse) {
                $device->forceFill(['status' => 'available'])->saveQuietly();
            }

            Audit::log(
                action: 'session_ended',
                entityType: UsageSession::class,
                entityId: $session->id,
                metadata: [
                    'session_uuid' => $session->session_uuid,
                    'close_reason' => $reason->value,
                    'duration_minutes' => (int) ($session->duration_minutes ?? 0),
                ],
                actorType: 'device',
                actorId: $device->id,
                request: $request,
            );
        }

        return ApiResponse::ok($this->sessionSummary($session->refresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionSummary(UsageSession $session): array
    {
        return [
            'session_uuid' => $session->session_uuid,
            'user_type' => $session->user_type->value,
            'device_id' => $session->device_id,
            'student_id' => $session->student_id,
            'staff_id' => $session->staff_id,
            'subject_id' => $session->subject_id,
            'usage_purpose' => $session->usage_purpose,
            'started_at_client' => $session->started_at_client?->toIso8601String(),
            'started_at_server' => $session->started_at_server?->toIso8601String(),
            'last_heartbeat_at' => $session->last_heartbeat_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'close_reason' => $session->close_reason?->value,
            'duration_minutes' => (int) ($session->duration_minutes ?? 0),
            'active' => $session->isActive(),
        ];
    }
}
