<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink">Masuk Dashboard</h1>
        <p class="mt-1 text-sm text-ink-faint">Gunakan akun yang diberikan oleh Admin IT sekolah.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-ink-soft">
                <input id="remember_me" type="checkbox" class="rounded border-line text-navy-800 shadow-sm focus:ring-navy-500" name="remember">
                {{ __('Remember me') }}
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-navy-800 hover:underline" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full justify-center">
            <x-icon name="lock" size="h-4 w-4" />
            {{ __('Log in') }}
        </x-primary-button>
    </form>

    <p class="mt-6 border-t border-line pt-4 text-center text-[11px] leading-relaxed text-ink-faint">
        Registrasi publik ditutup. Akun dashboard hanya dibuat oleh Admin IT sekolah.
    </p>
</x-guest-layout>
