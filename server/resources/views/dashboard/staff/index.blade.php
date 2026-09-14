<x-dashboard-layout title="Guru & Pegawai">
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('staff.import.form') }}" class="btn-secondary !py-1.5 text-xs">
                <x-icon name="upload" size="h-3.5 w-3.5" />
                Impor
            </a>
            <a href="{{ route('staff.create') }}" class="btn-primary !py-1.5 text-xs">
                <x-icon name="plus" size="h-3.5 w-3.5" />
                Tambah
            </a>
        </div>
    </x-slot:actions>

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <x-stat-card label="Total" :value="$stats['total']" icon="user-check" tone="navy" />
            <x-stat-card label="Guru" :value="$stats['teachers']" icon="users" tone="navy" />
            <x-stat-card label="Nonaktif" :value="$stats['inactive']" icon="x" tone="brick" />
        </div>

        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <form method="GET" action="{{ route('staff.index') }}" class="flex items-center gap-2">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / NIP…" class="input !w-52 py-1.5 text-xs">
                    <button type="submit" class="btn-primary !py-1.5 text-xs">Cari</button>
                </form>
                <span class="font-mono text-xs text-ink-faint">{{ $staff->total() }} orang</span>
            </header>

            @if ($staff->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-ink-soft">Belum ada data guru/pegawai.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="bg-paper/70 text-left text-[11px] uppercase tracking-wider text-ink-faint">
                                <th class="px-4 py-2.5 font-semibold">NIP / NUPTK</th>
                                <th class="px-4 py-2.5 font-semibold">Nama</th>
                                <th class="px-4 py-2.5 font-semibold">Peran</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($staff as $member)
                                <tr class="hover:bg-paper/60 {{ ! $member->is_active ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-2.5 font-mono text-xs text-ink-soft">{{ $member->nip_id }}</td>
                                    <td class="px-4 py-2.5 font-medium text-ink">{{ $member->name }}</td>
                                    <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $member->role->label() }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($member->is_active)
                                            <span class="badge-muted">Aktif</span>
                                        @else
                                            <span class="badge-danger">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('staff.edit', $member) }}" class="btn-secondary !px-2 !py-1 text-xs" title="Edit">
                                                <x-icon name="pencil" size="h-3.5 w-3.5" />
                                            </a>
                                            <form method="POST" action="{{ route('staff.destroy', $member) }}" onsubmit="return confirm('Hapus {{ $member->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-secondary !px-2 !py-1 text-xs text-brick-600" title="Hapus">
                                                    <x-icon name="trash" size="h-3.5 w-3.5" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-line px-4 py-3">{{ $staff->links() }}</div>
            @endif
        </section>
    </div>
</x-dashboard-layout>
