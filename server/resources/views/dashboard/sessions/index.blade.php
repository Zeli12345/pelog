<x-dashboard-layout title="Sesi Penggunaan" subtitle="Riwayat pemakaian perangkat & tujuan">
    <div class="space-y-4">
        {{-- Filter --}}
        <section class="card p-4">
            <form method="GET" action="{{ route('sessions.index') }}" class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                <div>
                    <label class="label" for="from">Dari</label>
                    <input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="input text-xs">
                </div>
                <div>
                    <label class="label" for="to">Sampai</label>
                    <input type="date" id="to" name="to" value="{{ $filters['to'] }}" class="input text-xs">
                </div>
                <div>
                    <label class="label" for="user_type">Tipe Pengguna</label>
                    <select id="user_type" name="user_type" class="input text-xs">
                        <option value="">Semua</option>
                        @foreach ($userTypes as $type)
                            <option value="{{ $type->value }}" @selected($filters['user_type'] === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="device_id">Perangkat</label>
                    <select id="device_id" name="device_id" class="input text-xs">
                        <option value="">Semua</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected((string) $filters['device_id'] === (string) $device->id)>{{ $device->label ?? $device->hostname }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="q">Cari</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Nama / NISN / tujuan…" class="input text-xs">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary !py-2 text-xs">Terapkan</button>
                    <a href="{{ route('sessions.index') }}" class="btn-secondary !py-2 text-xs">Reset</a>
                </div>
            </form>
        </section>

        {{-- Tabel --}}
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold text-ink">Riwayat Sesi</h2>
                <span class="font-mono text-xs text-ink-faint">{{ $sessions->total() }} sesi</span>
            </header>

            @if ($sessions->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-ink-soft">Belum ada sesi pada rentang ini.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-4 py-2.5 font-semibold">Mulai (WITA)</th>
                                <th class="px-4 py-2.5 font-semibold">Perangkat</th>
                                <th class="px-4 py-2.5 font-semibold">Pengguna</th>
                                <th class="px-4 py-2.5 font-semibold">Mapel &amp; Tujuan</th>
                                <th class="px-4 py-2.5 font-semibold">Durasi</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                @if (auth()->user()->isAdminIt())
                                    <th class="px-4 py-2.5 text-right font-semibold">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($sessions as $session)
                                @php
                                    $start = $session->started_at_server ?? $session->started_at_client;
                                @endphp
                                <tr class="table-row {{ $session->isActive() ? 'bg-moss-50/40' : '' }}">
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-ink-soft">
                                        {{ $start?->timezone('Asia/Makassar')->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs">
                                        <a href="{{ route('devices.show', $session->device_id) }}" class="font-medium text-navy-900 hover:underline">
                                            {{ $session->device?->label ?? $session->device?->hostname }}
                                        </a>
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
                                    <td class="px-4 py-2.5">
                                        @if ($session->isActive())
                                            <span class="badge border-moss-200 bg-moss-50 text-moss-700">Aktif</span>
                                        @else
                                            <span class="badge-muted">{{ $session->close_reason?->label() ?? 'Selesai' }}</span>
                                        @endif
                                    </td>
                                    @if (auth()->user()->isAdminIt())
                                        <td class="px-4 py-2.5 text-right">
                                            @if ($session->isActive())
                                                <form method="POST" action="{{ route('sessions.close', $session) }}" onsubmit="return confirm('Tutup paksa sesi ini? Perangkat akan kembali tersedia.')">
                                                    @csrf
                                                    <button type="submit" class="btn-secondary !px-2.5 !py-1 text-[11px]">Tutup</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-line px-4 py-3">
                    {{ $sessions->links() }}
                </div>
            @endif
        </section>
    </div>
</x-dashboard-layout>
