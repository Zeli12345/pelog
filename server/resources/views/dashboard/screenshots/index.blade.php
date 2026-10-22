<x-dashboard-layout title="Galeri Layar & Kamera" subtitle="Tangkapan layar dan kamera selama sesi perangkat">
    @php
        $kindLabels = [
            '' => 'Semua',
            'screen' => 'Layar',
            'webcam' => 'Webcam',
        ];
    @endphp

    <div class="space-y-4"
         x-data="{
            open: false, src: '', label: '', meta: '', link: '', zoom: 1,
            showMedia(data) {
                this.src = data.src; this.label = data.label; this.meta = data.meta; this.link = data.link; this.zoom = 1; this.open = true;
            },
            closeMedia() { this.open = false; },
            zoomIn() { this.zoom = Math.min(4, Math.round((this.zoom + 0.25) * 100) / 100); },
            zoomOut() { this.zoom = Math.max(0.25, Math.round((this.zoom - 0.25) * 100) / 100); },
            resetZoom() { this.zoom = 1; }
         }">
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
                        <option value="location" @selected($filters['sort'] === 'location')>Lokasi (A-Z)</option>
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
                        $locationName = $session?->device?->location?->name;
                        $captured = $screenshot->captured_at?->timezone('Asia/Makassar')->format('d/m H:i:s') ?? '—';
                        $badge = $screenshot->kind->value === 'webcam' ? 'Webcam' : 'Layar';
                    @endphp
                    <figure class="card group overflow-hidden">
                        <button type="button"
                                class="block w-full bg-navy-950 text-left"
                                x-on:click="showMedia({{ \Illuminate\Support\Js::from([
                                    'src' => route('screenshots.file', $screenshot),
                                    'label' => $badge.' · '.$person,
                                    'meta' => $deviceName.($locationName ? ' · '.$locationName : '').' · '.$captured.' WITA · '.number_format($screenshot->size_bytes / 1024, 1).' KB',
                                    'link' => route('screenshots.file', $screenshot),
                                ]) }})">
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
                            <p class="truncate text-[11px] text-ink-faint">
                                {{ $deviceName }}@if ($locationName) · {{ $locationName }}@endif · {{ $captured }} WITA
                            </p>
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

        {{-- Lightbox: fixed ke viewport, bukan ke container halaman --}}
        <div x-show="open" x-cloak x-transition.opacity
             class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/85 p-2 sm:p-5"
             @keydown.escape.window="closeMedia()"
             @click.self="closeMedia()">
            <div class="flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
                <header class="flex items-start justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink" x-text="label"></p>
                        <p class="mt-0.5 truncate text-xs text-ink-faint" x-text="meta"></p>
                    </div>
                    <button type="button" class="btn-secondary !p-2" x-on:click="closeMedia()" title="Tutup">
                        <x-icon name="x" size="h-4 w-4" />
                    </button>
                </header>

                <div class="flex min-h-0 flex-1 items-center justify-center overflow-auto bg-navy-950 p-2 sm:p-4">
                    <img :src="src"
                         alt=""
                         class="h-auto max-w-full object-contain"
                         :style="zoom <= 1
                            ? { width: (zoom * 100) + '%', maxWidth: '100%', maxHeight: '68vh' }
                            : { width: (zoom * 100) + '%', maxWidth: 'none', maxHeight: 'none' }">
                </div>

                <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-2.5 sm:px-5">
                    <div class="flex items-center gap-1.5">
                        <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs" x-on:click="zoomOut()" :disabled="zoom <= 0.25" title="Perkecil">−</button>
                        <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs font-mono" x-on:click="resetZoom()" x-text="Math.round(zoom * 100) + '%'"></button>
                        <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs" x-on:click="zoomIn()" :disabled="zoom >= 4" title="Perbesar">+</button>
                        <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs" x-on:click="resetZoom()">Fit</button>
                    </div>
                    <a :href="link" target="_blank" rel="noopener" class="btn-secondary !py-1.5 text-xs">Buka file asli</a>
                </footer>
            </div>
        </div>
    </div>
</x-dashboard-layout>
