<?php

namespace App\Console\Commands;

use App\Enums\CloseReason;
use App\Enums\DeviceStatus;
use App\Models\Setting;
use App\Models\UsageSession;
use Illuminate\Console\Command;

class CloseStaleSessions extends Command
{
    protected $signature = 'pelog:close-stale-sessions';

    protected $description = 'Menutup sesi yang menggantung (heartbeat lama) sebagai recovery';

    public function handle(): int
    {
        $minutes = (int) Setting::getValue('stale_session_minutes', 15);
        $threshold = now()->subMinutes(max(5, $minutes));

        // Laptop yang dimatikan manual berhenti mengirim heartbeat. Sesi seperti
        // itu juga ditutup begitu perangkat terlihat offline (last_seen lebih tua
        // dari jendela online), tanpa harus menunggu ambang stale penuh.
        $onlineWindowSeconds = (int) Setting::getValue('device_online_window_seconds', 300);
        $offlineThreshold = now()->subSeconds(max(60, $onlineWindowSeconds));

        $closed = 0;

        UsageSession::query()
            ->with('device')
            ->whereNull('closed_at')
            ->where(function ($query) use ($threshold, $offlineThreshold) {
                $query->where('last_heartbeat_at', '<', $threshold)
                    ->orWhereNull('last_heartbeat_at')
                    ->orWhereHas('device', fn ($device) => $device->where('last_seen_at', '<', $offlineThreshold));
            })
            ->chunkById(100, function ($sessions) use (&$closed) {
                foreach ($sessions as $session) {
                    $endedAt = $session->last_heartbeat_at ?? $session->started_at_server ?? $session->created_at;
                    $startedAt = $session->started_at_server ?? $session->started_at_client ?? $session->created_at;

                    $session->forceFill([
                        'closed_at' => $endedAt,
                        'close_reason' => CloseReason::Recovery,
                        'duration_minutes' => $startedAt !== null
                            ? max(0, (int) $startedAt->diffInMinutes($endedAt))
                            : 0,
                    ])->save();

                    $device = $session->device;

                    if (
                        $device !== null
                        && $device->status === DeviceStatus::InUse
                        && ! $device->sessions()->active()->exists()
                    ) {
                        $device->forceFill(['status' => 'available'])->saveQuietly();
                    }

                    $closed++;
                }
            });

        $this->info("Sesi menggantung ditutup: {$closed}");

        return self::SUCCESS;
    }
}
