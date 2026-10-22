<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CloseReason;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceNote;
use App\Models\Location;
use App\Models\LocationLayoutObject;
use App\Models\Setting;
use App\Support\Audit;
use App\Support\DeviceStatusResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $onlineWindow = (int) Setting::getValue('device_online_window_seconds', 300);
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');
        $locationFilter = (int) $request->query('location_id', 0);
        $trashed = $request->boolean('trashed');

        $devices = ($trashed ? Device::onlyTrashed() : Device::query())
            ->with([
                'location',
                'sessions' => fn ($query) => $query->active()->with(['student', 'staff', 'subject']),
            ])
            ->withCount(['notes as open_notes_count' => fn ($query) => $query->open()])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('hostname', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orWhere('location_label', 'like', "%{$search}%")
                        ->orWhereHas('location', fn ($location) => $location->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($locationFilter > 0, fn ($query) => $query->where('location_id', $locationFilter))
            ->orderByRaw('label IS NULL, label')
            ->orderBy('hostname')
            ->get();

        $statuses = $devices->mapWithKeys(
            fn (Device $device) => [$device->id => DeviceStatusResolver::resolve($device, $onlineWindow)]
        );

        if ($statusFilter !== '') {
            $devices = $devices->filter(function (Device $device) use ($statusFilter, $statuses) {
                if ($statusFilter === 'offline') {
                    return DeviceStatusResolver::isOffline($device, $onlineWindow);
                }

                return $statuses[$device->id]->value === $statusFilter;
            })->values();
        }

        return view('dashboard.devices.index', [
            'devices' => $devices,
            'statuses' => $statuses,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'locationFilter' => $locationFilter,
            'locations' => Location::query()->orderBy('sort_order')->orderBy('name')->get(),
            'onlineWindow' => $onlineWindow,
            'trashed' => $trashed,
            'latestVersion' => (string) Setting::getValue('app_version', config('pelog.version')),
        ]);
    }

    public function show(Request $request, Device $device): View
    {
        $onlineWindow = (int) Setting::getValue('device_online_window_seconds', 300);

        $device->load([
            'location',
            'sessions' => fn ($query) => $query->active()->with(['student', 'staff', 'subject']),
        ]);

        $status = DeviceStatusResolver::resolve($device, $onlineWindow);

        $history = $device->sessions()
            ->with(['student', 'staff', 'subject', 'screenshot'])
            ->withCount('screenshots')
            ->orderByDesc('started_at_server')
            ->orderByDesc('started_at_client')
            ->limit(50)
            ->get();

        $tab = (string) $request->query('tab', 'sessions');
        if (! in_array($tab, ['sessions', 'notes', 'activity'], true)) {
            $tab = 'sessions';
        }

        if ($tab === 'activity' && ! $request->user()->isAdmin()) {
            $tab = 'sessions';
        }

        $noteStatus = (string) $request->query('note_status', '');
        $noteCategory = (string) $request->query('note_category', '');
        $noteSearch = trim((string) $request->query('note_q', ''));

        $notes = $device->notes()
            ->with(['creator', 'attachments'])
            ->when($noteStatus !== '', fn ($query) => $query->where('status', $noteStatus))
            ->when($noteCategory !== '', fn ($query) => $query->where('category', $noteCategory))
            ->when($noteSearch !== '', function ($query) use ($noteSearch) {
                $query->where(function ($inner) use ($noteSearch) {
                    $inner->where('title', 'like', "%{$noteSearch}%")
                        ->orWhere('body', 'like', "%{$noteSearch}%")
                        ->orWhere('vendor', 'like', "%{$noteSearch}%");
                });
            })
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $activity = collect();
        if ($tab === 'activity') {
            $activity = AuditLog::query()
                ->where('entity_type', Device::class)
                ->where('entity_id', $device->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $openNotes = $device->notes()->open()->get();
        $pinnedNote = $device->notes()->where('pinned', true)->latest('id')->first();

        return view('dashboard.devices.show', [
            'device' => $device,
            'status' => $status,
            'activeSession' => $device->sessions->first(),
            'history' => $history,
            'notes' => $notes,
            'activity' => $activity,
            'tab' => $tab,
            'noteStatus' => $noteStatus,
            'noteCategory' => $noteCategory,
            'noteSearch' => $noteSearch,
            'openNotes' => $openNotes,
            'pinnedNote' => $pinnedNote,
            'locations' => Location::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'noteCategories' => DeviceNote::query()->distinct()->orderBy('category')->pluck('category'),
            'idleShutdownMinutes' => (int) Setting::getValue('idle_shutdown_minutes', 90),
        ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:40'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'new_location' => ['nullable', 'string', 'max:80'],
            'location_label' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:available,maintenance'],
        ]);

        $before = [
            'label' => $device->label,
            'location_id' => $device->location_id,
            'location_label' => $device->location_label,
            'status' => $device->status->value,
        ];

        $label = trim((string) ($data['label'] ?? ''));
        $locationProvided = array_key_exists('location_id', $data)
            || array_key_exists('new_location', $data)
            || array_key_exists('location_label', $data);
        $locationId = $data['location_id'] ?? null;
        $newLocation = trim((string) ($data['new_location'] ?? ($data['location_label'] ?? '')));

        if ($newLocation !== '') {
            $normalized = Location::normalizeName($newLocation);

            if ($normalized === '') {
                return back()->withErrors(['new_location' => 'Nama ruangan tidak valid.'])->withInput();
            }

            $location = Location::findSimilar($newLocation);

            if ($location === null) {
                $location = Location::query()->create([
                    'name' => $newLocation,
                    'normalized_name' => $normalized,
                    'is_active' => true,
                ]);
            } elseif (! $location->is_active) {
                $location->forceFill(['is_active' => true])->save();
            }

            $locationId = $location->id;
        }

        $location = $locationId !== null ? Location::query()->find($locationId) : null;

        $device->forceFill([
            'label' => $label !== '' ? $label : null,
            'location_id' => $locationProvided ? $location?->id : $device->location_id,
            'location_label' => $locationProvided ? $location?->name : $device->location_label,
            'status' => $data['status'],
        ])->save();

        if ($before['location_id'] !== $device->location_id) {
            LocationLayoutObject::query()
                ->where('device_id', $device->id)
                ->where('location_id', '!=', $device->location_id)
                ->delete();
        }

        Audit::log(
            action: 'device_updated',
            entityType: Device::class,
            entityId: $device->id,
            metadata: [
                'before' => $before,
                'after' => [
                    'label' => $device->label,
                    'location_id' => $device->location_id,
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

    public function destroy(Request $request, Device $device): RedirectResponse
    {
        $closedSessions = $this->forceCloseActiveSessions($device);

        Audit::log(
            action: 'device_deleted',
            entityType: Device::class,
            entityId: $device->id,
            metadata: [
                'hostname' => $device->hostname,
                'label' => $device->label,
                'closed_sessions' => $closedSessions,
            ],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        LocationLayoutObject::query()->where('device_id', $device->id)->delete();
        $device->delete();

        return redirect()->route('devices.index')->with(
            'status',
            "Perangkat {$device->hostname} dihapus. Kiosk akan menghapus dirinya sendiri saat boot berikutnya."
        );
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $this->validatedIds($request);

        $devices = Device::query()->whereKey($data['ids'])->get();

        if ($devices->isEmpty()) {
            return redirect()->route('devices.index')->with('status', 'Tidak ada perangkat yang cocok untuk dihapus.');
        }

        DB::transaction(function () use ($devices, $request) {
            $closedSessions = 0;

            foreach ($devices as $device) {
                $closedSessions += $this->forceCloseActiveSessions($device);
                LocationLayoutObject::query()->where('device_id', $device->id)->delete();
                $device->delete();
            }

            Audit::log(
                action: 'devices_bulk_deleted',
                entityType: Device::class,
                metadata: [
                    'ids' => $devices->pluck('id')->all(),
                    'count' => $devices->count(),
                    'closed_sessions' => $closedSessions,
                ],
                actorType: 'user',
                actorId: $request->user()->id,
                request: $request,
            );
        });

        return redirect()->route('devices.index')->with(
            'status',
            "{$devices->count()} perangkat dihapus. Kiosk akan menghapus dirinya sendiri saat boot berikutnya."
        );
    }

    public function restore(Request $request, int $device): RedirectResponse
    {
        $device = Device::onlyTrashed()->findOrFail($device);
        $device->restore();

        Audit::log(
            action: 'device_restored',
            entityType: Device::class,
            entityId: $device->id,
            metadata: ['hostname' => $device->hostname],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('devices.index', ['trashed' => 1])
            ->with('status', "Perangkat {$device->hostname} dipulihkan. Token lama berlaku kembali.");
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $data = $this->validatedIds($request);

        $devices = Device::onlyTrashed()->whereKey($data['ids'])->get();

        if ($devices->isEmpty()) {
            return redirect()->route('devices.index', ['trashed' => 1])->with('status', 'Tidak ada perangkat yang cocok untuk dipulihkan.');
        }

        DB::transaction(function () use ($devices, $request) {
            foreach ($devices as $device) {
                $device->restore();
            }

            Audit::log(
                action: 'devices_bulk_restored',
                entityType: Device::class,
                metadata: [
                    'ids' => $devices->pluck('id')->all(),
                    'count' => $devices->count(),
                ],
                actorType: 'user',
                actorId: $request->user()->id,
                request: $request,
            );
        });

        return redirect()->route('devices.index', ['trashed' => 1])
            ->with('status', "{$devices->count()} perangkat dipulihkan.");
    }

    public function requestScreenshot(Request $request, Device $device): RedirectResponse
    {
        $device->forceFill(['screenshot_requested_at' => now()])->save();

        Audit::log(
            action: 'screenshot_requested',
            entityType: Device::class,
            entityId: $device->id,
            metadata: ['hostname' => $device->hostname],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with(
            'status',
            'Permintaan screenshot dikirim. Kiosk akan mengunggahnya saat heartbeat berikutnya (satu sesi aktif ±1 menit).'
        );
    }

    private function forceCloseActiveSessions(Device $device): int
    {
        $closed = 0;
        $closedAt = now();

        foreach ($device->sessions()->active()->get() as $session) {
            $startedAt = $session->started_at_server ?? $session->started_at_client ?? $session->created_at;

            $session->forceFill([
                'closed_at' => $closedAt,
                'close_reason' => CloseReason::Admin,
                'duration_minutes' => $startedAt !== null
                    ? max(0, (int) $startedAt->diffInMinutes($closedAt))
                    : 0,
            ])->save();

            $closed++;
        }

        if ($closed > 0) {
            $device->forceFill(['status' => 'available'])->saveQuietly();
        }

        return $closed;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedIds(Request $request): array
    {
        return $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);
    }
}
