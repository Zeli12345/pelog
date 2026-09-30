<x-dashboard-layout title="Mata Pelajaran" subtitle="Daftar mata pelajaran aktif">
    <div class="space-y-4">
        @if ($editing)
            <section class="card border-navy-200 p-5">
                <h2 class="text-sm font-semibold text-ink">Edit Mata Pelajaran</h2>
                <form method="POST" action="{{ route('subjects.update', $editing) }}" class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="label" for="code">Kode</label>
                        <input type="text" id="code" name="code" value="{{ old('code', $editing->code) }}" required class="input font-mono text-sm">
                    </div>
                    <div class="lg:col-span-2">
                        <label class="label" for="name">Nama Mata Pelajaran</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $editing->name) }}" required class="input text-sm">
                    </div>
                    <div class="flex items-end gap-3">
                        <label class="flex items-center gap-2 pb-2 text-sm text-ink-soft">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active)) class="rounded border-line text-navy-700 focus:ring-navy-500">
                            Aktif
                        </label>
                        <div class="flex gap-2 pb-1">
                            <button type="submit" class="btn-primary !py-2 text-xs">Simpan</button>
                            <a href="{{ route('subjects.index') }}" class="btn-secondary !py-2 text-xs">Batal</a>
                        </div>
                    </div>
                </form>
            </section>
        @endif

        <section class="card p-5">
            <h2 class="text-sm font-semibold text-ink">Tambah Mata Pelajaran</h2>
            <form method="POST" action="{{ route('subjects.store') }}" class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-4">
                @csrf
                <div>
                    <label class="label" for="new_code">Kode</label>
                    <input type="text" id="new_code" name="code" value="{{ old('code') }}" required placeholder="PWPB" class="input font-mono text-sm @error('code') border-brick-400 @enderror">
                    @error('code')
                        <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="lg:col-span-2">
                    <label class="label" for="new_name">Nama Mata Pelajaran</label>
                    <input type="text" id="new_name" name="name" value="{{ old('name') }}" required placeholder="Pemrograman Web & Perangkat Bergerak" class="input text-sm @error('name') border-brick-400 @enderror">
                    @error('name')
                        <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn-primary !py-2 text-xs">
                        <x-icon name="plus" size="h-3.5 w-3.5" />
                        Tambah
                    </button>
                </div>
            </form>
        </section>

        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold text-ink">Daftar Mata Pelajaran</h2>
                <form method="GET" action="{{ route('subjects.index') }}" class="flex items-center gap-2">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari…" class="input !w-40 py-1.5 text-xs">
                    <button type="submit" class="btn-secondary !py-1.5 text-xs">Cari</button>
                </form>
            </header>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="px-4 py-2.5 font-semibold">Kode</th>
                            <th class="px-4 py-2.5 font-semibold">Nama</th>
                            <th class="px-4 py-2.5 font-semibold">Dipakai</th>
                            <th class="px-4 py-2.5 font-semibold">Status</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line/70">
                        @forelse ($subjects as $subject)
                            <tr class="table-row {{ ! $subject->is_active ? 'opacity-60' : '' }}">
                                <td class="px-4 py-2.5 font-mono text-xs font-semibold text-navy-800">{{ $subject->code }}</td>
                                <td class="px-4 py-2.5 text-ink">{{ $subject->name }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-ink-faint">{{ $subject->sessions_count }} sesi</td>
                                <td class="px-4 py-2.5">
                                    @if ($subject->is_active)
                                        <span class="badge-muted">Aktif</span>
                                    @else
                                        <span class="badge-danger">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('subjects.index', ['edit' => $subject->id]) }}" class="btn-secondary !px-2 !py-1 text-xs" title="Edit">
                                            <x-icon name="pencil" size="h-3.5 w-3.5" />
                                        </a>
                                        <form method="POST" action="{{ route('subjects.destroy', $subject) }}" data-confirm="Hapus/nonaktifkan {{ $subject->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-secondary !px-2 !py-1 text-xs text-brick-600" title="Hapus">
                                                <x-icon name="trash" size="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-sm text-ink-faint">Belum ada mata pelajaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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
