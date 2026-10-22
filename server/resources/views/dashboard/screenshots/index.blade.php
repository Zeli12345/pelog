<x-dashboard-layout title="Galeri Layar & Kamera" subtitle="Tangkapan layar dan kamera selama sesi perangkat">
    @php
        $kindLabels = [
            '' => 'Semua',
            'screen' => 'Layar',
            'webcam' => 'Webcam',
        ];
    @endphp

    <div class="space-y-4" x-data="{ open: false, src: '', label: '', meta: '', link: '', close() { this.open = false; } }">
        <section class="card p-4">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                @foreach ($kindLabels as $kindValue => $kindLabel)
                    @php
                        $query = array_merge(request()->except(['page', 'kind']), $kindValue === '' ? [] : ['kind' => $kindValue]);
                        $count = $kindValue === '' ? $counts['all'] : $counts[$kindValue];
                    @endphp
                    <a href="{{ route('screenshots.index', $query) }}"
                       class="rounded-full border px-3 py-1.5 text-xs font-semibold transition
                              {{ ($filters['kind'] ?? '') === $kindValue
                                  ? 'border-navy-900 bg-navy-900 text-white'
                                  : 'border-line bg-white text-ink-soft hover:border-navy-300' }}">
                        {{ $kindLabel }} <span class="ml-1 opacity-70">({{ $count }})</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('screenshots.index') }}" id="screenshot-filter" class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                <input type="hidden" name="kind" value="{{ $filters['kind'] }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div>
                    <label class="label" for="device_id">Perangkat</label>
                    <select id="device_id" name="device_id" class="input text-xs">
                        <option value="">Semua</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected((string) $filters['device_id'] === (string) $device->id)>
                                {{ $device->label ?? $device->hostname }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="from">Dari (WITA)</label>
                    <input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="input text-xs">
                </div>
                <div>
                    <label class="label" for="to">Sampai (WITA)</label>
                    <input type="date" id="to" name="to" value="{{ $filters['to'] }}" class="input text-xs">
                </div>
                <div>
                    <label class="label" for="sort">Urutkan</label>
                    <select id="sort" name="sort" class="input text-xs">
                        <option value="latest" @selected($filters['sort'] === 'latest')>Terbaru dulu</option>
                        <option value="oldest" @selected($filters['sort'] === 'oldest')>Terlama dulu</option>
                        <option value="device" @selected($filters['sort'] === 'device')>Perangkat (A-Z)</option>
                        <option value="student" @selected($filters['sort'] === 'student')>Nama siswa (A-Z)</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="q">Cari</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Nama / NISN / tujuan / perangkat…" class="input text-xs">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary !py-2 text-xs">Terapkan</button>
                    <a href="{{ route('screenshots.index') }}" class="btn-secondary !py-2 text-xs">Reset</a>
                </div>
            </form>
        </section>

        @if ($screenshots->isEmpty())
            <section class="card px-4 py-16 text-center">
                <p class="text-sm font-medium text-ink-soft">Belum ada media yang cocok dengan filter.</p>
                <p class="mt-1 text-xs text-ink-faint">Tangkapan layar dan kamera akan muncul di sini setelah sesi berjalan.</p>
            </section>
        @else
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($screenshots as $screenshot)
                    @php
                        $session = $screenshot->usageSession;
                        $person = $session?->student?->name ?? $session?->staff?->name ?? 'Tidak diketahui';
                        $deviceName = $session?->device?->label ?? $session?->device?->hostname ?? 'Perangkat';
                        $captured = $screenshot->captured_at?->timezone('Asia/Makassar')->format('d/m H:i:s') ?? '—';
                        $badge = $screenshot->kind->value === 'webcam' ? 'Webcam' : 'Layar';
                    @endphp
                    <figure class="card group overflow-hidden">
                        <button type="button"
                                class="block w-full bg-navy-950 text-left"
                                x-on:click="open = true; src = '{{ route('screenshots.file', $screenshot) }}'; label = '{{ $badge }} · {{ addslashes($person) }}'; meta = '{{ addslashes($deviceName) }} · {{ $captured }} WITA · {{ number_format($screenshot->size_bytes / 1024, 1) }} KB'; link = '{{ route('screenshots.file', $screenshot) }}'">
                            <div class="relative">
                                <img
                                    src="{{ route('screenshots.thumb', $screenshot) }}"
                                    alt="{{ $badge }} {{ $person }}"
                                    loading="lazy"
                                    class="aspect-video w-full object-cover transition group-hover:opacity-90"
                                >
                                <span class="absolute left-2 top-2 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide
                                             {{ $screenshot->kind->value === 'webcam' ? 'bg-gold-400 text-navy-950' : 'bg-white/90 text-navy-900' }}">
                                    {{ $badge }}
                                </span>
                            </div>
                        </button>
                        <figcaption class="space-y-1 p-3">
                            <p class="truncate text-xs font-semibold text-ink">{{ $person }}</p>
                            <p class="truncate text-[11px] text-ink-faint">{{ $deviceName }} · {{ $captured }} WITA</p>
                            <p class="font-mono text-[10px] uppercase text-ink-faint">{{ $screenshot->format->value }} · {{ number_format($screenshot->size_bytes / 1024, 1) }} KB</p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-3 text-xs text-ink-faint">
                    <span>{{ $screenshots->total() }} media</span>
                    <label class="flex items-center gap-1.5">
                        Tampilkan
                        <select name="per_page" form="screenshot-filter" onchange="this.form.submit()" class="input !w-auto py-1 text-xs">
                            @foreach ([10, 25, 50, 100] as $option)
                                <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        per halaman
                    </label>
                </div>
                {{ $screenshots->links() }}
            </div>
        @endif

        {{-- Lightbox --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/80 p-4" @keydown.escape.window="open = false">
            <div class="max-h-full w-full max-w-4xl overflow-auto rounded-xl bg-white p-4 shadow-2xl" x-on:click.outside="open = false">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-ink" x-text="label"></p>
                        <p class="mt-0.5 text-xs text-ink-faint" x-text="meta"></p>
                    </div>
                    <button type="button" class="btn-secondary !p-2" x-on:click="open = false" title="Tutup">
                        <x-icon name="x" size="h-4 w-4" />
                    </button>
                </div>
                <img :src="src" alt="" class="max-h-[70vh] w-full rounded-lg bg-navy-950 object-contain">
                <div class="mt-3 flex justify-end">
                    <a :href="link" target="_blank" rel="noopener" class="btn-secondary !py-1.5 text-xs">Buka file asli</a>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
