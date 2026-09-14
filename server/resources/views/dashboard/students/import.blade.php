<x-dashboard-layout title="Impor Siswa">
    <x-slot:actions>
        <a href="{{ route('students.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold text-ink">Unggah Berkas</h2>
            <p class="mt-1 text-xs text-ink-faint">Format CSV, XLSX, atau XLS. Baris pertama adalah header: <code class="rounded bg-paper px-1">NISN</code>, <code class="rounded bg-paper px-1">NAMA</code>, <code class="rounded bg-paper px-1">KELAS</code>.</p>

            <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="label" for="file">Berkas data siswa</label>
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
                    <a href="{{ route('students.import.template') }}" class="btn-secondary">
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

                    @if (count($result['errors']) > 0)
                        <div class="mt-4 overflow-hidden rounded-md border border-brick-200">
                            <table class="min-w-full divide-y divide-brick-100 text-xs">
                                <thead class="bg-brick-50 text-left text-brick-800">
                                    <tr>
                                        <th class="px-3 py-2 font-semibold">Baris</th>
                                        <th class="px-3 py-2 font-semibold">NISN</th>
                                        <th class="px-3 py-2 font-semibold">Masalah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-brick-100 bg-white">
                                    @foreach ($result['errors'] as $error)
                                        <tr>
                                            <td class="px-3 py-1.5 font-mono text-ink-soft">{{ $error['row'] }}</td>
                                            <td class="px-3 py-1.5 font-mono text-ink-soft">{{ $error['nisn'] ?: '—' }}</td>
                                            <td class="px-3 py-1.5 text-brick-700">{{ $error['message'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <aside class="card h-fit p-5">
            <h3 class="text-sm font-semibold text-ink">Panduan</h3>
            <ol class="mt-3 space-y-2 text-xs text-ink-soft">
                <li class="flex gap-2"><span class="font-mono font-bold text-navy-800">1.</span> Unduh template CSV di samping.</li>
                <li class="flex gap-2"><span class="font-mono font-bold text-navy-800">2.</span> Buka dengan Excel/Google Sheets, isi data siswa (bisa dari ekspor Dapodik).</li>
                <li class="flex gap-2"><span class="font-mono font-bold text-navy-800">3.</span> Simpan sebagai CSV (UTF-8) atau XLSX.</li>
                <li class="flex gap-2"><span class="font-mono font-bold text-navy-800">4.</span> Unggah — NISN yang sudah ada akan <strong>diperbarui</strong>, bukan diduplikasi.</li>
            </ol>
            <div class="mt-4 rounded-md border border-gold-200 bg-gold-50 p-3 text-[11px] text-gold-800">
                <strong>Catatan:</strong> NISN wajib 10 angka. Baris bermasalah akan dilaporkan tanpa membatalkan baris lain.
            </div>
        </aside>
    </div>
</x-dashboard-layout>
