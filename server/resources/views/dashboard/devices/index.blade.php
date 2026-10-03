<x-dashboard-layout title="Perangkat" subtitle="Status & riwayat seluruh perangkat">
    <x-slot:actions>
        <a href="{{ route('client.download') }}" class="btn-secondary !py-1.5 text-xs" title="Unduh installer untuk perangkat baru">
            <x-icon name="download" size="h-3.5 w-3.5" />
            Unduh Installer
        </a>
        <form method="GET" action="{{ route('devices.index') }}" class="flex items-center gap-2">
            @if ($trashed)
                <input type="hidden" name="trashed" value="1">
            @endif
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
            <form id="devices-bulk-delete-form" method="POST" action="{{ route('devices.bulk-delete') }}" data-confirm="Hapus perangkat terpilih? Kiosk perangkat akan menghapus dirinya sendiri saat boot berikutnya.">
                @csrf
            </form>
            <form id="devices-bulk-restore-form" method="POST" action="{{ route('devices.bulk-restore') }}" data-confirm="Pulihkan perangkat terpilih? Token lama akan berlaku kembali.">
                @csrf
            </form>

            <header class="flex items-center justify-between border-b border-line px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Daftar Perangkat</h2>
                    <p class="text-xs text-ink-faint">{{ $devices->count() }} perangkat ditampilkan · offline jika tanpa heartbeat &gt; {{ max(1, intdiv($onlineWindow, 60)) }} menit</p>
                </div>
                <a href="{{ $trashed ? route('devices.index', request()->except('trashed')) : route('devices.index', array_merge(request()->query(), ['trashed' => 1])) }}" class="btn-secondary !py-1.5 text-xs">
                    <x-icon :name="$trashed ? 'arrow-left' : 'trash'" size="h-3.5 w-3.5" />
                    {{ $trashed ? 'Kembali ke daftar aktif' : 'Tampilkan yang terhapus' }}
                </a>
            </header>

            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line bg-navy-50/40 px-4 py-2 text-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-1.5 text-ink-soft">
                        <input type="checkbox" data-bulk-select-all class="rounded border-line text-navy-700 focus:ring-navy-500">
                        Pilih semua
                    </label>
                    <span class="text-ink-faint"><span data-bulk-count>0</span> dipilih</span>
                    @if ($trashed)
                        <span class="badge-danger">Tampilan terhapus</span>
                    @else
                        <span class="text-ink-faint">Menghapus perangkat juga memicu kiosk menghapus dirinya sendiri saat boot berikutnya.</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if ($trashed)
                        <button type="submit" form="devices-bulk-restore-form" data-bulk-submit disabled class="btn-secondary !py-1.5 text-xs">Pulihkan terpilih</button>
                    @else
                        <button type="submit" form="devices-bulk-delete-form" data-bulk-submit disabled class="btn-secondary !py-1.5 text-xs text-brick-600">Hapus terpilih</button>
                    @endif
                </div>
            </div>

            @if ($devices->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-ink-soft">{{ $trashed ? 'Tidak ada perangkat terhapus.' : 'Tidak ada perangkat yang cocok.' }}</p>
                    <p class="mt-1 text-xs text-ink-faint">Coba ubah kata kunci atau filter status.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="w-8 px-4 py-2.5"></th>
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
                                        <input type="checkbox" name="ids[]" value="{{ $device->id }}"
                                            form="{{ $trashed ? 'devices-bulk-restore-form' : 'devices-bulk-delete-form' }}"
                                            data-bulk-checkbox class="rounded border-line text-navy-700 focus:ring-navy-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-navy-900">{{ $device->label ?? $device->hostname }}</p>
                                        <p class="font-mono text-[11px] text-ink-faint">{{ $device->hostname }}{{ $device->location_label ? ' · '.$device->location_label : '' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($trashed)
                                            <span class="badge-danger">Terhapus</span>
                                        @else
                                            <x-status-chip :status="$status->value" />
                                        @endif
                                    </td>
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
                                        <p class="font-mono">
                                            {{ $device->agent_version ?? '—' }}
                                            @if ($device->agent_version && $device->agent_version !== $latestVersion)
                                                <span class="badge-warn ml-1 !px-1.5 !py-0 text-[10px]" title="Rilis terbaru: {{ $latestVersion }}">lama</span>
                                            @endif
                                        </p>
                                        <p class="text-[11px] text-ink-faint">{{ $device->windows_version ? \Illuminate\Support\Str::limit($device->windows_version, 30) : '—' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-ink-soft">
                                        {{ $device->last_seen_at?->diffForHumans(short: true) ?? '—' }}
                                        @if ($trashed)
                                            <p class="text-[11px] text-brick-600">Dihapus {{ $device->deleted_at?->diffForHumans(short: true) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @unless ($trashed)
                                                <a href="{{ route('devices.show', $device) }}" class="btn-secondary !py-1 text-xs">Detail</a>
                                            @endunless
                                            @if ($trashed)
                                                <form method="POST" action="{{ route('devices.restore', $device) }}" data-confirm="Pulihkan perangkat {{ $device->hostname }}? Token lama akan berlaku kembali.">
                                                    @csrf
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs text-moss-700" title="Pulihkan">
                                                        <x-icon name="refresh" size="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('devices.destroy', $device) }}" data-confirm="Hapus perangkat {{ $device->hostname }}? Kiosk akan menghapus dirinya sendiri saat boot berikutnya.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-secondary !px-2 !py-1 text-xs text-brick-600" title="Hapus">
                                                        <x-icon name="trash" size="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-confirm]');

            if (form && ! confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });

        (() => {
            const boxes = () => Array.from(document.querySelectorAll('[data-bulk-checkbox]'));

            const updateBulkState = () => {
                const count = boxes().filter((box) => box.checked).length;

                document.querySelectorAll('[data-bulk-count]').forEach((el) => { el.textContent = count; });
                document.querySelectorAll('[data-bulk-submit]').forEach((el) => { el.disabled = count === 0; });
            };

            document.addEventListener('change', (event) => {
                if (event.target.matches('[data-bulk-select-all]')) {
                    boxes().forEach((box) => { box.checked = event.target.checked; });
                }

                if (event.target.matches('[data-bulk-select-all], [data-bulk-checkbox]')) {
                    updateBulkState();
                }
            });

            updateBulkState();
        })();
    </script>
</x-dashboard-layout>
