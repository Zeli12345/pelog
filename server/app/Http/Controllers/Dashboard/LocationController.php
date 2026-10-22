<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\LocationLayoutObject;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $onlineWindow = (int) Setting::getValue('device_online_window_seconds', 300);
        $showHidden = $request->boolean('hidden') && $request->user()?->isAdmin();

        $locations = Location::query()
            ->when(! $showHidden, fn ($query) => $query->where('is_active', true))
            ->withCount([
                'devices',
                'inventoryItems',
                'layoutObjects',
                'devices as active_devices_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->with(['layoutObjects' => fn ($query) => $query->orderBy('z_index')->limit(120)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('dashboard.locations.index', [
            'locations' => $locations,
            'onlineWindow' => $onlineWindow,
            'showHidden' => $showHidden,
        ]);
    }

    public function show(Location $location): View
    {
        abort_if(! $location->is_active && ! auth()->user()?->isAdmin(), 404);

        $location->load([
            'devices' => fn ($query) => $query->with(['sessions' => fn ($session) => $session->active()->with(['student', 'staff'])]),
            'layoutObjects' => fn ($query) => $query->orderBy('z_index')->orderBy('id'),
            'layoutObjects.device',
        ]);

        $placedIds = $location->layoutObjects->where('type', 'device')->pluck('device_id')->filter();
        $unplaced = $location->devices->whereNotIn('id', $placedIds);
        $inventory = InventoryItem::query()
            ->where('location_id', $location->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('dashboard.locations.show', [
            'location' => $location,
            'unplaced' => $unplaced,
            'inventory' => $inventory,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $name = trim($data['name']);
        $normalized = Location::normalizeName($name);

        if ($normalized === '') {
            return back()->withErrors(['name' => 'Nama ruangan tidak valid.'])->withInput();
        }

        $similar = Location::findSimilar($name);

        if ($similar !== null) {
            return back()->with('error', "Ruangan \"{$name}\" mirip dengan \"{$similar->name}\" yang sudah ada. Gunakan ruangan tersebut atau pilih nama yang berbeda.");
        }

        $location = Location::query()->create([
            'name' => $name,
            'normalized_name' => $normalized,
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        Audit::log(
            action: 'location_created',
            entityType: Location::class,
            entityId: $location->id,
            metadata: ['name' => $location->name],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with('status', "Ruangan {$location->name} ditambahkan.");
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $name = trim($data['name']);
        $normalized = Location::normalizeName($name);

        if ($normalized === '') {
            return back()->withErrors(['name' => 'Nama ruangan tidak valid.'])->withInput();
        }

        $similar = Location::findSimilar($name);

        if ($similar !== null && ! $similar->is($location)) {
            return back()->with('error', "Ruangan \"{$name}\" mirip dengan \"{$similar->name}\". Gunakan nama lain atau gabungkan ruangan.");
        }

        $before = $location->only(['name', 'is_active', 'sort_order']);

        $location->forceFill([
            'name' => $name,
            'normalized_name' => $normalized,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $data['sort_order'] ?? $location->sort_order,
        ])->save();

        Audit::log(
            action: 'location_updated',
            entityType: Location::class,
            entityId: $location->id,
            metadata: ['before' => $before, 'after' => $location->only(['name', 'is_active', 'sort_order'])],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with('status', 'Ruangan diperbarui.');
    }

    public function toggleVisibility(Request $request, Location $location): RedirectResponse
    {
        $location->forceFill(['is_active' => ! $location->is_active])->save();

        Audit::log(
            action: 'location_visibility_changed',
            entityType: Location::class,
            entityId: $location->id,
            metadata: ['name' => $location->name, 'is_active' => $location->is_active],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with(
            'status',
            $location->is_active
                ? "Ruangan {$location->name} ditampilkan kembali."
                : "Ruangan {$location->name} disembunyikan. Perangkat yang sudah terdaftar tetap menyimpan lokasi ini."
        );
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $data = $request->validate([
            'confirm_name' => ['required', 'string', 'max:80'],
        ]);

        $devices = $location->devices()->withTrashed()->count();
        $items = $location->inventoryItems()->count();
        $objects = $location->layoutObjects()->count();

        if ($devices > 0 || $items > 0 || $objects > 0) {
            return back()->with('error', 'Ruangan tidak bisa dihapus karena masih dipakai. Pindahkan perangkat/barang dan kosongkan denah terlebih dahulu.');
        }

        if (trim($data['confirm_name']) !== $location->name) {
            return back()->with('error', 'Nama ruangan yang diketik tidak cocok. Penghapusan dibatalkan.');
        }

        Audit::log(
            action: 'location_deleted',
            entityType: Location::class,
            entityId: $location->id,
            metadata: ['name' => $location->name],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        $location->delete();

        return redirect()->route('locations.index')->with('status', 'Ruangan dihapus.');
    }

    public function merge(Request $request, Location $location): RedirectResponse
    {
        $data = $request->validate([
            'target_id' => ['required', 'integer', 'exists:locations,id'],
        ]);

        $target = Location::query()->findOrFail($data['target_id']);

        if ($target->is($location)) {
            return back()->withErrors(['target_id' => 'Pilih ruangan tujuan yang berbeda.']);
        }

        if (! $target->is_active) {
            return back()->withErrors(['target_id' => 'Ruangan tujuan harus aktif.']);
        }

        DB::transaction(function () use ($location, $target, $request) {
            Device::withTrashed()->where('location_id', $location->id)->update([
                'location_id' => $target->id,
                'location_label' => $target->name,
            ]);

            InventoryItem::query()->where('location_id', $location->id)->update([
                'location_id' => $target->id,
            ]);

            // Denah sumber tidak dipindahkan agar tidak menimpa posisi di target;
            // perangkat yang dipindah akan muncul di panel "belum ditempatkan".
            LocationLayoutObject::query()->where('location_id', $location->id)->delete();

            Audit::log(
                action: 'location_merged',
                entityType: Location::class,
                entityId: $location->id,
                metadata: ['source' => $location->name, 'target' => $target->name, 'target_id' => $target->id],
                actorType: 'user',
                actorId: $request->user()->id,
                request: $request,
            );

            $location->delete();
        });

        return redirect()->route('locations.index')->with('status', "Ruangan {$location->name} digabungkan ke {$target->name}.");
    }

    public function saveLayout(Request $request, Location $location): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'objects' => ['present', 'array', 'max:500'],
            'objects.*.type' => ['required', 'in:device,table_rect,table_circle'],
            'objects.*.device_id' => ['nullable', 'integer'],
            'objects.*.label' => ['nullable', 'string', 'max:60'],
            'objects.*.x' => ['required', 'numeric', 'min:0', 'max:100'],
            'objects.*.y' => ['required', 'numeric', 'min:0', 'max:100'],
            'objects.*.w' => ['required', 'numeric', 'min:2', 'max:100'],
            'objects.*.h' => ['required', 'numeric', 'min:2', 'max:100'],
            'objects.*.rotation' => ['required', 'integer', 'in:0,90,180,270'],
            'objects.*.color' => ['nullable', 'string', 'max:20'],
            'objects.*.z_index' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $objects = collect($data['objects'])->map(function (array $object) {
            $type = $object['type'];
            $width = (float) $object['w'];
            $height = (float) $object['h'];

            if ($type === 'table_circle') {
                $height = $width;
            }

            return [
                'type' => $type,
                'device_id' => $type === 'device' ? (int) ($object['device_id'] ?? 0) : null,
                'label' => $object['label'] ?? null,
                'x' => round((float) $object['x'], 2),
                'y' => round((float) $object['y'], 2),
                'w' => round($width, 2),
                'h' => round($height, 2),
                'rotation' => (int) $object['rotation'],
                'color' => $object['color'] ?? null,
                'z_index' => (int) ($object['z_index'] ?? ($type === 'device' ? 20 : 5)),
            ];
        });

        foreach ($objects as $object) {
            if ($object['x'] + $object['w'] > 100.01 || $object['y'] + $object['h'] > 100.01) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Ada objek yang keluar dari kanvas. Perkecil atau geser kembali.',
                ], 422);
            }

            if ($object['type'] === 'device' && $object['device_id'] <= 0) {
                return response()->json(['ok' => false, 'message' => 'Objek perangkat tidak valid.'], 422);
            }
        }

        $deviceIds = $objects->where('type', 'device')->pluck('device_id')->filter()->values();

        if ($deviceIds->duplicates()->isNotEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Ada perangkat yang ditempatkan lebih dari sekali.'], 422);
        }

        if ($deviceIds->isNotEmpty()) {
            $belongs = Device::query()
                ->whereIn('id', $deviceIds)
                ->where('location_id', $location->id)
                ->count();

            if ($belongs !== $deviceIds->unique()->count()) {
                return response()->json(['ok' => false, 'message' => 'Ada perangkat yang bukan anggota ruangan ini.'], 422);
            }
        }

        $newVersion = DB::transaction(function () use ($location, $data, $objects, $request) {
            /** @var Location $locked */
            $locked = Location::query()->whereKey($location->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->layout_version !== (int) $data['version']) {
                abort(409, 'Layout sudah diubah admin lain. Muat ulang halaman.');
            }

            LocationLayoutObject::query()->where('location_id', $locked->id)->delete();

            foreach ($objects as $object) {
                LocationLayoutObject::query()->create(array_merge($object, [
                    'location_id' => $locked->id,
                ]));
            }

            $locked->increment('layout_version');

            Audit::log(
                action: 'location_layout_saved',
                entityType: Location::class,
                entityId: $locked->id,
                metadata: ['objects' => $objects->count(), 'version' => $locked->layout_version],
                actorType: 'user',
                actorId: $request->user()->id,
                request: $request,
            );

            return $locked->layout_version;
        });

        return response()->json(['ok' => true, 'version' => $newVersion]);
    }

    public function uploadBackground(Request $request, Location $location): RedirectResponse
    {
        $request->validate([
            'background' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);

        $file = $request->file('background');
        $info = @getimagesize((string) $file->getRealPath());

        if ($info === false) {
            return back()->withErrors(['background' => 'Gambar denah tidak valid.']);
        }

        [$width, $height] = $info;

        if ($width < 200 || $height < 150 || $width > 6000 || $height > 6000) {
            return back()->withErrors(['background' => 'Ukuran gambar harus antara 200×150 dan 6000×6000 piksel.']);
        }

        $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $path = 'locations/backgrounds/'.$location->id.'-'.time().'.'.$extension;
        Storage::disk('local')->put($path, $file->get());

        if ($location->background_path !== null) {
            Storage::disk('local')->delete($location->background_path);
        }

        $location->forceFill([
            'background_path' => $path,
            'canvas_width' => $width,
            'canvas_height' => $height,
        ])->save();

        Audit::log(
            action: 'location_background_updated',
            entityType: Location::class,
            entityId: $location->id,
            metadata: ['path' => $path, 'width' => $width, 'height' => $height],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with('status', 'Gambar denah diperbarui. Periksa kembali posisi objek.');
    }

    public function background(Location $location): BinaryFileResponse
    {
        abort_unless($location->background_path !== null && Storage::disk('local')->exists($location->background_path), 404);

        return response()->file(Storage::disk('local')->path($location->background_path));
    }
}
