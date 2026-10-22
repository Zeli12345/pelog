<x-dashboard-layout title="Lokasi & Denah" subtitle="Ruangan, perangkat, dan tata letak">
    @php $isAdmin = auth()->user()->isAdmin(); @endphp

    <x-slot:actions>
        @if ($isAdmin)
            <button type="button" class="btn-primary !py-1.5 text-xs" x-data x-on:click="$dispatch('open-add-location')">
                <x-icon name="plus" size="h-3.5 w-3.5" />
                Tambah Ruangan
            </button>
        @endif
    </x-slot:actions>

    <div class="space-y-4" x-data="{ addOpen: false, manage: null }" x-on:open-add-location.window="addOpen = true">
        <div class="rounded-lg border border-line bg-white/60 px-4 py-3 text-xs text-ink-soft">
            Pilih ruangan untuk melihat tata letak perangkat. Admin dapat mengatur posisi, menambah meja, dan menyimpan denah.
        </div>

        @if ($locations->isEmpty())
            <section class="card px-4 py-16 text-center">
                <p class="text-sm font-medium text-ink-soft">Belum ada ruangan.</p>
                <p class="mt-1 text-xs text-ink-faint">Tambahkan ruangan seperti “Lab RPL 1” atau “Ruang Guru”.</p>
            </section>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($locations as $location)
                    <article class="card overflow-hidden">
                        <div class="border-b border-line px-4 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h2 class="truncate text-sm font-semibold text-ink">{{ $location->name }}</h2>
                                    <p class="mt-0.5 text-[11px] text-ink-faint">
                                        {{ $location->devices_count }} perangkat · {{ $location->active_devices_count }} aktif
                                        · {{ $location->inventory_items_count }} barang
                                    </p>
                                </div>
                                @if (! $location->is_active)
                                    <span class="badge-muted">Arsip</span>
                                @endif
                            </div>
                        </div>

                        <a href="{{ route('locations.show', $location) }}" class="block bg-paper p-3">
                            <div class="relative aspect-[5/3] overflow-hidden rounded-md border border-line bg-white"
                                 style="background-image: linear-gradient(to right, #ece7dc 1px, transparent 1px), linear-gradient(to bottom, #ece7dc 1px, transparent 1px); background-size: 8% 8%;">
                                @foreach ($location->layoutObjects->take(60) as $object)
                                    @php
                                        $style = 'left:'.($object->x).'%;top:'.($object->y).'%;width:'.($object->w).'%;height:'.($object->h).'%;';
                                        $isCircle = $object->type === 'table_circle';
                                        $isDevice = $object->type === 'device';
                                    @endphp
                                    <span class="absolute border text-[6px] {{ $isCircle ? 'rounded-full' : 'rounded-[2px]' }}
                                        {{ $isDevice ? 'border-navy-700 bg-navy-700/80' : 'border-gold-500 bg-gold-200/80' }}"
                                          style="{{ $style }}">
                                    </span>
                                @endforeach
                            </div>
                        </a>

                        <div class="flex items-center justify-between gap-2 border-t border-line px-4 py-2.5">
                            <a href="{{ route('locations.show', $location) }}" class="text-xs font-semibold text-navy-800 hover:underline">
                                Buka Denah →
                            </a>
                            @if ($isAdmin)
                                <button type="button"
                                        class="btn-secondary !px-2 !py-1 text-[11px]"
                                        x-on:click="manage = {{ \Illuminate\Support\Js::from([
                                            'id' => $location->id,
                                            'name' => $location->name,
                                            'is_active' => $location->is_active,
                                            'sort_order' => $location->sort_order,
                                            'devices' => $location->devices_count,
                                            'items' => $location->inventory_items_count,
                                            'objects' => $location->layout_objects_count,
                                        ]) }}">
                                    Kelola
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        {{-- Modal tambah --}}
        @if ($isAdmin)
            <div x-show="addOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4" x-on:keydown.escape.window="addOpen = false">
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl" x-on:click.outside="addOpen = false">
                    <h2 class="text-sm font-semibold text-ink">Tambah Ruangan</h2>
                    <form method="POST" action="{{ route('locations.store') }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="label" for="new-location-name">Nama ruangan</label>
                            <input id="new-location-name" name="name" class="input text-sm" placeholder="mis. Lab RPL 2" required maxlength="80">
                            <p class="mt-1 text-[11px] text-ink-faint">Gunakan nama yang konsisten. “rpl” dan “RPL” dianggap sama.</p>
                        </div>
                        <div>
                            <label class="label" for="new-location-sort">Urutan</label>
                            <input id="new-location-sort" name="sort_order" type="number" min="0" max="9999" value="0" class="input text-sm">
                        </div>
                        <div class="flex justify-end gap-2 border-t border-line pt-3">
                            <button type="button" class="btn-secondary" x-on:click="addOpen = false">Batal</button>
                            <button type="submit" class="btn-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Modal kelola --}}
            <div x-show="manage !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4">
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-5 shadow-2xl" x-on:click.outside="manage = null">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-ink">Kelola Ruangan</h2>
                            <p class="mt-0.5 text-xs text-ink-faint" x-text="manage?.name"></p>
                        </div>
                        <button type="button" class="btn-secondary !p-2" x-on:click="manage = null"><x-icon name="x" size="h-4 w-4" /></button>
                    </div>

                    <template x-if="manage">
                        <div class="mt-4 space-y-5">
                            <form method="POST" :action="'/locations/' + manage.id" class="space-y-3">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="label">Nama ruangan</label>
                                    <input name="name" class="input text-sm" :value="manage.name" required maxlength="80">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="label">Urutan</label>
                                        <input name="sort_order" type="number" min="0" max="9999" class="input text-sm" :value="manage.sort_order">
                                    </div>
                                    <label class="flex items-end gap-2 pb-2 text-sm text-ink-soft">
                                        <input type="checkbox" name="is_active" value="1" :checked="manage.is_active" class="rounded border-line text-navy-700">
                                        Aktif
                                    </label>
                                </div>
                                <button type="submit" class="btn-primary !py-1.5 text-xs">Simpan Perubahan</button>
                            </form>

                            <form method="POST" :action="'/locations/' + manage.id + '/merge'" class="space-y-3 border-t border-line pt-4">
                                @csrf
                                <div>
                                    <label class="label">Gabungkan ke ruangan lain</label>
                                    <select name="target_id" class="input text-sm" required>
                                        <option value="">Pilih ruangan tujuan…</option>
                                        @foreach ($locations as $target)
                                            <option value="{{ $target->id }}">{{ $target->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-[11px] text-ink-faint">Perangkat dan barang dipindahkan; denah sumber dikosongkan.</p>
                                </div>
                                <button type="submit" class="btn-secondary !py-1.5 text-xs" onclick="return confirm('Gabungkan ruangan ini? Tindakan ini memindahkan semua perangkat dan barang.')">Gabungkan</button>
                            </form>

                            <form method="POST" :action="'/locations/' + manage.id" class="space-y-3 border-t border-line pt-4" onsubmit="return confirm('Hapus ruangan ini? Hanya bisa jika benar-benar kosong.')">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <label class="label">Hapus ruangan (harus kosong)</label>
                                    <input name="confirm_name" class="input text-sm" placeholder="Ketik nama ruangan persis" required>
                                    <p class="mt-1 text-[11px] text-ink-faint">
                                        Perangkat: <span x-text="manage?.devices"></span> ·
                                        Barang: <span x-text="manage?.items"></span> ·
                                        Objek denah: <span x-text="manage?.objects"></span>
                                    </p>
                                </div>
                                <button type="submit" class="btn-danger !py-1.5 text-xs">Hapus Permanen</button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        @endif
    </div>
</x-dashboard-layout>
