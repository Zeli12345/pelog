<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Setting;
use App\Support\Audit;
use App\Support\DeviceStatusResolver;
use Illuminate\Http\RedirectResponse;
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

    public function update(Request $request, Device $device): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:40'],
            'location_label' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'in:available,maintenance'],
        ]);

        $before = [
            'label' => $device->label,
            'location_label' => $device->location_label,
            'status' => $device->status->value,
        ];

        $label = trim((string) ($data['label'] ?? ''));
        $location = trim((string) ($data['location_label'] ?? ''));

        $device->forceFill([
            'label' => $label !== '' ? $label : null,
            'location_label' => $location !== '' ? $location : null,
            'status' => $data['status'],
        ])->save();

        Audit::log(
            action: 'device_updated',
            entityType: Device::class,
            entityId: $device->id,
            metadata: [
                'before' => $before,
                'after' => [
                    'label' => $device->label,
                    'location_label' => $device->location_label,
                    'status' => $device->status->value,
                ],
            ],
            actorType: 'user',
            actorId: auth()->id(),
            request: $request,
        );

        return back()->with('status', 'Perangkat diperbarui.');
    }
}
