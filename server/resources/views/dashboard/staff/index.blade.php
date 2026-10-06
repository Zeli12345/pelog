<x-dashboard-layout title="Guru & Pegawai" subtitle="Master data guru & pegawai">
    @php $isAdmin = auth()->user()->isAdmin(); @endphp

    <x-slot:actions>
        <div class="flex items-center gap-2">
            @if ($isAdmin)
            <a href="{{ route('staff.import.form') }}" class="btn-secondary !py-1.5 text-xs">
                <x-icon name="upload" size="h-3.5 w-3.5" />
                Impor
            </a>
            <a href="{{ route('staff.create') }}" class="btn-primary !py-1.5 text-xs">
                <x-icon name="plus" size="h-3.5 w-3.5" />
                Tambah
            </a>
            @endif
        </div>
    </x-slot:actions>

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <x-stat-card label="Total" :value="$stats['total']" icon="user-check" tone="navy" />
            <x-stat-card label="Guru" :value="$stats['teachers']" icon="users" tone="navy" />
            <x-stat-card label="Nonaktif" :value="$stats['inactive']" icon="x" tone="brick" />
        </div>

        <section class="card overflow-hidden">
            @if ($isAdmin)
            <form id="staff-bulk-delete-form" method="POST" action="{{ route('staff.bulk-delete') }}" data-confirm="Hapus guru/pegawai terpilih? Riwayat sesi mereka tetap tersimpan.">
                @csrf
            </form>
            <form id="staff-bulk-restore-form" method="POST" action="{{ route('staff.bulk-restore') }}" data-confirm="Pulihkan guru/pegawai terpilih?">
                @csrf
            </form>
            @endif

            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <form method="GET" action="{{ route('staff.index') }}" class="flex items-center gap-2">
                    @if ($trashed)
                        <input type="hidden" name="trashed" value="1">
                    @endif
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / NIP…" class="input !w-52 py-1.5 text-xs">
                    <button type="submit" class="btn-primary !py-1.5 text-xs">Cari</button>
                </form>
                <span class="font-mono text-xs text-ink-faint">{{ $staff->total() }} orang</span>
            </header>

            @if ($isAdmin)
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line bg-navy-50/40 px-4 py-2 text-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-1.5 text-ink-soft">
                        <input type="checkbox" data-bulk-select-all class="rounded border-line text-navy-700 focus:ring-navy-500">
                        Pilih semua
                    </label>
                    <span class="text-ink-faint"><span data-bulk-count>0</span> dipilih</span>
                    @if ($trashed)
                        <span class="badge-danger">Tampilan terhapus</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if ($trashed)
                        <button type="submit" form="staff-bulk-restore-form" data-bulk-submit disabled class="btn-secondary !py-1.5 text-xs">Pulihkan terpilih</button>
                        <a href="{{ route('staff.index', request()->except('trashed')) }}" class="btn-secondary !py-1.5 text-xs">
                            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
                            Kembali ke daftar aktif
                        </a>
                    @else
                        <button type="submit" form="staff-bulk-delete-form" data-bulk-submit disabled class="btn-secondary !py-1.5 text-xs text-brick-600">Hapus terpilih</button>
                        <a href="{{ route('staff.index', array_merge(request()->query(), ['trashed' => 1])) }}" class="btn-secondary !py-1.5 text-xs">
                            <x-icon name="trash" size="h-3.5 w-3.5" />
                            Tampilkan yang terhapus
                        </a>
                    @endif
                </div>
            </div>
            @endif

            @if ($staff->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-ink-soft">{{ $trashed ? 'Tidak ada guru/pegawai terhapus.' : 'Belum ada data guru/pegawai.' }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                @if ($isAdmin)
                                <th class="w-8 px-4 py-2.5"></th>
                                @endif
                                <th class="px-4 py-2.5 font-semibold">NIP / NUPTK</th>
                                <th class="px-4 py-2.5 font-semibold">Nama</th>
                                <th class="px-4 py-2.5 font-semibold">Peran</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                @if ($isAdmin)
                                <th class="px-4 py-2.5 text-right font-semibold">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($staff as $member)
                                <tr class="table-row {{ ! $member->is_active ? 'opacity-60' : '' }}">
                                    @if ($isAdmin)
                                    <td class="px-4 py-2.5">
                                        <input type="checkbox" name="ids[]" value="{{ $member->id }}"
                                            form="{{ $trashed ? 'staff-bulk-restore-form' : 'staff-bulk-delete-form' }}"
                                            data-bulk-checkbox class="rounded border-line text-navy-700 focus:ring-navy-500">
                                    </td>
                                    @endif
                                    <td class="px-4 py-2.5 font-mono text-xs text-ink-soft">{{ $member->nip_id }}</td>
                                    <td class="px-4 py-2.5 font-medium text-ink">{{ $member->name }}</td>
                                    <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $member->role->label() }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($trashed)
                                            <span class="badge-danger">Terhapus</span>
                                        @elseif ($member->is_active)
                                            <span class="badge-muted">Aktif</span>
                                        @else
                                            <span class="badge-danger">Nonaktif</span>
                                        @endif
                                    </td>
                                    @if ($isAdmin)
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if ($trashed)
                                                <form method="POST" action="{{ route('staff.restore', $member) }}" data-confirm="Pulihkan {{ $member->name }}?">
                                                    @csrf
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs text-moss-700" title="Pulihkan">
                                                        <x-icon name="refresh" size="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                            @else
                                                <a href="{{ route('staff.edit', $member) }}" class="btn-secondary !px-2 !py-1 text-xs" title="Edit">
                                                    <x-icon name="pencil" size="h-3.5 w-3.5" />
                                                </a>
                                                <form method="POST" action="{{ route('staff.destroy', $member) }}" data-confirm="Hapus {{ $member->name }}? Data sesi historis tetap tersimpan.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs text-brick-600" title="Hapus">
                                                        <x-icon name="trash" size="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-line px-4 py-3">{{ $staff->links() }}</div>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-confirm]');

            if (form && ! confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });

        (() => {
            const boxes = () => Array.from(document.querySelectorAll('[data-bulk-checkbox]'));

            const updateBulkState = () => {
                const count = boxes().filter((box) => box.checked).length;

                document.querySelectorAll('[data-bulk-count]').forEach((el) => { el.textContent = count; });
                document.querySelectorAll('[data-bulk-submit]').forEach((el) => { el.disabled = count === 0; });
            };

            document.addEventListener('change', (event) => {
                if (event.target.matches('[data-bulk-select-all]')) {
                    boxes().forEach((box) => { box.checked = event.target.checked; });
                }

                if (event.target.matches('[data-bulk-select-all], [data-bulk-checkbox]')) {
                    updateBulkState();
                }
            });

            updateBulkState();
        })();
    </script>
</x-dashboard-layout>
