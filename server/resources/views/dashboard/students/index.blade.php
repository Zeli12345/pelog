<x-dashboard-layout title="Siswa" subtitle="Master data siswa & status PIN">
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('students.import.form') }}" class="btn-secondary !py-1.5 text-xs">
                <x-icon name="upload" size="h-3.5 w-3.5" />
                Impor
            </a>
            <a href="{{ route('students.create') }}" class="btn-primary !py-1.5 text-xs">
                <x-icon name="plus" size="h-3.5 w-3.5" />
                Tambah Siswa
            </a>
        </div>
    </x-slot:actions>

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card label="Total Siswa" :value="$stats['total']" icon="users" tone="navy" />
            <x-stat-card label="Sudah Punya PIN" :value="$stats['with_pin']" icon="key" tone="moss" />
            <x-stat-card label="Belum Set PIN" :value="$stats['without_pin']" icon="alert-triangle" tone="gold" />
            <x-stat-card label="Nonaktif" :value="$stats['inactive']" icon="x" tone="brick" />
        </div>

        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <form method="GET" action="{{ route('students.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / NISN…" class="input !w-44 py-1.5 text-xs lg:!w-56">
                    <select name="class" class="input !w-36 py-1.5 text-xs" onchange="this.form.submit()">
                        <option value="">Semua kelas</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class }}" @selected($classFilter === $class)>{{ $class }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-primary !py-1.5 text-xs">Cari</button>
                    @if ($search !== '' || $classFilter !== '')
                        <a href="{{ route('students.index') }}" class="btn-secondary !py-1.5 text-xs">Reset</a>
                    @endif
                </form>
                <span class="font-mono text-xs text-ink-faint">{{ $students->total() }} siswa</span>
            </header>

            @if ($students->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-ink-soft">Tidak ada siswa yang cocok.</p>
                    <p class="mt-1 text-xs text-ink-faint">Tambahkan manual atau impor dari Excel/CSV.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-4 py-2.5 font-semibold">NISN</th>
                                <th class="px-4 py-2.5 font-semibold">Nama</th>
                                <th class="px-4 py-2.5 font-semibold">Kelas</th>
                                <th class="px-4 py-2.5 font-semibold">PIN</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($students as $student)
                                <tr class="table-row {{ ! $student->is_active ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-2.5 font-mono text-xs text-ink-soft">{{ $student->nisn }}</td>
                                    <td class="px-4 py-2.5 font-medium text-ink">{{ $student->name }}</td>
                                    <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $student->class }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($student->hasPin())
                                            <span class="badge-ok"><x-icon name="key" size="h-3 w-3" /> Ada</span>
                                        @else
                                            <span class="badge-warn">Belum</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($student->is_active)
                                            <span class="badge-muted">Aktif</span>
                                        @else
                                            <span class="badge-danger">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('students.edit', $student) }}" class="btn-secondary !px-2 !py-1 text-xs" title="Edit">
                                                <x-icon name="pencil" size="h-3.5 w-3.5" />
                                            </a>
                                            @if ($student->hasPin())
                                                <form method="POST" action="{{ route('students.reset-pin', $student) }}" onsubmit="return confirm('Reset PIN {{ $student->name }}? Siswa akan diminta membuat PIN baru.')">
                                                    @csrf
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs" title="Reset PIN">
                                                        <x-icon name="refresh" size="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Hapus {{ $student->name }}? Data sesi historis tetap tersimpan.')">
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

                <div class="border-t border-line px-4 py-3">{{ $students->links() }}</div>
            @endif
        </section>
    </div>
</x-dashboard-layout>
