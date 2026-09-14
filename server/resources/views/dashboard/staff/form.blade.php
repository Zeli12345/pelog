<x-dashboard-layout :title="$member->exists ? 'Edit Guru/Pegawai' : 'Tambah Guru/Pegawai'">
    <x-slot:actions>
        <a href="{{ route('staff.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    <section class="card max-w-2xl p-5">
        <form method="POST" action="{{ $member->exists ? route('staff.update', $member) : route('staff.store') }}" class="space-y-4">
            @csrf
            @if ($member->exists)
                @method('PUT')
            @endif

            <div>
                <label class="label" for="nip_id">NIP / NUPTK <span class="text-brick-600">*</span></label>
                <input type="text" id="nip_id" name="nip_id" value="{{ old('nip_id', $member->nip_id) }}" required class="input font-mono @error('nip_id') border-brick-400 @enderror">
                @error('nip_id')
                    <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="name">Nama Lengkap &amp; Gelar <span class="text-brick-600">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $member->name) }}" required class="input @error('name') border-brick-400 @enderror">
                @error('name')
                    <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label" for="role">Peran <span class="text-brick-600">*</span></label>
                <select id="role" name="role" class="input">
                    <option value="teacher" @selected(old('role', $member->role?->value ?? 'teacher') === 'teacher')>Guru</option>
                    <option value="staff" @selected(old('role', $member->role?->value) === 'staff')>Pegawai</option>
                    <option value="admin" @selected(old('role', $member->role?->value) === 'admin')>Admin</option>
                </select>
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $member->exists ? $member->is_active : true)) class="rounded border-line text-navy-700 focus:ring-navy-500">
                Aktif (boleh login di kiosk)
            </label>

            <div class="flex items-center gap-2 border-t border-line pt-4">
                <button type="submit" class="btn-primary">{{ $member->exists ? 'Simpan Perubahan' : 'Tambah' }}</button>
                <a href="{{ route('staff.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </section>
</x-dashboard-layout>
