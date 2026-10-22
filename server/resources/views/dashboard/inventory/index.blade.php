<x-dashboard-layout title="Inventaris" subtitle="Stok barang, sparepart, dan posisi penyimpanan">
    @php
        $isAdmin = auth()->user()->isAdmin();
        $conditionLabels = ['new' => 'Baru', 'good' => 'Baik', 'damaged' => 'Rusak', 'repair' => 'Perbaikan'];
        $conditionClasses = ['new' => 'badge-ok', 'good' => 'badge-muted', 'damaged' => 'badge-danger', 'repair' => 'badge-warn'];
    @endphp

    <x-slot:actions>
        @if ($isAdmin)
            <button type="button" class="btn-secondary !py-1.5 text-xs" x-data x-on:click="$dispatch('open-category')">Kelola Kategori</button>
            <a href="{{ route('inventory.create') }}" class="btn-primary !py-1.5 text-xs">
                <x-icon name="plus" size="h-3.5 w-3.5" />
                Tambah Barang
            </a>
        @endif
    </x-slot:actions>

    <div class="space-y-4" x-data="{ move: null, moveType: 'in', categoryOpen: false }" x-on:open-category.window="categoryOpen = true">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card label="Jenis Barang" :value="$stats['items']" icon="box" tone="navy" />
            <x-stat-card label="Total Unit" :value="$stats['units']" icon="hard-drive" tone="moss" />
            <x-stat-card label="Stok Menipis" :value="$stats['low']" icon="alert-triangle" tone="gold" />
            <x-stat-card label="Rusak / Perbaikan" :value="$stats['damaged']" icon="alert-triangle" tone="brick" />
        </div>

        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <form method="GET" action="{{ route('inventory.index') }}" class="flex flex-wrap items-center gap-2">
                    @if ($archived)<input type="hidden" name="archived" value="1">@endif
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / kode / posisi…" class="input !w-48 py-1.5 text-xs">
                    <select name="category_id" class="input !w-36 py-1.5 text-xs">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <select name="location_id" class="input !w-36 py-1.5 text-xs">
                        <option value="">Semua lokasi</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    <select name="condition" class="input !w-32 py-1.5 text-xs">
                        <option value="">Semua kondisi</option>
                        @foreach ($conditionLabels as $value => $label)
                            <option value="{{ $value }}" @selected($condition === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-1.5 text-xs text-ink-soft">
                        <input type="checkbox" name="low" value="1" @checked($lowOnly) class="rounded border-line text-navy-700">
                        Low stock
                    </label>
                    <button type="submit" class="btn-primary !py-1.5 text-xs">Terapkan</button>
                    @if ($search !== '' || $categoryId > 0 || $locationId > 0 || $condition !== '' || $lowOnly)
                        <a href="{{ route('inventory.index', $archived ? ['archived' => 1] : []) }}" class="btn-secondary !py-1.5 text-xs">Reset</a>
                    @endif
                </form>
                <div class="flex items-center gap-2 text-xs text-ink-faint">
                    <span>{{ $items->total() }} barang</span>
                    @if ($isAdmin)
                        <a href="{{ $archived ? route('inventory.index') : route('inventory.index', ['archived' => 1]) }}"
                           class="font-semibold text-navy-800 hover:underline">
                            {{ $archived ? 'Lihat aktif' : 'Lihat arsip' }}
                        </a>
                    @endif
                </div>
            </header>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="px-4 py-2.5 text-left font-semibold">Barang</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Kategori</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Stok</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Lokasi / Posisi</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Kondisi</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line/70">
                        @forelse ($items as $item)
                            <tr class="table-row {{ ! $item->is_active ? 'opacity-60' : '' }}">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('inventory.show', $item) }}" class="font-medium text-ink hover:underline">{{ $item->name }}</a>
                                    <p class="font-mono text-[11px] text-ink-faint">{{ $item->code ?: 'tanpa kode' }} · {{ $item->unit }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $item->category?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="font-mono text-xs font-semibold {{ $item->isLowStock() ? 'text-brick-600' : 'text-ink' }}">
                                        {{ $item->quantity }} {{ $item->unit }}
                                    </span>
                                    <p class="text-[10px] text-ink-faint">min. {{ $item->min_quantity }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-ink-soft">
                                    {{ $item->location?->name ?? '—' }}
                                    @if ($item->position_detail)
                                        <p class="text-[11px] text-ink-faint">{{ $item->position_detail }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="{{ $conditionClasses[$item->condition] ?? 'badge-muted' }}">{{ $conditionLabels[$item->condition] ?? $item->condition }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($isAdmin)
                                            @if ($item->is_active)
                                                <button type="button" class="btn-secondary !px-2 !py-1 text-xs"
                                                        x-on:click="move = {{ \Illuminate\Support\Js::from([
                                                            'id' => $item->id,
                                                            'name' => $item->name,
                                                            'quantity' => $item->quantity,
                                                            'unit' => $item->unit,
                                                            'location_id' => $item->location_id,
                                                        ]) }}; moveType = 'in'">
                                                    Mutasi
                                                </button>
                                                <a href="{{ route('inventory.edit', $item) }}" class="btn-secondary !px-2 !py-1 text-xs" title="Edit"><x-icon name="pencil" size="h-3.5 w-3.5" /></a>
                                                <form method="POST" action="{{ route('inventory.archive', $item) }}" onsubmit="return confirm('Arsipkan barang ini? Riwayat tetap tersimpan.')">
                                                    @csrf
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs text-brick-600" title="Arsipkan"><x-icon name="trash" size="h-3.5 w-3.5" /></button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('inventory.restore', $item) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs">Pulihkan</button>
                                                </form>
                                            @endif
                                        @else
                                            <a href="{{ route('inventory.show', $item) }}" class="btn-secondary !px-2 !py-1 text-xs">Detail</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-ink-faint">
                                    Belum ada barang inventaris yang cocok.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-line px-4 py-3">{{ $items->links() }}</div>
        </section>

        @if ($isAdmin)
            <div x-show="move !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4" x-on:keydown.escape.window="move = null">
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl" x-on:click.outside="move = null">
                    <h2 class="text-sm font-semibold text-ink">Catat Pergerakan Stok</h2>
                    <p class="mt-0.5 text-xs text-ink-faint" x-text="move ? `${move.name} · stok ${move.quantity} ${move.unit}` : ''"></p>

                    <form method="POST" :action="move ? `/inventory/${move.id}/movements` : ''" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="label">Jenis</label>
                            <select name="type" class="input text-sm" x-model="moveType" required>
                                <option value="in">Masuk</option>
                                <option value="out">Keluar</option>
                                <option value="adjust">Penyesuaian (set stok baru)</option>
                                <option value="transfer">Transfer lokasi</option>
                                <option value="repair">Kirim perbaikan</option>
                                <option value="return">Kembali dari perbaikan</option>
                                <option value="write_off">Hapus / hilang</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Jumlah / stok baru</label>
                            <input type="number" name="quantity" min="0" max="1000000" class="input text-sm" required x-bind:value="moveType === 'adjust' ? move?.quantity : ''">
                        </div>
                        <div x-show="moveType === 'transfer'">
                            <label class="label">Lokasi tujuan</label>
                            <select name="location_to_id" class="input text-sm">
                                <option value="">Pilih lokasi…</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Referensi</label>
                            <input type="text" name="reference" maxlength="160" class="input text-sm" placeholder="mis. dipasang di PC-04 / dipakai Bu Wayan">
                        </div>
                        <div>
                            <label class="label">Catatan</label>
                            <input type="text" name="note" maxlength="255" class="input text-sm">
                        </div>
                        <div class="flex justify-end gap-2 border-t border-line pt-3">
                            <button type="button" class="btn-secondary" x-on:click="move = null">Batal</button>
                            <button type="submit" class="btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="categoryOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4" x-on:keydown.escape.window="categoryOpen = false">
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl" x-on:click.outside="categoryOpen = false">
                    <h2 class="text-sm font-semibold text-ink">Kelola Kategori</h2>
                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                        @forelse ($categories as $category)
                            <div class="rounded-md border border-line px-2.5 py-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('inventory.categories.update', $category) }}" class="flex min-w-0 flex-1 items-center gap-1.5">
                                        @csrf
                                        @method('PUT')
                                        <input name="name" value="{{ $category->name }}" class="input !py-1 text-xs" maxlength="60" required>
                                        <button type="submit" class="btn-secondary !px-2 !py-1 text-[10px]">Simpan</button>
                                    </form>
                                    <form method="POST" action="{{ route('inventory.categories.destroy', $category) }}"
                                          onsubmit="return confirm('{{ $category->items_count > 0 ? 'Kategori masih dipakai. Arsipkan kategori ini?' : 'Hapus kategori ini?' }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-secondary !px-2 !py-1 text-[10px] text-brick-600">
                                            {{ $category->items_count > 0 ? 'Arsipkan' : 'Hapus' }}
                                        </button>
                                    </form>
                                </div>
                                <p class="mt-1 text-[10px] text-ink-faint">
                                    {{ $category->items_count }} barang · {{ $category->is_active ? 'aktif' : 'arsip — klik Simpan untuk mengaktifkan' }}
                                </p>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-line px-3 py-4 text-center text-[11px] text-ink-faint">
                                Belum ada kategori. Tambahkan kategori pertama di bawah.
                            </p>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('inventory.categories.store') }}" class="mt-4 flex gap-2">
                        @csrf
                        <input type="text" name="name" class="input text-sm" placeholder="Nama kategori baru" required maxlength="60">
                        <button type="submit" class="btn-primary !py-1.5 text-xs">Tambah</button>
                    </form>
                    <p class="mt-2 text-[11px] text-ink-faint">Kategori bisa untuk apa saja: elektronik, ATK, kabel, bahan praktik, dan lainnya.</p>
                </div>
            </div>
        @endif
    </div>
</x-dashboard-layout>
