<?php

namespace App\Support;

use App\Enums\DeviceStatus;
use App\Models\Device;

class DeviceStatusResolver
{
    /**
     * Status tampilan perangkat berdasarkan sesi aktif dan heartbeat terakhir.
     */
    public static function resolve(Device $device, int $onlineWindowSeconds = 300): DeviceStatus
    {
        if (! $device->is_active || $device->status === DeviceStatus::Maintenance) {
            return DeviceStatus::Maintenance;
        }

        $offline = $device->last_seen_at === null
            || $device->last_seen_at->diffInSeconds(now()) > $onlineWindowSeconds;

        if ($offline) {
            return DeviceStatus::Offline;
        }

        return $device->sessions->isNotEmpty() ? DeviceStatus::InUse : DeviceStatus::Available;
    }
}
