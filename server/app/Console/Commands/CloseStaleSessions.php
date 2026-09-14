<?php

namespace App\Console\Commands;

use App\Enums\CloseReason;
use App\Models\Setting;
use App\Models\UsageSession;
use Illuminate\Console\Command;

class CloseStaleSessions extends Command
{
    protected $signature = 'balilog:close-stale-sessions';

    protected $description = 'Menutup sesi yang menggantung (heartbeat lama) sebagai recovery';

    public function handle(): int
    {
        $minutes = (int) Setting::getValue('stale_session_minutes', 15);
        $threshold = now()->subMinutes(max(5, $minutes));

        $sessions = UsageSession::query()
            ->whereNull('closed_at')
            ->where(function ($query) use ($threshold) {
                $query->where('last_heartbeat_at', '<', $threshold)
                    ->orWhereNull('last_heartbeat_at');
            })
            ->get();

        $closed = 0;

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

            if ($device !== null && ! $device->sessions()->active()->exists()) {
                $device->forceFill(['status' => 'available'])->saveQuietly();
            }

            $closed++;
        }

        $this->info("Sesi menggantung ditutup: {$closed}");

        return self::SUCCESS;
    }
}
