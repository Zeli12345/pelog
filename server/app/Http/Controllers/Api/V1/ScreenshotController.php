<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Screenshot;
use App\Models\UsageSession;
use App\Services\ScreenshotService;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScreenshotController extends Controller
{
    public function store(Request $request, string $sessionUuid, ScreenshotService $service): JsonResponse
    {
        $data = $request->validate([
            'screenshot_uuid' => ['required', 'uuid'],
            'image_file' => ['required', 'file', 'max:300', 'mimetypes:image/webp,image/jpeg'],
            'captured_at' => ['nullable', 'date'],
            'captured_at_client' => ['nullable', 'date'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('pelog_device');

        $session = UsageSession::query()
            ->where('session_uuid', $sessionUuid)
            ->where('device_id', $device->id)
            ->first();

        if ($session === null) {
            return ApiResponse::error('session_not_found', 'Sesi tidak ditemukan.', 404);
        }

        // Idempotensi dibatasi pada sesi ini; uuid milik sesi/perangkat lain tidak
        // boleh dianggap sebagai ringkasan milik sesi ini.
        $existing = Screenshot::query()
            ->where('usage_session_id', $session->id)
            ->first();

        if ($existing !== null) {
            return ApiResponse::ok($this->summary($existing));
        }

        $screenshot = $service->store($request->file('image_file'), $session);

        $screenshot->forceFill([
            'screenshot_uuid' => $data['screenshot_uuid'],
            'captured_at' => isset($data['captured_at']) ? Carbon::parse($data['captured_at']) : now(),
            'captured_at_client' => isset($data['captured_at_client'])
                ? Carbon::parse($data['captured_at_client'])
                : null,
        ])->save();

        // Permintaan screenshot langsung dari dashboard sudah terpenuhi.
        $device->forceFill(['screenshot_requested_at' => null])->saveQuietly();

        Audit::log(
            action: 'screenshot_uploaded',
            entityType: Screenshot::class,
            entityId: $screenshot->id,
            metadata: [
                'session_uuid' => $session->session_uuid,
                'format' => $screenshot->format->value,
                'size_bytes' => $screenshot->size_bytes,
            ],
            actorType: 'device',
            actorId: $device->id,
            request: $request,
        );

        return ApiResponse::ok($this->summary($screenshot), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Screenshot $screenshot): array
    {
        return [
            'screenshot_uuid' => $screenshot->screenshot_uuid,
            'usage_session_id' => $screenshot->usage_session_id,
            'format' => $screenshot->format->value,
            'size_bytes' => $screenshot->size_bytes,
            'thumb_available' => $screenshot->thumb_path !== null,
            'captured_at' => $screenshot->captured_at?->toIso8601String(),
        ];
    }
}
