<x-dashboard-layout title="Perangkat" subtitle="Status & riwayat seluruh perangkat">
    <x-slot:actions>
        <form method="GET" action="{{ route('devices.index') }}" class="flex items-center gap-2">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-ink-faint">
                    <x-icon name="search" size="h-3.5 w-3.5" />
                </span>
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari perangkat…" class="input !w-44 py-1.5 pl-8 text-xs lg:!w-56">
            </div>
            <select name="status" class="input !w-32 py-1.5 text-xs" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <option value="in_use" @selected($statusFilter === 'in_use')>Digunakan</option>
                <option value="available" @selected($statusFilter === 'available')>Tersedia</option>
                <option value="offline" @selected($statusFilter === 'offline')>Offline</option>
                <option value="maintenance" @selected($statusFilter === 'maintenance')>Perawatan</option>
            </select>
            <button type="submit" class="btn-primary !py-1.5 text-xs">Cari</button>
        </form>
    </x-slot:actions>

    <div class="space-y-4">
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-line px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Daftar Perangkat</h2>
                    <p class="text-xs text-ink-faint">{{ $devices->count() }} perangkat ditampilkan · offline jika tanpa heartbeat &gt; {{ max(1, intdiv($onlineWindow, 60)) }} menit</p>
                </div>
            </header>

            @if ($devices->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-ink-soft">Tidak ada perangkat yang cocok.</p>
                    <p class="mt-1 text-xs text-ink-faint">Coba ubah kata kunci atau filter status.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-4 py-2.5 font-semibold">Perangkat</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                <th class="px-4 py-2.5 font-semibold">Pengguna Aktif</th>
                                <th class="px-4 py-2.5 font-semibold">Penyimpanan</th>
                                <th class="px-4 py-2.5 font-semibold">Agent / OS</th>
                                <th class="px-4 py-2.5 font-semibold">Terakhir Terlihat</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($devices as $device)
                                @php
                                    $status = $statuses[$device->id];
                                    $session = $device->sessions->first();
                                    $storagePercent = $device->storage_total_gb > 0
                                        ? min(100, (int) round($device->storage_used_gb / $device->storage_total_gb * 100))
                                        : 0;
                                @endphp
                                <tr class="table-row">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-navy-900">{{ $device->label ?? $device->hostname }}</p>
                                        <p class="font-mono text-[11px] text-ink-faint">{{ $device->hostname }}{{ $device->location_label ? ' · '.$device->location_label : '' }}</p>
                                    </td>
                                    <td class="px-4 py-3"><x-status-chip :status="$status->value" /></td>
                                    <td class="px-4 py-3">
                                        @if ($session?->student)
                                            <p class="font-medium text-ink">{{ $session->student->name }}</p>
                                            <p class="text-[11px] text-ink-faint">{{ $session->student->class }} · {{ $session->subject?->name ?? 'tanpa mapel' }}</p>
                                        @elseif ($session?->staff)
                                            <p class="font-medium text-ink">{{ $session->staff->name }}</p>
                                            <p class="text-[11px] text-ink-faint">Guru / Pegawai</p>
                                        @else
                                            <span class="text-ink-faint">—</span>
                                        @endif
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
                                        <p class="font-mono">{{ $device->agent_version ?? '—' }}</p>
                                        <p class="text-[11px] text-ink-faint">{{ $device->windows_version ? \Illuminate\Support\Str::limit($device->windows_version, 30) : '—' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-ink-soft">{{ $device->last_seen_at?->diffForHumans(short: true) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('devices.show', $device) }}" class="btn-secondary !py-1 text-xs">Detail</a>
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
