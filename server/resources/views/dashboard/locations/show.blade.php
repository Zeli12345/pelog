<x-dashboard-layout :title="'Denah — '.$location->name" subtitle="Tata letak perangkat & meja">
    @php
        $isAdmin = auth()->user()->isAdmin();
        $layoutObjects = $location->layoutObjects->map(function ($object) {
            return [
                'id' => $object->id,
                'type' => $object->type,
                'device_id' => $object->device_id,
                'label' => $object->label,
                'x' => (float) $object->x,
                'y' => (float) $object->y,
                'w' => (float) $object->w,
                'h' => (float) $object->h,
                'rotation' => (int) $object->rotation,
                'color' => $object->color,
                'z_index' => (int) $object->z_index,
                'device' => $object->device === null ? null : [
                    'name' => $object->device->label ?? $object->device->hostname,
                    'hostname' => $object->device->hostname,
                    'status' => $object->device->status->value,
                    'href' => route('devices.show', $object->device),
                ],
            ];
        })->values();

        $unplacedPayload = $unplaced->map(fn ($device) => [
            'id' => $device->id,
            'name' => $device->label ?? $device->hostname,
            'hostname' => $device->hostname,
            'status' => $device->status->value,
            'href' => route('devices.show', $device),
        ])->values();
    @endphp

    <x-slot:actions>
        <a href="{{ route('locations.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    <div class="space-y-4" id="layout-editor"
         data-edit="{{ $isAdmin ? '1' : '0' }}"
         data-save-url="{{ route('locations.layout', $location) }}"
         data-version="{{ $location->layout_version }}"
         data-background="{{ $location->background_path ? route('locations.background', $location) : '' }}">
        <section class="card p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-ink">{{ $location->name }}</h2>
                        @if (! $location->is_active)
                            <span class="badge-muted">Arsip</span>
                        @endif
                        <span class="badge-ok">{{ $location->devices->count() }} perangkat</span>
                        <span class="badge-muted">{{ $inventory->count() }} barang</span>
                    </div>
                    <p class="mt-1 text-xs text-ink-faint">Geser perangkat untuk mengatur posisi. Gunakan tombol meja untuk menggambar meja kotak atau bulat.</p>
                </div>
                @if ($isAdmin)
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-secondary !py-1.5 text-xs" data-action="toggle-edit">Atur Layout</button>
                        <button type="button" class="btn-primary !py-1.5 text-xs hidden" data-action="save">Simpan Layout</button>
                        <button type="button" class="btn-secondary !py-1.5 text-xs hidden" data-action="cancel">Batal</button>
                    </div>
                @endif
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_280px]">
            <section class="card overflow-hidden">
                @if ($isAdmin)
                    <div class="hidden flex-wrap items-center gap-2 border-b border-line bg-paper/60 px-3 py-2" data-toolbar>
                        <button type="button" class="btn-secondary !px-2 !py-1 text-[11px]" data-action="select">Pilih</button>
                        <button type="button" class="btn-secondary !px-2 !py-1 text-[11px]" data-action="add-table-rect">+ Meja Kotak</button>
                        <button type="button" class="btn-secondary !px-2 !py-1 text-[11px]" data-action="add-table-circle">+ Meja Bulat</button>
                        <span class="mx-1 h-5 w-px bg-line"></span>
                        <button type="button" class="btn-secondary !px-2 !py-1 text-[11px]" data-action="auto-layout">Susun Otomatis</button>
                        <button type="button" class="btn-secondary !px-2 !py-1 text-[11px]" data-action="toggle-grid">Grid</button>
                        <span class="ml-auto text-[11px] text-ink-faint" data-hint>Klik objek untuk memilih.</span>
                    </div>
                @endif

                <div class="relative w-full overflow-hidden bg-paper"
                     id="layout-canvas"
                     style="aspect-ratio: {{ max(200, $location->canvas_width) }} / {{ max(150, $location->canvas_height) }};">
                    @if ($location->background_path)
                        <img src="{{ route('locations.background', $location) }}" alt="Denah {{ $location->name }}" class="absolute inset-0 h-full w-full object-fill opacity-80">
                    @endif
                    <div class="absolute inset-0" id="layout-grid"
                         style="background-image: linear-gradient(to right, rgba(25,28,32,.06) 1px, transparent 1px), linear-gradient(to bottom, rgba(25,28,32,.06) 1px, transparent 1px); background-size: 5% 5%;"></div>
                    <div class="absolute inset-0" id="layout-objects"></div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-3 py-2 text-[11px] text-ink-faint">
                    <span>Skala: {{ $location->canvas_width }} × {{ $location->canvas_height }} · Versi layout {{ $location->layout_version }}</span>
                    <span data-dirty class="hidden font-semibold text-gold-700">Belum disimpan</span>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="card p-4">
                    <h3 class="text-sm font-semibold text-ink">Panel</h3>
                    <div class="mt-2 text-xs text-ink-soft" data-selection-panel>
                        <p class="text-ink-faint">Pilih objek pada denah.</p>
                    </div>
                </section>

                <section class="card p-4">
                    <h3 class="text-sm font-semibold text-ink">Filter Perangkat</h3>
                    <input type="text" data-device-filter class="input mt-2 text-xs" placeholder="Cari nama / hostname…">
                    <select data-status-filter class="input mt-2 text-xs">
                        <option value="">Semua status</option>
                        <option value="available">Tersedia</option>
                        <option value="in_use">Digunakan</option>
                        <option value="maintenance">Perawatan</option>
                        <option value="offline">Offline</option>
                    </select>
                    <p class="mt-2 text-[11px] text-ink-faint" data-filter-count>Menampilkan semua perangkat.</p>
                </section>

                <section class="card p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-ink">Belum Ditempatkan</h3>
                        <span class="badge-muted">{{ $unplaced->count() }}</span>
                    </div>
                    <p class="mt-1 text-[11px] text-ink-faint">Perangkat di ruangan ini yang belum punya posisi di denah.</p>
                    <div class="mt-3 space-y-1.5" data-unplaced>
                        @forelse ($unplaced as $device)
                            <div class="flex items-center justify-between gap-2 rounded-md border border-line px-2 py-1.5"
                                 data-device-item
                                 data-name="{{ strtolower(($device->label ?? '').' '.$device->hostname) }}"
                                 data-status="{{ $device->status->value }}">
                                <span class="min-w-0 truncate text-xs text-ink">{{ $device->label ?? $device->hostname }}</span>
                                @if ($isAdmin)
                                    <button type="button" class="btn-secondary !px-1.5 !py-0.5 text-[10px]" data-place-device="{{ $device->id }}" data-name="{{ $device->label ?? $device->hostname }}">Tempatkan</button>
                                @endif
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-faint">Semua perangkat sudah ditempatkan.</p>
                        @endforelse
                    </div>
                </section>

                @if ($inventory->isNotEmpty())
                    <section class="card p-4">
                        <h3 class="text-sm font-semibold text-ink">Barang di Ruangan</h3>
                        <div class="mt-2 space-y-1.5">
                            @foreach ($inventory as $item)
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="truncate text-ink-soft">{{ $item->name }}</span>
                                    <span class="font-mono text-[11px] text-ink-faint">{{ $item->quantity }} {{ $item->unit }}</span>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('inventory.index', ['location_id' => $location->id]) }}" class="mt-3 inline-block text-[11px] font-semibold text-navy-800 hover:underline">Lihat inventaris ruangan →</a>
                    </section>
                @endif

                <section class="card p-4">
                    <h3 class="text-sm font-semibold text-ink">Cara Mengatur</h3>
                    <ol class="mt-2 list-decimal space-y-1 pl-4 text-[11px] leading-relaxed text-ink-soft">
                        <li>Klik <strong>Atur Layout</strong>.</li>
                        <li>Tambahkan meja kotak/bulat bila perlu.</li>
                        <li>Tarik perangkat dari panel atau dari denah.</li>
                        <li>Klik <strong>Simpan Layout</strong>.</li>
                    </ol>
                </section>
            </aside>
        </div>
    </div>

    <script type="application/json" id="layout-data">@json($layoutObjects)</script>
    <script type="application/json" id="layout-unplaced">@json($unplacedPayload)</script>
    @vite('resources/js/layout-editor.js')
</x-dashboard-layout>
