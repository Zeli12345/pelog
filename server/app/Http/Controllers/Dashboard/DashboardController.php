<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Screenshot;
use App\Models\Setting;
use App\Models\Student;
use App\Models\UsageSession;
use App\Support\DeviceStatusResolver;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $onlineWindow = (int) Setting::getValue('device_online_window_seconds', 300);

        $devices = Device::query()
            ->with(['sessions' => fn ($query) => $query->active()->with(['student', 'staff', 'subject'])])
            ->orderByRaw('label IS NULL, label')
            ->orderBy('hostname')
            ->get();

        $statuses = $devices->mapWithKeys(
            fn (Device $device) => [$device->id => DeviceStatusResolver::resolve($device, $onlineWindow)]
        );

        $stats = [
            'total' => $devices->count(),
            'in_use' => $statuses->filter(fn (DeviceStatus $status) => $status === DeviceStatus::InUse)->count(),
            'available' => $statuses->filter(fn (DeviceStatus $status) => $status === DeviceStatus::Available)->count(),
            'offline' => $statuses->filter(fn (DeviceStatus $status) => $status === DeviceStatus::Offline)->count(),
            'maintenance' => $statuses->filter(fn (DeviceStatus $status) => $status === DeviceStatus::Maintenance)->count(),
            'sessions_today' => UsageSession::query()
                ->where(function ($query) {
                    $query->whereDate('started_at_server', today())
                        ->orWhereDate('started_at_client', today());
                })
                ->count(),
            'screenshots_today' => Screenshot::query()->whereDate('captured_at', today())->count(),
            'students_active' => Student::query()->where('is_active', true)->count(),
        ];

        return view('dashboard.overview', [
            'devices' => $devices,
            'statuses' => $statuses,
            'stats' => $stats,
            'onlineWindow' => $onlineWindow,
        ]);
    }
}
