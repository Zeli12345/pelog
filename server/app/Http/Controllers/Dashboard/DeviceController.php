<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Setting;
use App\Support\DeviceStatusResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $onlineWindow = (int) Setting::getValue('device_online_window_seconds', 300);
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');

        $devices = Device::query()
            ->with(['sessions' => fn ($query) => $query->active()->with(['student', 'staff', 'subject'])])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('hostname', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orWhere('location_label', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('label IS NULL, label')
            ->orderBy('hostname')
            ->get();

        $statuses = $devices->mapWithKeys(
            fn (Device $device) => [$device->id => DeviceStatusResolver::resolve($device, $onlineWindow)]
        );

        if ($statusFilter !== '') {
            $devices = $devices->filter(
                fn (Device $device) => $statuses[$device->id]->value === $statusFilter
            )->values();
        }

        return view('dashboard.devices.index', [
            'devices' => $devices,
            'statuses' => $statuses,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'onlineWindow' => $onlineWindow,
        ]);
    }

    public function show(Device $device): View
    {
        $onlineWindow = (int) Setting::getValue('device_online_window_seconds', 300);

        $device->load(['sessions' => fn ($query) => $query->active()->with(['student', 'staff', 'subject'])]);

        $status = DeviceStatusResolver::resolve($device, $onlineWindow);

        $history = $device->sessions()
            ->with(['student', 'staff', 'subject', 'screenshot'])
            ->orderByDesc('started_at_server')
            ->orderByDesc('started_at_client')
            ->limit(50)
            ->get();

        return view('dashboard.devices.show', [
            'device' => $device,
            'status' => $status,
            'activeSession' => $device->sessions->first(),
            'history' => $history,
        ]);
    }
}
