<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'PELOG') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_sekolah.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
<div class="flex min-h-full">

    {{-- Panel brand --}}
    <aside class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-navy-950 p-10 lg:flex">
        <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.05]" aria-hidden="true">
            <defs>
                <pattern id="pelog-pattern" width="56" height="56" patternUnits="userSpaceOnUse">
                    <path d="M28 4L52 28L28 52L4 28Z" fill="none" stroke="white" stroke-width="1"/>
                    <path d="M28 16L40 28L28 40L16 28Z" fill="none" stroke="white" stroke-width="0.6"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#pelog-pattern)"/>
        </svg>

        <div class="relative flex items-center gap-3">
            <img src="{{ asset('images/logo_sekolah.png') }}" alt="Logo SMK Negeri 1 Mas Ubud" class="h-12 w-12 rounded-full bg-white object-contain p-0.5">
            <div>
                <p class="text-lg font-extrabold tracking-wide text-white">PELOG</p>
                <p class="text-xs text-navy-300">SMK Negeri 1 Mas Ubud</p>
            </div>
        </div>

        <div class="relative max-w-md">
            <h1 class="text-2xl font-bold leading-snug text-white">
                Sistem Monitoring Aktivitas Komputer &amp; Laptop Sekolah
            </h1>
        </div>

        <div class="relative">
            <p class="text-sm font-semibold italic tracking-wide text-gold-400">Kriya Kencana Raksa</p>
            <p class="mt-1 text-[11px] text-navy-400">&copy; {{ now()->year }} SMK Negeri 1 Mas Ubud</p>
        </div>
    </aside>

    {{-- Panel formulir --}}
    <main class="flex w-full flex-col items-center justify-center bg-paper px-6 py-10 lg:w-1/2">
        <div class="mb-8 flex items-center gap-3 lg:hidden">
            <img src="{{ asset('images/logo_sekolah.png') }}" alt="Logo sekolah" class="h-10 w-10 rounded-full bg-white object-contain p-0.5 shadow-card">
            <div>
                <p class="text-sm font-extrabold tracking-wide text-navy-950">PELOG</p>
                <p class="text-[11px] text-ink-faint">SMK Negeri 1 Mas Ubud</p>
            </div>
        </div>

        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>

        <p class="mt-10 text-center text-[11px] text-ink-faint">
            &copy; {{ now()->year }} SMK Negeri 1 Mas Ubud
        </p>
    </main>
</div>
</body>
</html>
