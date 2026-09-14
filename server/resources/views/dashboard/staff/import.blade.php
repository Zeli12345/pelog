<x-dashboard-layout title="Impor Guru & Pegawai">
    <x-slot:actions>
        <a href="{{ route('staff.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold text-ink">Unggah Berkas</h2>
            <p class="mt-1 text-xs text-ink-faint">Header: <code class="rounded bg-paper px-1">NIP</code>, <code class="rounded bg-paper px-1">NAMA</code>, <code class="rounded bg-paper px-1">PERAN</code> (teacher/staff/admin — opsional, default teacher).</p>

            <form method="POST" action="{{ route('staff.import') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="label" for="file">Berkas data guru/pegawai</label>
                    <input type="file" id="file" name="file" accept=".csv,.txt,.xlsx,.xls" required
                           class="block w-full cursor-pointer rounded-md border border-line bg-white text-sm text-ink-soft file:mr-3 file:border-0 file:bg-navy-900 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-800">
                    @error('file')
                        <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn-primary">
                        <x-icon name="upload" size="h-4 w-4" />
                        Proses Impor
                    </button>
                    <a href="{{ route('staff.import.template') }}" class="btn-secondary">
                        <x-icon name="download" size="h-4 w-4" />
                        Unduh Template CSV
                    </a>
                </div>
            </form>

            @if ($result)
                <div class="mt-6 border-t border-line pt-5">
                    <h3 class="text-sm font-semibold text-ink">Hasil Impor</h3>
                    <div class="mt-3 grid grid-cols-3 gap-3">
                        <div class="rounded-md border border-moss-200 bg-moss-50 px-3 py-2 text-center">
                            <p class="font-mono text-lg font-semibold text-moss-700">{{ $result['created'] }}</p>
                            <p class="text-[11px] text-moss-700">Dibuat</p>
                        </div>
                        <div class="rounded-md border border-navy-200 bg-navy-50 px-3 py-2 text-center">
                            <p class="font-mono text-lg font-semibold text-navy-800">{{ $result['updated'] }}</p>
                            <p class="text-[11px] text-navy-800">Diperbarui</p>
                        </div>
                        <div class="rounded-md border border-brick-200 bg-brick-50 px-3 py-2 text-center">
                            <p class="font-mono text-lg font-semibold text-brick-700">{{ count($result['errors']) }}</p>
                            <p class="text-[11px] text-brick-700">Gagal</p>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <aside class="card h-fit p-5">
            <h3 class="text-sm font-semibold text-ink">Tips</h3>
            <p class="mt-2 text-xs text-ink-soft">Gunakan data kepegawaian resmi sekolah. NIP yang sudah ada akan diperbarui, bukan diduplikasi.</p>
        </aside>
    </div>
</x-dashboard-layout>
