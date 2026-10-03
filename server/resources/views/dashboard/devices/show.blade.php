<x-dashboard-layout :title="'Perangkat — '.($device->label ?? $device->hostname)">
    <x-slot:actions>
        <a href="{{ route('devices.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    <div class="space-y-4">
        {{-- Header perangkat --}}
        <section class="card p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-navy-900 text-white">
                        <x-icon name="monitor" size="h-6 w-6" />
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-ink">{{ $device->label ?? $device->hostname }}</h2>
                            <x-status-chip :status="$status->value" />
                        </div>
                        <p class="font-mono text-xs text-ink-faint">{{ $device->hostname }} · {{ $device->location_label ?? 'tanpa lokasi' }}</p>
                    </div>
                </div>

                @if ($activeSession)
                    @php
                        $start = $activeSession->started_at_server ?? $activeSession->started_at_client;
                    @endphp
                    <div class="rounded-md border border-moss-200 bg-moss-50 px-4 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-moss-700">Sesi Berjalan</p>
                        <p class="text-sm font-semibold text-ink">{{ $activeSession->student?->name ?? $activeSession->staff?->name }}</p>
                        <p class="text-xs text-ink-soft">{{ $activeSession->student?->class ?? 'Guru / Pegawai' }} · {{ $activeSession->subject?->name ?? 'tanpa mapel' }}</p>
                        <p class="mt-1 font-mono text-xs text-moss-700">Durasi {{ $start ? $start->diff(now())->format('%H:%I') : '—' }}</p>

                        @if (auth()->user()->isAdminIt())
                            <div class="mt-2 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('sessions.close', $activeSession) }}" onsubmit="return confirm('Tutup paksa sesi ini? Laptop akan dimatikan otomatis oleh kiosk.')">
                                    @csrf
                                    <button type="submit" class="btn-secondary !px-2.5 !py-1 text-[11px]">Tutup Sesi</button>
                                </form>
                                <form method="POST" action="{{ route('devices.request-screenshot', $device) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary !px-2.5 !py-1 text-[11px]">
                                        <x-icon name="camera" size="h-3.5 w-3.5" />
                                        Minta Screenshot
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-4 text-sm lg:grid-cols-4">
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">UUID</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs text-ink-soft">{{ $device->uuid }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Tipe</dt>
                    <dd class="mt-0.5 text-ink-soft">{{ strtoupper($device->device_type) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Penyimpanan C:</dt>
                    <dd class="mt-0.5 font-mono text-xs text-ink-soft">
                        @if ($device->storage_total_gb > 0)
                            {{ $device->storage_used_gb }} GB / {{ $device->storage_total_gb }} GB
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Terakhir Terlihat</dt>
                    <dd class="mt-0.5 text-ink-soft">{{ $device->last_seen_at?->timezone('Asia/Makassar')->translatedFormat('d M Y H:i') ?? '—' }} WITA</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Agent</dt>
                    <dd class="mt-0.5 font-mono text-xs text-ink-soft">{{ $device->agent_version ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Windows</dt>
                    <dd class="mt-0.5 text-ink-soft">{{ $device->windows_version ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Enrolled</dt>
                    <dd class="mt-0.5 text-ink-soft">{{ $device->enrolled_at?->timezone('Asia/Makassar')->translatedFormat('d M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Aktif</dt>
                    <dd class="mt-0.5">{{ $device->is_active ? 'Ya' : 'Tidak (nonaktif)' }}</dd>
                </div>
            </dl>
        </section>

        {{-- Pengaturan perangkat (Admin IT) --}}
        @if (auth()->user()->isAdminIt())
            <section class="card p-4">
                <header class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-ink">Pengaturan Perangkat</h3>
                    @if ($device->status === \App\Enums\DeviceStatus::Maintenance)
                        <span class="badge border-gold-300 bg-gold-50 text-gold-800">Mode perawatan aktif</span>
                    @endif
                </header>

                <form method="POST" action="{{ route('devices.update', $device) }}" class="grid grid-cols-1 gap-3 md:grid-cols-4 md:items-end">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="label" for="label">Nama perangkat</label>
                        <input id="label" name="label" value="{{ old('label', $device->label) }}" placeholder="mis. LAB-BL-09" class="input text-xs">
                    </div>
                    <div>
                        <label class="label" for="location_label">Lokasi</label>
                        <input id="location_label" name="location_label" value="{{ old('location_label', $device->location_label) }}" placeholder="mis. Lab Komputer 1" class="input text-xs">
                    </div>
                    <div>
                        <label class="label" for="status">Status</label>
                        <select id="status" name="status" class="input text-xs">
                            <option value="available" @selected($device->status->value === 'available')>Tersedia (normal)</option>
                            <option value="maintenance" @selected($device->status->value === 'maintenance')>Perawatan</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary w-full justify-center !py-2 text-xs">Simpan Pengaturan</button>
                    </div>
                </form>

                <p class="mt-2 text-[11px] leading-relaxed text-ink-faint">
                    Status perawatan memblokir dimulainya sesi baru di laptop ini sampai dikembalikan ke Tersedia.
                </p>
            </section>
        @endif

        {{-- Riwayat sesi --}}
        <section class="card overflow-hidden">
            <header class="border-b border-line px-4 py-3">
                <h3 class="text-sm font-semibold text-ink">Riwayat 50 Sesi Terakhir</h3>
            </header>

            @if ($history->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-ink-faint">Belum ada riwayat sesi pada perangkat ini.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-4 py-2.5 font-semibold">Waktu</th>
                                <th class="px-4 py-2.5 font-semibold">Pengguna</th>
                                <th class="px-4 py-2.5 font-semibold">Tujuan</th>
                                <th class="px-4 py-2.5 font-semibold">Durasi</th>
                                <th class="px-4 py-2.5 font-semibold">Penutupan</th>
                                <th class="px-4 py-2.5 font-semibold">Screenshot</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($history as $session)
                                <tr class="table-row {{ $session->isActive() ? 'bg-moss-50/40' : '' }}">
                                    <td class="px-4 py-2.5 font-mono text-xs text-ink-soft">
                                        {{ ($session->started_at_server ?? $session->started_at_client)?->timezone('Asia/Makassar')->format('d/m H:i') ?? '—' }}
                                        @if ($session->isActive())
                                            <span class="ml-1 text-moss-600">• aktif</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <p class="font-medium text-ink">{{ $session->student?->name ?? $session->staff?->name ?? '—' }}</p>
                                        <p class="text-[11px] text-ink-faint">{{ $session->student?->class ?? $session->user_type->label() }}</p>
                                    </td>
                                    <td class="max-w-[18rem] px-4 py-2.5">
                                        <p class="truncate text-ink-soft" title="{{ $session->usage_purpose }}">{{ $session->usage_purpose }}</p>
                                        <p class="text-[11px] text-ink-faint">{{ $session->subject?->name ?? '—' }}</p>
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-ink-soft">{{ $session->duration_minutes }} mnt</td>
                                    <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $session->close_reason?->label() ?? '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($session->screenshot)
                                            <a href="{{ route('screenshots.file', $session->screenshot) }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-navy-700 hover:underline">
                                                <x-icon name="image" size="h-3.5 w-3.5" />
                                                Lihat
                                            </a>
                                        @else
                                            <span class="text-xs text-ink-faint">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-dashboard-layout>
