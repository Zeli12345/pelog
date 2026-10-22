<x-dashboard-layout :title="$item->exists ? 'Edit Barang' : 'Tambah Barang'" subtitle="Inventaris fleksibel untuk barang apa pun">
    <x-slot:actions>
        <a href="{{ $item->exists ? route('inventory.show', $item) : route('inventory.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <form method="POST" enctype="multipart/form-data"
                  action="{{ $item->exists ? route('inventory.update', $item) : route('inventory.store') }}"
                  class="space-y-4">
                @csrf
                @if ($item->exists)
                    @method('PUT')
                @endif

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="label" for="name">Nama barang <span class="text-brick-600">*</span></label>
                        <input id="name" name="name" value="{{ old('name', $item->name) }}" class="input text-sm @error('name') border-brick-400 @enderror" required maxlength="120" placeholder="mis. RAM DDR4 8GB, Kabel HDMI, Laptop cadangan">
                        @error('name')<p class="mt-1 text-xs text-brick-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="label" for="code">Kode / SKU</label>
                        <input id="code" name="code" value="{{ old('code', $item->code) }}" class="input text-sm font-mono" maxlength="40">
                    </div>

                    <div>
                        <label class="label" for="category_id">Kategori</label>
                        <select id="category_id" name="category_id" class="input text-sm">
                            <option value="">Tanpa kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id) === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-ink-faint">Kategori dapat ditambah dari halaman Inventaris.</p>
                    </div>

                    <div>
                        <label class="label" for="unit">Satuan</label>
                        <input id="unit" name="unit" value="{{ old('unit', $item->unit ?? 'pcs') }}" class="input text-sm" maxlength="20" placeholder="pcs / unit / set / box">
                    </div>

                    <div>
                        <label class="label" for="min_quantity">Stok minimum</label>
                        <input id="min_quantity" type="number" min="0" max="1000000" name="min_quantity" value="{{ old('min_quantity', $item->min_quantity ?? 0) }}" class="input text-sm">
                    </div>

                    @if (! $item->exists)
                        <div>
                            <label class="label" for="initial_quantity">Stok awal</label>
                            <input id="initial_quantity" type="number" min="0" max="1000000" name="initial_quantity" value="{{ old('initial_quantity', 0) }}" class="input text-sm">
                            <p class="mt-1 text-[11px] text-ink-faint">Dicatat sebagai pergerakan “Masuk — stok awal”.</p>
                        </div>
                    @else
                        <div>
                            <label class="label">Stok saat ini</label>
                            <div class="input flex items-center justify-between bg-paper text-sm">
                                <span class="font-mono font-semibold">{{ $item->quantity }} {{ $item->unit }}</span>
                                <a href="{{ route('inventory.show', $item) }}" class="text-[11px] font-semibold text-navy-800 hover:underline">Mutasi stok →</a>
                            </div>
                            <p class="mt-1 text-[11px] text-ink-faint">Stok hanya berubah lewat mutasi, bukan dari form edit.</p>
                        </div>
                    @endif

                    <div>
                        <label class="label" for="location_id">Lokasi</label>
                        <select id="location_id" name="location_id" class="input text-sm">
                            <option value="">Belum ditentukan</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('location_id', $item->location_id) === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label" for="position_detail">Posisi detail</label>
                        <input id="position_detail" name="position_detail" value="{{ old('position_detail', $item->position_detail) }}" class="input text-sm" maxlength="120" placeholder="Rak A / Laci 2">
                    </div>

                    <div>
                        <label class="label" for="condition">Kondisi</label>
                        <select id="condition" name="condition" class="input text-sm">
                            <option value="new" @selected(old('condition', $item->condition) === 'new')>Baru</option>
                            <option value="good" @selected(old('condition', $item->condition) === 'good')>Baik</option>
                            <option value="damaged" @selected(old('condition', $item->condition) === 'damaged')>Rusak</option>
                            <option value="repair" @selected(old('condition', $item->condition) === 'repair')>Dalam perbaikan</option>
                        </select>
                    </div>

                    <div>
                        <label class="label" for="photo">Foto (opsional)</label>
                        <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="input text-xs">
                    </div>

                    <div class="md:col-span-2">
                        <label class="label" for="attributes_text">Atribut tambahan (bebas)</label>
                        <textarea id="attributes_text" name="attributes_text" rows="4" class="input text-xs font-mono" placeholder="Merk: Lenovo&#10;Serial: SN-12345&#10;Ukuran: 8 GB">{{ old('attributes_text', $attributesText) }}</textarea>
                        <p class="mt-1 text-[11px] text-ink-faint">Satu atribut per baris, format <code>Nama: Nilai</code>. Cocok untuk merek, serial, ukuran, warna, dsb.</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="label" for="notes">Catatan</label>
                        <textarea id="notes" name="notes" rows="3" class="input text-sm">{{ old('notes', $item->notes) }}</textarea>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->exists ? $item->is_active : true)) class="rounded border-line text-navy-700 focus:ring-navy-500">
                    Aktif
                </label>

                <div class="flex items-center gap-2 border-t border-line pt-4">
                    <button type="submit" class="btn-primary">{{ $item->exists ? 'Simpan Perubahan' : 'Tambah Barang' }}</button>
                    <a href="{{ $item->exists ? route('inventory.show', $item) : route('inventory.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </section>

        <aside class="space-y-4">
            <section class="card p-4">
                <h2 class="text-sm font-semibold text-ink">Panduan singkat</h2>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-[11px] leading-relaxed text-ink-soft">
                    <li>Gunakan kategori yang konsisten agar mudah dicari.</li>
                    <li>Stok awal dicatat otomatis sebagai mutasi masuk.</li>
                    <li>Setelah dibuat, stok hanya berubah melalui tombol <strong>Mutasi</strong>.</li>
                    <li>Barang yang sudah punya riwayat tidak dihapus, hanya diarsipkan.</li>
                </ul>
            </section>
        </aside>
    </div>
</x-dashboard-layout>
