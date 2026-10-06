<x-dashboard-layout title="Pengguna Dashboard" subtitle="Kelola akun yang bisa masuk ke dashboard PELOG">
    <x-slot:actions>
        <a href="{{ route('users.create') }}" class="btn-primary !py-1.5 text-xs">
            <x-icon name="plus" size="h-3.5 w-3.5" />
            Tambah Pengguna
        </a>
    </x-slot:actions>

    <div class="space-y-4">
        <div class="grid grid-cols-3 gap-3">
            <x-stat-card label="Total Pengguna" :value="$stats['total']" icon="users" tone="navy" />
            <x-stat-card label="Admin" :value="$stats['admins']" icon="shield-check" tone="gold" />
            <x-stat-card label="Viewer" :value="$stats['viewers']" icon="eye" tone="moss" />
        </div>

        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / email…" class="input !w-44 py-1.5 text-xs lg:!w-56">
                    <select name="role" class="input !w-36 py-1.5 text-xs" onchange="this.form.submit()">
                        <option value="">Semua peran</option>
                        <option value="admin" @selected($roleFilter === 'admin')>Admin</option>
                        <option value="viewer" @selected($roleFilter === 'viewer')>Viewer</option>
                    </select>
                    <select name="status" class="input !w-36 py-1.5 text-xs" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                        <option value="inactive" @selected($statusFilter === 'inactive')>Nonaktif</option>
                    </select>
                    <button type="submit" class="btn-primary !py-1.5 text-xs">Cari</button>
                    @if ($search !== '' || $roleFilter !== '' || $statusFilter !== '')
                        <a href="{{ route('users.index') }}" class="btn-secondary !py-1.5 text-xs">Reset</a>
                    @endif
                </form>
                <span class="font-mono text-xs text-ink-faint">{{ $users->total() }} pengguna</span>
            </header>

            @if ($users->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-ink-soft">Tidak ada pengguna yang cocok.</p>
                    <p class="mt-1 text-xs text-ink-faint">Tambahkan akun baru lewat tombol “Tambah Pengguna”.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-4 py-2.5 font-semibold">Pengguna</th>
                                <th class="px-4 py-2.5 font-semibold">Peran</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                <th class="px-4 py-2.5 font-semibold">Dibuat</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($users as $item)
                                @php
                                    $isSelf = $item->is(auth()->user());
                                    $canUpdate = auth()->user()->can('update', $item);
                                    $canToggle = auth()->user()->can('toggleActive', $item);
                                    $canDelete = auth()->user()->can('delete', $item);
                                @endphp
                                <tr class="table-row {{ ! $item->is_active ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-navy-50 text-[11px] font-bold uppercase text-navy-700">
                                                {{ \Illuminate\Support\Str::of($item->name)->substr(0, 2) }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-ink">
                                                    {{ $item->name }}
                                                    @if ($isSelf)
                                                        <span class="badge-muted ml-1">Anda</span>
                                                    @endif
                                                </p>
                                                <p class="truncate text-xs text-ink-faint">{{ $item->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5"><x-role-badge :role="$item->role" /></td>
                                    <td class="px-4 py-2.5">
                                        @if ($item->is_active)
                                            <span class="badge-muted">Aktif</span>
                                        @else
                                            <span class="badge-danger">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $item->created_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if ($canUpdate)
                                                <a href="{{ route('users.edit', $item) }}" class="btn-secondary !px-2 !py-1 text-xs" title="Ubah">
                                                    <x-icon name="pencil" size="h-3.5 w-3.5" />
                                                </a>
                                            @endif
                                            @if ($canToggle)
                                                <form method="POST" action="{{ route('users.toggle', $item) }}" data-confirm="{{ $item->is_active ? 'Nonaktifkan akun '.$item->name.'? Akun tidak bisa login sampai diaktifkan kembali.' : 'Aktifkan kembali akun '.$item->name.'?' }}">
                                                    @csrf
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs" title="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                        <x-icon :name="$item->is_active ? 'x' : 'check'" size="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($canDelete)
                                                <form method="POST" action="{{ route('users.destroy', $item) }}" data-confirm="Hapus akun {{ $item->name }}? Akun langsung tidak bisa login.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs text-brick-600" title="Hapus">
                                                        <x-icon name="trash" size="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($item->isAdminUtama())
                                                <span class="inline-flex items-center gap-1 text-[11px] text-ink-faint" title="Akun utama sistem — dikelola lewat halaman Profil">
                                                    <x-icon name="lock" size="h-3.5 w-3.5" />
                                                    Akun utama
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-line px-4 py-3">{{ $users->links() }}</div>
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
    </script>
</x-dashboard-layout>
