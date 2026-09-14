<x-dashboard-layout title="Ringkasan" subtitle="Pemantauan perangkat & sesi lab komputer">
    <div class="space-y-6">

        {{-- Statistik --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card label="Total Perangkat" :value="$stats['total']" icon="monitor" tone="navy" />
            <x-stat-card label="Sedang Digunakan" :value="$stats['in_use']" icon="clock" tone="moss" hint="sesi aktif berjalan" />
            <x-stat-card label="Tersedia" :value="$stats['available']" icon="check" tone="navy" hint="siap dipakai" />
            <x-stat-card label="Offline" :value="$stats['offline']" icon="wifi-off" tone="gold" :hint="'> ' . max(1, intdiv($onlineWindow, 60)) . ' menit tanpa heartbeat'" />
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card label="Sesi Hari Ini" :value="$stats['sessions_today']" icon="file-text" tone="navy" />
            <x-stat-card label="Screenshot Hari Ini" :value="$stats['screenshots_today']" icon="camera" tone="navy" />
            <x-stat-card label="Siswa Aktif" :value="$stats['students_active']" icon="users" tone="navy" />
            <x-stat-card label="Perawatan" :value="$stats['maintenance']" icon="alert-triangle" tone="brick" />
        </div>

        {{-- Daftar perangkat --}}
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Status Perangkat</h2>
                    <p class="text-xs text-ink-faint">{{ $stats['total'] }} perangkat terdaftar · status diperbarui dari heartbeat</p>
                </div>
                <a href="{{ route('devices.index') }}" class="btn-secondary !py-1.5 text-xs">
                    Semua perangkat
                    <x-icon name="chevron-right" size="h-3.5 w-3.5" />
                </a>
            </header>

            @if ($devices->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-ink-soft">Belum ada perangkat terdaftar.</p>
                    <p class="mt-1 text-xs text-ink-faint">Perangkat akan muncul otomatis setelah aplikasi kiosk di laptop melakukan enrollment.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-4 py-2.5 font-semibold">Perangkat</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                <th class="px-4 py-2.5 font-semibold">Pengguna</th>
                                <th class="px-4 py-2.5 font-semibold">Mapel / Tujuan</th>
                                <th class="px-4 py-2.5 font-semibold">Durasi</th>
                                <th class="px-4 py-2.5 font-semibold">Penyimpanan</th>
                                <th class="px-4 py-2.5 font-semibold">Terakhir Terlihat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($devices as $device)
                                @php
                                    $status = $statuses[$device->id];
                                    $session = $device->sessions->first();
                                    $start = $session?->started_at_server ?? $session?->started_at_client;
                                    $storagePercent = $device->storage_total_gb > 0
                                        ? min(100, (int) round($device->storage_used_gb / $device->storage_total_gb * 100))
                                        : 0;
                                @endphp
                                <tr class="table-row">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('devices.show', $device) }}" class="whitespace-nowrap font-semibold text-navy-900 hover:underline">
                                            {{ $device->label ?? $device->hostname }}
                                        </a>
                                        <p class="font-mono text-[11px] text-ink-faint">{{ $device->hostname }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-chip :status="$status->value" />
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($session?->student)
                                            <p class="font-medium text-ink">{{ $session->student->name }}</p>
                                            <p class="text-[11px] text-ink-faint">{{ $session->student->class }}</p>
                                        @elseif ($session?->staff)
                                            <p class="font-medium text-ink">{{ $session->staff->name }}</p>
                                            <p class="text-[11px] text-ink-faint">Guru / Pegawai</p>
                                        @else
                                            <span class="text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="max-w-[16rem] px-4 py-3">
                                        @if ($session)
                                            <p class="truncate text-ink-soft" title="{{ $session->usage_purpose }}">{{ $session->usage_purpose }}</p>
                                            @if ($session->subject)
                                                <p class="text-[11px] text-ink-faint">{{ $session->subject->name }}</p>
                                            @endif
                                        @else
                                            <span class="text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-ink-soft">
                                        {{ $start ? $start->diff(now())->format('%H:%I') : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($device->storage_total_gb > 0)
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-line">
                                                    <div class="h-full rounded-full {{ $storagePercent > 85 ? 'bg-brick-500' : 'bg-navy-600' }}" style="width: {{ $storagePercent }}%"></div>
                                                </div>
                                                <span class="font-mono text-[11px] text-ink-faint">{{ $device->storage_used_gb }}/{{ $device->storage_total_gb }} GB</span>
                                            </div>
                                        @else
                                            <span class="text-ink-faint">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-ink-soft">
                                        {{ $device->last_seen_at?->diffForHumans(short: true) ?? '—' }}
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
