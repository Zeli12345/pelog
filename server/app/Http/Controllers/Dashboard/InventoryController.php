<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $categoryId = (int) $request->query('category_id', 0);
        $locationId = (int) $request->query('location_id', 0);
        $condition = (string) $request->query('condition', '');
        $lowOnly = $request->boolean('low');
        $archived = $request->boolean('archived');

        $items = InventoryItem::query()
            ->with(['category', 'location'])
            ->when($archived, fn ($query) => $query->where('is_active', false), fn ($query) => $query->where('is_active', true))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('position_detail', 'like', "%{$search}%");
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($locationId > 0, fn ($query) => $query->where('location_id', $locationId))
            ->when($condition !== '', fn ($query) => $query->where('condition', $condition))
            ->when($lowOnly, fn ($query) => $query->whereColumn('quantity', '<=', 'min_quantity'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $base = InventoryItem::query()->where('is_active', true);

        return view('dashboard.inventory.index', [
            'items' => $items,
            'categories' => InventoryCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'search' => $search,
            'categoryId' => $categoryId,
            'locationId' => $locationId,
            'condition' => $condition,
            'lowOnly' => $lowOnly,
            'archived' => $archived,
            'stats' => [
                'items' => (clone $base)->count(),
                'units' => (int) (clone $base)->sum('quantity'),
                'low' => (clone $base)->whereColumn('quantity', '<=', 'min_quantity')->count(),
                'damaged' => (clone $base)->whereIn('condition', ['damaged', 'repair'])->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('dashboard.inventory.form', [
            'item' => new InventoryItem(['unit' => 'pcs', 'condition' => 'good', 'is_active' => true]),
            'categories' => InventoryCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'attributesText' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $initial = max(0, (int) ($data['initial_quantity'] ?? 0));
        unset($data['initial_quantity']);

        $item = DB::transaction(function () use ($request, $data, $initial) {
            $item = InventoryItem::query()->create(array_merge($data, [
                'quantity' => 0,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]));

            if ($initial > 0) {
                $item->forceFill(['quantity' => $initial])->save();

                InventoryMovement::query()->create([
                    'inventory_item_id' => $item->id,
                    'type' => 'in',
                    'quantity' => $initial,
                    'quantity_before' => 0,
                    'quantity_after' => $initial,
                    'location_to_id' => $item->location_id,
                    'reference' => 'Stok awal',
                    'actor_id' => $request->user()->id,
                    'occurred_at' => now(),
                ]);
            }

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->storeAs(
                    'inventory/items',
                    'item-'.$item->id.'-'.Str::uuid().'.'.strtolower($request->file('photo')->getClientOriginalExtension()),
                    'local'
                );
                $item->forceFill(['photo_path' => $path])->save();
            }

            Audit::log(
                action: 'inventory_item_created',
                entityType: InventoryItem::class,
                entityId: $item->id,
                metadata: ['name' => $item->name, 'initial_quantity' => $initial],
                actorType: 'user',
                actorId: $request->user()->id,
                request: $request,
            );

            return $item;
        });

        return redirect()->route('inventory.show', $item)->with('status', 'Barang inventaris ditambahkan.');
    }

    public function show(InventoryItem $item): View
    {
        $item->load(['category', 'location', 'movements.actor', 'movements.locationFrom', 'movements.locationTo']);

        return view('dashboard.inventory.show', [
            'item' => $item,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(InventoryItem $item): View
    {
        return view('dashboard.inventory.form', [
            'item' => $item,
            'categories' => InventoryCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'attributesText' => collect($item->attributes ?? [])->map(fn ($value, $key) => $key.': '.$value)->implode("\n"),
        ]);
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $this->validated($request, $item);

        DB::transaction(function () use ($request, $item, $data) {
            if ($request->hasFile('photo')) {
                if ($item->photo_path !== null) {
                    Storage::disk('local')->delete($item->photo_path);
                }

                $data['photo_path'] = $request->file('photo')->storeAs(
                    'inventory/items',
                    'item-'.$item->id.'-'.Str::uuid().'.'.strtolower($request->file('photo')->getClientOriginalExtension()),
                    'local'
                );
            }

            $before = $item->only(['name', 'code', 'category_id', 'min_quantity', 'location_id', 'position_detail', 'condition', 'is_active']);
            $data['updated_by'] = $request->user()->id;
            $item->forceFill($data)->save();

            Audit::log(
                action: 'inventory_item_updated',
                entityType: InventoryItem::class,
                entityId: $item->id,
                metadata: ['before' => $before, 'after' => $item->only(array_keys($before))],
                actorType: 'user',
                actorId: $request->user()->id,
                request: $request,
            );
        });

        return redirect()->route('inventory.show', $item)->with('status', 'Barang inventaris diperbarui.');
    }

    public function recordMovement(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out,adjust,transfer,repair,return,write_off'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'location_to_id' => ['nullable', 'integer', 'exists:locations,id'],
            'reference' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($request, $item, $data) {
                /** @var InventoryItem $locked */
                $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

                if (! $locked->is_active) {
                    throw new \RuntimeException('Barang sudah diarsipkan.');
                }

                $before = (int) $locked->quantity;
                $type = $data['type'];
                $quantity = (int) $data['quantity'];
                $after = $before;
                $moved = $quantity;

                switch ($type) {
                    case 'in':
                    case 'return':
                        $after = $before + $quantity;
                        break;
                    case 'out':
                    case 'repair':
                    case 'write_off':
                        if ($quantity > $before) {
                            throw new \RuntimeException('Jumlah keluar melebihi stok tersedia.');
                        }
                        $after = $before - $quantity;
                        break;
                    case 'adjust':
                        $after = $quantity;
                        $moved = abs($after - $before);
                        break;
                    case 'transfer':
                        if (($data['location_to_id'] ?? null) === null) {
                            throw new \RuntimeException('Pilih lokasi tujuan transfer.');
                        }
                        $after = $before;
                        $moved = $before;
                        $locked->forceFill(['location_id' => $data['location_to_id']])->save();
                        break;
                }

                if ($after < 0) {
                    throw new \RuntimeException('Stok tidak boleh negatif.');
                }

                $fromLocation = $locked->location_id;
                $locked->forceFill([
                    'quantity' => $after,
                    'updated_by' => $request->user()->id,
                ])->save();

                InventoryMovement::query()->create([
                    'inventory_item_id' => $locked->id,
                    'type' => $type,
                    'quantity' => $moved,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'location_from_id' => $fromLocation,
                    'location_to_id' => $type === 'transfer' ? $data['location_to_id'] : null,
                    'reference' => $data['reference'] ?? null,
                    'note' => $data['note'] ?? null,
                    'actor_id' => $request->user()->id,
                    'occurred_at' => now(),
                ]);

                Audit::log(
                    action: 'inventory_movement_recorded',
                    entityType: InventoryItem::class,
                    entityId: $locked->id,
                    metadata: ['type' => $type, 'quantity' => $moved, 'before' => $before, 'after' => $after],
                    actorType: 'user',
                    actorId: $request->user()->id,
                    request: $request,
                );
            });
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Pergerakan stok dicatat.');
    }

    public function archive(Request $request, InventoryItem $item): RedirectResponse
    {
        $item->forceFill(['is_active' => false, 'updated_by' => $request->user()->id])->save();

        Audit::log(
            action: 'inventory_item_archived',
            entityType: InventoryItem::class,
            entityId: $item->id,
            metadata: ['name' => $item->name],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('inventory.index')->with('status', 'Barang diarsipkan.');
    }

    public function restore(Request $request, InventoryItem $item): RedirectResponse
    {
        $item->forceFill(['is_active' => true, 'updated_by' => $request->user()->id])->save();

        Audit::log(
            action: 'inventory_item_restored',
            entityType: InventoryItem::class,
            entityId: $item->id,
            metadata: ['name' => $item->name],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('inventory.index', ['archived' => 1])->with('status', 'Barang dipulihkan.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $name = trim($data['name']);
        $existing = InventoryCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing !== null) {
            return back()->with('error', "Kategori \"{$name}\" sudah ada.");
        }

        $category = InventoryCategory::query()->create([
            'name' => $name,
            'is_active' => true,
            'sort_order' => (int) InventoryCategory::query()->max('sort_order') + 1,
        ]);

        Audit::log(
            action: 'inventory_category_created',
            entityType: InventoryCategory::class,
            entityId: $category->id,
            metadata: ['name' => $category->name],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with('status', 'Kategori inventaris ditambahkan.');
    }

    public function photo(InventoryItem $item): BinaryFileResponse
    {
        abort_unless($item->photo_path !== null && Storage::disk('local')->exists($item->photo_path), 404);

        return response()->file(Storage::disk('local')->path($item->photo_path));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?InventoryItem $item = null): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', 'unique:inventory_items,code'.($item ? ','.$item->id : '')],
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'min_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'initial_quantity' => [$item ? 'nullable' : 'nullable', 'integer', 'min:0', 'max:1000000'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'position_detail' => ['nullable', 'string', 'max:120'],
            'condition' => ['required', 'in:new,good,damaged,repair'],
            'attributes_text' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'photo' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $attributes = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('attributes_text', '')) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key !== '' && $value !== '') {
                $attributes[mb_substr($key, 0, 40)] = mb_substr($value, 0, 120);
            }

            if (count($attributes) >= 20) {
                break;
            }
        }

        $data = [
            'name' => trim((string) $request->input('name')),
            'code' => trim((string) $request->input('code', '')) ?: null,
            'category_id' => $request->input('category_id') ?: null,
            'unit' => trim((string) $request->input('unit', 'pcs')) ?: 'pcs',
            'min_quantity' => (int) $request->input('min_quantity', 0),
            'location_id' => $request->input('location_id') ?: null,
            'position_detail' => trim((string) $request->input('position_detail', '')) ?: null,
            'condition' => (string) $request->input('condition', 'good'),
            'attributes' => $attributes === [] ? null : $attributes,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($item === null) {
            $data['initial_quantity'] = (int) $request->input('initial_quantity', 0);
        }

        return $data;
    }
}
