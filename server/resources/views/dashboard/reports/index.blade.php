<x-dashboard-layout title="Laporan" subtitle="Rekapitulasi penggunaan perangkat">
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="btn-secondary !py-1.5 text-xs">
                <x-icon name="download" size="h-3.5 w-3.5" />
                CSV
            </a>
            <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="btn-secondary !py-1.5 text-xs">
                <x-icon name="download" size="h-3.5 w-3.5" />
                Excel
            </a>
        </div>
    </x-slot:actions>

    <div class="space-y-4">
        {{-- Filter --}}
        <section class="card p-4">
            <form method="GET" action="{{ route('reports.index') }}" class="grid grid-cols-2 gap-3 lg:grid-cols-6">
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
                        <option value="student" @selected($filters['user_type'] === 'student')>Siswa</option>
                        <option value="staff" @selected($filters['user_type'] === 'staff')>Guru / Pegawai</option>
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
                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit" class="btn-primary !py-2 text-xs">Terapkan</button>
                    <a href="{{ route('reports.index') }}" class="btn-secondary !py-2 text-xs">Reset</a>
                </div>
            </form>
        </section>

        {{-- Ringkasan --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat-card label="Total Sesi" :value="number_format($summary->total_sessions ?? 0)" icon="file-text" tone="navy" />
            <x-stat-card label="Total Durasi" :value="number_format($summary->total_minutes ?? 0).' mnt'" icon="clock" tone="moss" />
            <x-stat-card label="Sesi Siswa" :value="number_format($summary->student_sessions ?? 0)" icon="users" tone="navy" />
            <x-stat-card label="Sesi Guru/Pegawai" :value="number_format($summary->staff_sessions ?? 0)" icon="user-check" tone="navy" />
        </div>

        {{-- Mapel teratas --}}
        <section class="card p-4">
            <h3 class="text-sm font-semibold text-ink">Mata Pelajaran Terbanyak</h3>
            <p class="text-xs text-ink-faint">10 mapel dengan sesi terbanyak</p>
            @if ($bySubject->isEmpty())
                <p class="mt-4 text-xs text-ink-faint">Belum ada data.</p>
            @else
                <table class="mt-3 w-full text-sm">
                    <tbody class="divide-y divide-line/70">
                        @foreach ($bySubject as $row)
                            <tr>
                                <td class="py-2 text-ink-soft">{{ $row->subject_name }}</td>
                                <td class="py-2 text-right font-mono text-xs text-ink-faint">{{ $row->total }} sesi · {{ number_format($row->total_minutes) }} mnt</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- Tabel sesi --}}
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold text-ink">Rincian Sesi</h2>
                <span class="font-mono text-xs text-ink-faint">{{ $sessions->total() }} baris</span>
            </header>

            @if ($sessions->isEmpty())
                <div class="px-4 py-12 text-center text-sm text-ink-faint">Tidak ada data pada rentang ini.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-4 py-2.5 font-semibold">Tanggal</th>
                                <th class="px-4 py-2.5 font-semibold">Perangkat</th>
                                <th class="px-4 py-2.5 font-semibold">Pengguna</th>
                                <th class="px-4 py-2.5 font-semibold">Mapel</th>
                                <th class="px-4 py-2.5 font-semibold">Durasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line/70">
                            @foreach ($sessions as $session)
                                <tr class="table-row">
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-ink-soft">
                                        {{ ($session->started_at_server ?? $session->started_at_client)?->timezone('Asia/Makassar')->format('d/m H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $session->device?->label ?? $session->device?->hostname ?? '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        <p class="font-medium text-ink">{{ $session->student?->name ?? $session->staff?->name ?? '—' }}</p>
                                        <p class="text-[11px] text-ink-faint">{{ $session->student?->class ?? $session->user_type->label() }}</p>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $session->subject?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-ink-soft">{{ $session->duration_minutes }} mnt</td>
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
