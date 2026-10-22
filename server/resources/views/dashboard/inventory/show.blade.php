<x-dashboard-layout :title="'Inventaris — '.$item->name" subtitle="Detail barang, stok, dan riwayat pergerakan">
    @php
        $isAdmin = auth()->user()->isAdmin();
        $typeLabels = [
            'in' => 'Masuk', 'out' => 'Keluar', 'adjust' => 'Penyesuaian', 'transfer' => 'Transfer',
            'repair' => 'Kirim perbaikan', 'return' => 'Kembali dari perbaikan', 'write_off' => 'Hapus / hilang',
        ];
        $conditionLabels = ['new' => 'Baru', 'good' => 'Baik', 'damaged' => 'Rusak', 'repair' => 'Perbaikan'];
    @endphp

    <x-slot:actions>
        <a href="{{ route('inventory.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
        @if ($isAdmin)
            <a href="{{ route('inventory.edit', $item) }}" class="btn-primary !py-1.5 text-xs">Edit Barang</a>
        @endif
    </x-slot:actions>

    <div class="space-y-4" x-data="{ moveOpen: false, moveType: 'in' }">
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="card p-5 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-ink">{{ $item->name }}</h2>
                            <span class="badge-muted">{{ $conditionLabels[$item->condition] ?? $item->condition }}</span>
                            @if (! $item->is_active)<span class="badge-danger">Arsip</span>@endif
                            @if ($item->isLowStock())<span class="badge-warn">Stok menipis</span>@endif
                        </div>
                        <p class="mt-1 font-mono text-xs text-ink-faint">{{ $item->code ?: 'tanpa kode' }} · {{ $item->category?->name ?? 'tanpa kategori' }}</p>
                    </div>
                    @if ($isAdmin && $item->is_active)
                        <button type="button" class="btn-primary !py-1.5 text-xs" x-on:click="moveOpen = true; moveType = 'in'">Mutasi Stok</button>
                    @endif
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-4 text-sm lg:grid-cols-4">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Stok</dt>
                        <dd class="mt-0.5 font-mono text-lg font-bold {{ $item->isLowStock() ? 'text-brick-600' : 'text-ink' }}">{{ $item->quantity }} <span class="text-xs font-normal text-ink-faint">{{ $item->unit }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Minimum</dt>
                        <dd class="mt-0.5 font-mono text-sm text-ink-soft">{{ $item->min_quantity }} {{ $item->unit }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Lokasi</dt>
                        <dd class="mt-0.5 text-ink-soft">{{ $item->location?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Posisi</dt>
                        <dd class="mt-0.5 text-ink-soft">{{ $item->position_detail ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($item->attributes)
                    <div class="mt-4 border-t border-line pt-4">
                        <p class="text-[11px] uppercase tracking-wide text-ink-faint">Atribut</p>
                        <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($item->attributes as $key => $value)
                                <div class="rounded-md border border-line px-2.5 py-1.5">
                                    <dt class="text-[10px] uppercase tracking-wide text-ink-faint">{{ $key }}</dt>
                                    <dd class="text-xs text-ink">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                @if ($item->notes)
                    <div class="mt-4 border-t border-line pt-4">
                        <p class="text-[11px] uppercase tracking-wide text-ink-faint">Catatan</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-ink-soft">{{ $item->notes }}</p>
                    </div>
                @endif
            </section>

            <aside class="space-y-4">
                @if ($item->photo_path)
                    <section class="card overflow-hidden">
                        <img src="{{ route('inventory.photo', $item) }}" alt="{{ $item->name }}" class="aspect-video w-full object-cover">
                    </section>
                @endif

                <section class="card p-4">
                    <h2 class="text-sm font-semibold text-ink">Aksi</h2>
                    <div class="mt-2 space-y-2 text-xs">
                        @if ($isAdmin)
                            @if ($item->is_active)
                                <form method="POST" action="{{ route('inventory.archive', $item) }}" onsubmit="return confirm('Arsipkan barang ini?')">
                                    @csrf
                                    <button type="submit" class="btn-secondary w-full !py-1.5 text-xs text-brick-600">Arsipkan Barang</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('inventory.restore', $item) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary w-full !py-1.5 text-xs">Pulihkan Barang</button>
                                </form>
                            @endif
                        @endif
                        <a href="{{ route('inventory.index', ['location_id' => $item->location_id]) }}" class="btn-secondary block w-full !py-1.5 text-center text-xs">Lihat Barang di Lokasi Ini</a>
                    </div>
                </section>
            </aside>
        </div>

        <section class="card overflow-hidden">
            <header class="border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold text-ink">Riwayat Pergerakan</h2>
                <p class="mt-0.5 text-[11px] text-ink-faint">Semua perubahan stok tercatat; stok tidak bisa berubah tanpa jejak.</p>
            </header>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="px-4 py-2.5 text-left font-semibold">Waktu</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Jenis</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Jumlah</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Stok</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Referensi</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line/70">
                        @forelse ($item->movements as $movement)
                            <tr class="table-row">
                                <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $movement->occurred_at?->timezone('Asia/Makassar')->format('d/m/Y H:i') }} WITA</td>
                                <td class="px-4 py-2.5 text-xs text-ink">{{ $typeLabels[$movement->type] ?? $movement->type }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-ink-soft">{{ $movement->quantity }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-ink-soft">{{ $movement->quantity_before }} → {{ $movement->quantity_after }}</td>
                                <td class="px-4 py-2.5 text-xs text-ink-soft">
                                    {{ $movement->reference ?: '—' }}
                                    @if ($movement->type === 'transfer')
                                        <p class="text-[10px] text-ink-faint">{{ $movement->locationFrom?->name ?? '—' }} → {{ $movement->locationTo?->name ?? '—' }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $movement->actor?->name ?? 'Sistem' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-ink-faint">Belum ada pergerakan stok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($isAdmin)
            <div x-show="moveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4" x-on:keydown.escape.window="moveOpen = false">
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl" x-on:click.outside="moveOpen = false">
                    <h2 class="text-sm font-semibold text-ink">Mutasi Stok — {{ $item->name }}</h2>
                    <p class="mt-0.5 text-xs text-ink-faint">Stok sekarang: {{ $item->quantity }} {{ $item->unit }}</p>
                    <form method="POST" action="{{ route('inventory.movements.store', $item) }}" class="mt-4 space-y-3">
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
                            <input type="number" name="quantity" min="0" max="1000000" class="input text-sm" required>
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
                            <input type="text" name="reference" maxlength="160" class="input text-sm" placeholder="mis. dipasang di PC-04">
                        </div>
                        <div>
                            <label class="label">Catatan</label>
                            <input type="text" name="note" maxlength="255" class="input text-sm">
                        </div>
                        <div class="flex justify-end gap-2 border-t border-line pt-3">
                            <button type="button" class="btn-secondary" x-on:click="moveOpen = false">Batal</button>
                            <button type="submit" class="btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-dashboard-layout>
