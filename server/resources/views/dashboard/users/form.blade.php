<x-dashboard-layout :title="$user->exists ? 'Edit Pengguna' : 'Tambah Pengguna'">
    <x-slot:actions>
        <a href="{{ route('users.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    <section class="card max-w-2xl p-5">
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="space-y-5">
            @csrf
            @if ($user->exists)
                @method('PUT')
            @endif

            <div class="space-y-4">
                <h2 class="text-sm font-semibold text-ink">Informasi Akun</h2>

                <div>
                    <label class="label" for="name">Nama Lengkap <span class="text-brick-600">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="input @error('name') border-brick-400 @enderror">
                    @error('name')
                        <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label" for="email">Email <span class="text-brick-600">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="input @error('email') border-brick-400 @enderror">
                    @error('email')
                        <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="space-y-3 border-t border-line pt-5">
                <h2 class="text-sm font-semibold text-ink">Peran &amp; Akses</h2>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($roles as $role)
                        @php $checked = old('role', $user->role?->value) === $role->value; @endphp
                        <label class="relative cursor-pointer">
                            <input type="radio" name="role" value="{{ $role->value }}" @checked($checked) class="peer sr-only">
                            <span class="block rounded-xl border border-line bg-white p-4 transition hover:border-navy-300 peer-checked:border-navy-500 peer-checked:ring-1 peer-checked:ring-navy-500 peer-focus-visible:ring-2 peer-focus-visible:ring-gold-400">
                                <span class="block text-sm font-semibold text-ink">{{ $role->label() }}</span>
                                <span class="mt-1 block text-xs leading-relaxed text-ink-soft">
                                    @if ($role->value === 'sub_admin')
                                        Kelola siswa, guru &amp; pegawai, mapel, perangkat, sesi, laporan, dan pengaturan.
                                    @else
                                        Hanya melihat data, perangkat, dan laporan (baca-saja) + ekspor.
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('role')
                    <p class="text-xs text-brick-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-4 border-t border-line pt-5" x-data="{ show: false }">
                <h2 class="text-sm font-semibold text-ink">Keamanan</h2>

                <div>
                    <label class="label" for="password">
                        Kata Sandi @if (! $user->exists) <span class="text-brick-600">*</span> @endif
                    </label>
                    <input :type="show ? 'text' : 'password'" id="password" name="password" @if (! $user->exists) required @endif autocomplete="new-password"
                           class="input @error('password') border-brick-400 @enderror">
                    @if ($user->exists)
                        <p class="mt-1 text-xs text-ink-faint">Biarkan kosong bila tidak ingin mengganti kata sandi.</p>
                    @endif
                    @error('password')
                        <p class="mt-1 text-xs text-brick-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label" for="password_confirmation">Konfirmasi Kata Sandi</label>
                    <input :type="show ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" @if (! $user->exists) required @endif autocomplete="new-password"
                           class="input">
                </div>

                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" x-on:change="show = $event.target.checked" class="rounded border-line text-navy-700 focus:ring-navy-500">
                    Tampilkan kata sandi
                </label>
                <p class="text-xs text-ink-faint">Minimal 8 karakter.</p>
            </div>

            <div class="flex items-center gap-2 border-t border-line pt-4">
                <button type="submit" class="btn-primary">{{ $user->exists ? 'Simpan Perubahan' : 'Tambah Pengguna' }}</button>
                <a href="{{ route('users.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </section>
</x-dashboard-layout>
