<?php

namespace App\Support;

use App\Enums\DeviceStatus;
use App\Models\Device;

class DeviceStatusResolver
{
    /**
     * Status tampilan perangkat.
     *
     * Perangkat tanpa sesi aktif selalu "Tersedia" (walau sedang dimatikan /
     * offline) supaya tidak terlihat "stuck" setelah shutdown manual; info
     * offline ditampilkan terpisah lewat isOffline().
     */
    public static function resolve(Device $device, int $onlineWindowSeconds = 300): DeviceStatus
    {
        if (! $device->is_active || $device->status === DeviceStatus::Maintenance) {
            return DeviceStatus::Maintenance;
        }

        return $device->sessions->isNotEmpty() ? DeviceStatus::InUse : DeviceStatus::Available;
    }

    /** Benar bila heartbeat terakhir sudah melewati jendela online (dianggap offline). */
    public static function isOffline(Device $device, int $onlineWindowSeconds = 300): bool
    {
        return $device->last_seen_at === null
            || $device->last_seen_at->diffInSeconds(now()) > $onlineWindowSeconds;
    }
}
