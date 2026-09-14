<x-dashboard-layout title="Screenshot">
    <div class="space-y-4">
        <section class="card p-4">
            <form method="GET" action="{{ route('screenshots.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="label" for="from">Dari</label>
                    <input type="date" id="from" name="from" value="{{ $from }}" class="input text-xs">
                </div>
                <div>
                    <label class="label" for="to">Sampai</label>
                    <input type="date" id="to" name="to" value="{{ $to }}" class="input text-xs">
                </div>
                <button type="submit" class="btn-primary !py-2 text-xs">Terapkan</button>
                <a href="{{ route('screenshots.index') }}" class="btn-secondary !py-2 text-xs">Reset</a>
                <p class="ml-auto text-xs text-ink-faint">{{ $screenshots->total() }} screenshot · retensi permanen</p>
            </form>
        </section>

        @if ($screenshots->isEmpty())
            <section class="card px-4 py-16 text-center">
                <p class="text-sm font-medium text-ink-soft">Belum ada screenshot pada rentang ini.</p>
                <p class="mt-1 text-xs text-ink-faint">Screenshot diambil otomatis 1× per sesi siswa (menit ke-30).</p>
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
                                {{ $screenshot->captured_at?->timezone('Asia/Makassar')->format('d/m H:i') }} WITA
                            </p>
                            <p class="font-mono text-[10px] uppercase text-ink-faint">{{ $screenshot->format->value }} · {{ number_format($screenshot->size_bytes / 1024, 1) }} KB</p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <div>{{ $screenshots->links() }}</div>
        @endif
    </div>
</x-dashboard-layout>
