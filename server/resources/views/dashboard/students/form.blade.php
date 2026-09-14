<x-dashboard-layout :title="$student->exists ? 'Edit Siswa' : 'Tambah Siswa'">
    <x-slot:actions>
        <a href="{{ route('students.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    <section class="card max-w-2xl p-5">
        <form method="POST" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}" class="space-y-4">
            @csrf
            @if ($student->exists)
                @method('PUT')
            @endif

            <div>
                <label class="label" for="nisn">NISN <span class="text-brick-600">*</span></label>
                <input type="text" id="nisn" name="nisn" value="{{ old('nisn', $student->nisn) }}" inputmode="numeric" maxlength="10" required
                       class="input font-mono @error('nisn') border-brick-400 @enderror">
                @error('nisn')
                    <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                @else
                    <p class="mt-1 text-xs text-ink-faint">10 angka sesuai data sekolah/Dapodik.</p>
                @enderror
            </div>

            <div>
                <label class="label" for="name">Nama Lengkap <span class="text-brick-600">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $student->name) }}" required class="input @error('name') border-brick-400 @enderror">
                @error('name')
                    <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="class">Kelas <span class="text-brick-600">*</span></label>
                <input type="text" id="class" name="class" value="{{ old('class', $student->class) }}" required placeholder="contoh: X RPL 1" class="input @error('class') border-brick-400 @enderror">
                @error('class')
                    <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $student->exists ? $student->is_active : true)) class="rounded border-line text-navy-700 focus:ring-navy-500">
                Aktif (boleh login di kiosk)
            </label>

            <div class="flex items-center gap-2 border-t border-line pt-4">
                <button type="submit" class="btn-primary">{{ $student->exists ? 'Simpan Perubahan' : 'Tambah Siswa' }}</button>
                <a href="{{ route('students.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </section>
</x-dashboard-layout>
