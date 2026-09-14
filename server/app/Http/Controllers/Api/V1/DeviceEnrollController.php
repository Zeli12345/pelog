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
            'mac_list' => ['nullable', 'array'],
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

        $hostnameTaken = Device::query()
            ->where('hostname', $data['hostname'])
            ->where('uuid', '!=', $data['device_uuid'])
            ->exists();

        if ($hostnameTaken) {
            return ApiResponse::error(
                'hostname_taken',
                'Hostname sudah terdaftar untuk perangkat lain. Hubungi admin IT.',
                409,
            );
        }

        $token = bin2hex(random_bytes(32));

        $device = Device::query()->firstOrNew(['uuid' => $data['device_uuid']]);
        $isNew = ! $device->exists;

        $device->fill([
            'hostname' => $data['hostname'],
            'device_token_hash' => hash('sha256', $token),
            'mac_list' => $data['mac_list'] ?? null,
            'device_type' => $data['device_type'] ?? 'laptop',
            'storage_total_gb' => $data['storage_total_gb'] ?? 0,
            'storage_used_gb' => $data['storage_used_gb'] ?? 0,
            'agent_version' => $data['agent_version'] ?? null,
            'windows_version' => $data['windows_version'] ?? null,
            'last_seen_at' => now(),
            'is_active' => true,
        ]);

        if ($isNew) {
            $device->enrolled_at = now();
            $device->status = 'available';
        }

        $device->save();

        Audit::log(
            action: $isNew ? 'device_enrolled' : 'device_re_enrolled',
            entityType: Device::class,
            entityId: $device->id,
            metadata: ['hostname' => $device->hostname],
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
