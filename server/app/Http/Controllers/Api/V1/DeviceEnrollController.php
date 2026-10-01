<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Setting;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\DeviceConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceEnrollController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_code' => ['required', 'string', 'max:64'],
            'device_uuid' => ['required', 'uuid'],
            'hostname' => ['required', 'string', 'max:100'],
            'label' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:60'],
            'mac_list' => ['nullable', 'array', 'max:32'],
            'mac_list.*' => ['string', 'max:32'],
            'device_type' => ['nullable', 'in:pc,laptop'],
            'agent_version' => ['nullable', 'string', 'max:30'],
            'windows_version' => ['nullable', 'string', 'max:100'],
            'storage_total_gb' => ['nullable', 'integer', 'min:0'],
            'storage_used_gb' => ['nullable', 'integer', 'min:0'],
        ]);

        $storedHash = Setting::getValue('enrollment_code_hash');

        if (! is_string($storedHash) || $storedHash === '') {
            return ApiResponse::error(
                'enrollment_disabled',
                'Enrollment belum diaktifkan admin. Minta kode enrollment ke admin IT.',
                503,
            );
        }

        if (! hash_equals($storedHash, hash('sha256', $data['enrollment_code']))) {
            return ApiResponse::error('invalid_enrollment_code', 'Kode enrollment salah.', 401);
        }

        // Perangkat yang sudah dihapus admin (soft delete) dipulihkan saat kiosk
        // meng-enroll ulang — mis. laptop yang di-wipe lalu diinstal ulang.
        // Baris lama dipakai kembali agar unique hostname/uuid tidak bentrok dan
        // sesi historis tetap menempel; token dirotasi di bawah.
        $trashedByUuid = Device::onlyTrashed()
            ->where('uuid', $data['device_uuid'])
            ->first();

        $existingByHostname = Device::withTrashed()
            ->where('hostname', $data['hostname'])
            ->where('uuid', '!=', $data['device_uuid'])
            ->first();

        if ($trashedByUuid !== null && $existingByHostname !== null) {
            // Dua baris berbeda mengklaim uuid & hostname yang sama (mis. hostname
            // sudah dipakai perangkat aktif lain). Jangan menebak; minta admin.
            return ApiResponse::error(
                'hostname_taken',
                'Hostname sudah terdaftar untuk perangkat lain. Hubungi admin IT.',
                409,
            );
        }

        $restoredFromTrash = false;

        if ($trashedByUuid !== null) {
            $trashedByUuid->restore();
            $trashedByUuid->forceFill([
                'uuid' => $data['device_uuid'],
                'hostname' => $data['hostname'],
            ])->save();

            $restoredFromTrash = true;
        } elseif ($existingByHostname !== null) {
            if ($existingByHostname->trashed()) {
                $existingByHostname->restore();
                $existingByHostname->forceFill([
                    'uuid' => $data['device_uuid'],
                    'hostname' => $data['hostname'],
                ])->save();

                $restoredFromTrash = true;
            } else {
                $hasActiveSession = $existingByHostname->sessions()->active()->exists();
                $recentlySeen = $existingByHostname->last_seen_at !== null
                    && $existingByHostname->last_seen_at->diffInMinutes(now()) < 10;

                if ($hasActiveSession || $recentlySeen) {
                    return ApiResponse::error(
                        'hostname_taken',
                        'Hostname sudah terdaftar untuk perangkat lain. Hubungi admin IT.',
                        409,
                    );
                }

                // Adopsi perangkat lama yang sudah tidak aktif (mis. laptop di-reimage).
                $oldUuid = $existingByHostname->uuid;
                $existingByHostname->forceFill(['uuid' => $data['device_uuid']])->save();

                Audit::log(
                    action: 'device_adopted',
                    entityType: Device::class,
                    entityId: $existingByHostname->id,
                    metadata: ['hostname' => $data['hostname'], 'old_uuid' => $oldUuid],
                    actorType: 'device',
                    actorId: $existingByHostname->id,
                    request: $request,
                );
            }
        }

        $token = bin2hex(random_bytes(32));

        $device = Device::query()->firstOrNew(['uuid' => $data['device_uuid']]);
        $isNew = ! $device->exists;

        $label = trim((string) ($data['label'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));

        $device->fill([
            'hostname' => $data['hostname'],
            'label' => $label !== '' ? $label : $device->label,
            'location_label' => $location !== '' ? $location : $device->location_label,
            'device_token_hash' => hash('sha256', $token),
            'mac_list' => $data['mac_list'] ?? null,
            'device_type' => $data['device_type'] ?? 'laptop',
            'storage_total_gb' => $data['storage_total_gb'] ?? 0,
            'storage_used_gb' => $data['storage_used_gb'] ?? 0,
            'agent_version' => $data['agent_version'] ?? null,
            'windows_version' => $data['windows_version'] ?? null,
            'last_seen_at' => now(),
        ]);

        if ($isNew) {
            $device->enrolled_at = now();
            $device->status = 'available';
            $device->is_active = true;
        }

        $device->save();

        Audit::log(
            action: match (true) {
                $isNew => 'device_enrolled',
                $restoredFromTrash => 'device_reenrolled',
                default => 'device_re_enrolled',
            },
            entityType: Device::class,
            entityId: $device->id,
            metadata: ['hostname' => $device->hostname, 'restored_from_trash' => $restoredFromTrash],
            actorType: 'device',
            actorId: $device->id,
            request: $request,
        );

        return ApiResponse::ok([
            'device' => [
                'uuid' => $device->uuid,
                'hostname' => $device->hostname,
                'label' => $device->label,
            ],
            'device_token' => $token,
            'config' => DeviceConfig::forClient(),
        ]);
    }
}
