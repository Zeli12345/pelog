<x-dashboard-layout title="Screenshot" subtitle="Bukti visual aktivitas sesi">
    <div class="space-y-4">
        <section class="card p-4">
            <form method="GET" action="{{ route('screenshots.index') }}" id="screenshot-filter" class="grid grid-cols-2 gap-3 lg:grid-cols-6">
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
                    <label class="label" for="from">Dari</label>
                    <input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="input text-xs">
                </div>
                <div>
                    <label class="label" for="to">Sampai</label>
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
                    <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Nama / NISN / tujuan…" class="input text-xs">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary !py-2 text-xs">Terapkan</button>
                    <a href="{{ route('screenshots.index') }}" class="btn-secondary !py-2 text-xs">Reset</a>
                </div>
            </form>
        </section>

        @if ($screenshots->isEmpty())
            <section class="card px-4 py-16 text-center">
                <p class="text-sm font-medium text-ink-soft">Belum ada screenshot yang cocok dengan filter.</p>
                <p class="mt-1 text-xs text-ink-faint">Screenshot diambil berkala selama sesi siswa dan bisa diminta dari halaman detail perangkat.</p>
            </section>
        @else
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($screenshots as $screenshot)
                    @php $session = $screenshot->usageSession; @endphp
                    <figure class="card group overflow-hidden">
                        <a href="{{ route('screenshots.file', $screenshot) }}" target="_blank" class="block bg-navy-950">
                            <img
                                src="{{ route('screenshots.thumb', $screenshot) }}"
                                alt="Screenshot {{ $session?->student?->name }}"
                                loading="lazy"
                                class="aspect-video w-full object-cover transition group-hover:opacity-90"
                            >
                        </a>
                        <figcaption class="space-y-1 p-3">
                            <p class="truncate text-xs font-semibold text-ink">{{ $session?->student?->name ?? $session?->staff?->name ?? 'Tidak diketahui' }}</p>
                            <p class="truncate text-[11px] text-ink-faint">
                                {{ $session?->device?->label ?? $session?->device?->hostname }} ·
                                {{ $screenshot->captured_at?->timezone('Asia/Makassar')->format('d/m H:i:s') }} WITA
                            </p>
                            <p class="font-mono text-[10px] uppercase text-ink-faint">{{ $screenshot->format->value }} · {{ number_format($screenshot->size_bytes / 1024, 1) }} KB</p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-3 text-xs text-ink-faint">
                    <span>{{ $screenshots->total() }} screenshot</span>
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
    </div>
</x-dashboard-layout>
