@props(['title' => null])

@php
    $user = auth()->user();
    $isAdmin = (bool) $user?->isAdminIt();
    $current = request()->route()?->getName();

    $isActive = function (string $name) use ($current): bool {
        if ($name === $current) {
            return true;
        }
        $prefix = \Illuminate\Support\Str::before($name, '.');
        return $prefix !== $name && $current !== null && str_starts_with($current, $prefix.'.');
    };

    $monitoring = [
        ['route' => 'dashboard', 'label' => 'Ringkasan', 'icon' => 'layout-dashboard'],
        ['route' => 'devices.index', 'label' => 'Perangkat', 'icon' => 'monitor'],
        ['route' => 'sessions.index', 'label' => 'Sesi Penggunaan', 'icon' => 'clock'],
        ['route' => 'reports.index', 'label' => 'Laporan', 'icon' => 'file-text'],
        ['route' => 'screenshots.index', 'label' => 'Screenshot', 'icon' => 'camera'],
    ];

    $master = $isAdmin ? [
        ['route' => 'students.index', 'label' => 'Siswa', 'icon' => 'users'],
        ['route' => 'staff.index', 'label' => 'Guru & Pegawai', 'icon' => 'user-check'],
        ['route' => 'subjects.index', 'label' => 'Mata Pelajaran', 'icon' => 'book-open'],
    ] : [];

    $system = $isAdmin ? [
        ['route' => 'audit.index', 'label' => 'Audit', 'icon' => 'shield-check'],
        ['route' => 'settings.index', 'label' => 'Pengaturan', 'icon' => 'settings'],
    ] : [];

    $groups = array_values(array_filter([
        ['title' => 'Pemantauan', 'items' => $monitoring],
        ['title' => 'Master Data', 'items' => $master],
        ['title' => 'Sistem', 'items' => $system],
    ], fn ($group) => count($group['items']) > 0));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}BALI-LOG</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_sekolah.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
<div class="min-h-full" x-data="{ sidebarOpen: false }">

    {{-- Overlay mobile --}}
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-ink/50 lg:hidden" x-on:click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-navy-950 transition-transform duration-200 lg:translate-x-0"
        x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
        {{-- Brand --}}
        <div class="flex items-center gap-3 border-b border-white/10 px-4 py-4">
            <img src="{{ asset('images/logo_sekolah.png') }}" alt="Logo SMKN 1 Mas Ubud" class="h-10 w-10 rounded-full bg-white object-contain p-0.5">
            <div class="min-w-0">
                <p class="text-sm font-extrabold tracking-wide text-white">BALI-LOG</p>
                <p class="truncate text-[11px] text-navy-300">SMK Negeri 1 Mas Ubud</p>
            </div>
        </div>

        {{-- Navigasi --}}
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
            @foreach ($groups as $group)
                <div>
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-widest text-navy-400">{{ $group['title'] }}</p>
                    <div class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php $active = $isActive($item['route']); @endphp
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 rounded-md px-3 py-2 text-sm transition
                                      {{ $active ? 'bg-white/10 font-semibold text-white' : 'text-navy-200 hover:bg-white/5 hover:text-white' }}">
                                <x-icon :name="$item['icon']" size="h-4 w-4" />
                                {{ $item['label'] }}
                                @if ($active)
                                    <span class="ml-auto h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- Pengguna --}}
        <div class="border-t border-white/10 px-4 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-700 text-xs font-bold uppercase text-white">
                    {{ \Illuminate\Support\Str::of($user?->name ?? '?')->substr(0, 2) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-white">{{ $user?->name }}</p>
                    <p class="text-[11px] text-navy-300">{{ $user?->role?->label() }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md p-1.5 text-navy-300 transition hover:bg-white/10 hover:text-white" title="Keluar">
                        <x-icon name="logout" size="h-4 w-4" />
                    </button>
                </form>
            </div>
            <p class="mt-3 text-center text-[10px] italic tracking-wide text-navy-500">Kriya Kencana Raksa</p>
        </div>
    </aside>

    {{-- Konten --}}
    <div class="flex min-h-full flex-col lg:pl-64">
        {{-- Topbar --}}
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-line bg-white/95 px-4 backdrop-blur lg:px-6">
            <button class="rounded-md p-1.5 text-ink-soft hover:bg-paper lg:hidden" x-on:click="sidebarOpen = !sidebarOpen" title="Menu">
                <x-icon name="menu" size="h-5 w-5" />
            </button>

            <h1 class="truncate text-base font-semibold text-ink">{{ $title ?? 'Dashboard' }}</h1>

            <div class="ml-auto flex items-center gap-3">
                @isset($actions)
                    {{ $actions }}
                @endisset
                <span class="hidden font-mono text-xs text-ink-faint sm:block" title="Waktu server (WITA)">
                    {{ now()->timezone('Asia/Makassar')->translatedFormat('d M Y H:i') }} WITA
                </span>
            </div>
        </header>

        {{-- Flash --}}
        <main class="flex-1 px-4 py-6 lg:px-6">
            @if (session('status'))
                <div class="mb-4 flex items-start gap-2 rounded-md border border-moss-200 bg-moss-50 px-4 py-3 text-sm text-moss-700">
                    <x-icon name="check" size="h-4 w-4 mt-0.5" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 flex items-start gap-2 rounded-md border border-brick-200 bg-brick-50 px-4 py-3 text-sm text-brick-700">
                    <x-icon name="alert-triangle" size="h-4 w-4 mt-0.5" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="border-t border-line px-4 py-3 text-center text-[11px] text-ink-faint lg:px-6">
            BALI-LOG v{{ config('balilog.version') }} — Buku Aktivitas Laptop &amp; Informasi Device Log · SMK Negeri 1 Mas Ubud
        </footer>
    </div>
</div>
</body>
</html>
