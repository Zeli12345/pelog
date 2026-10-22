<x-dashboard-layout :title="'Perangkat — '.($device->label ?? $device->hostname)">
    <x-slot:actions>
        <a href="{{ route('devices.index') }}" class="btn-secondary !py-1.5 text-xs">
            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
            Kembali
        </a>
    </x-slot:actions>

    @php
        $isAdmin = auth()->user()->isAdmin();
        $statusLabels = ['open' => 'Terbuka', 'in_progress' => 'Proses', 'resolved' => 'Selesai'];
        $priorityLabels = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'critical' => 'Kritis'];
        $priorityClasses = ['low' => 'badge-muted', 'normal' => 'badge-muted', 'high' => 'badge-warn', 'critical' => 'badge-danger'];
        $categoryLabels = ['perbaikan' => 'Perbaikan', 'kerusakan' => 'Kerusakan', 'perawatan' => 'Perawatan', 'perangkat_lunak' => 'Perangkat Lunak', 'serah_terima' => 'Serah Terima', 'lainnya' => 'Lainnya'];
        $tabUrl = fn (string $tab) => route('devices.show', ['device' => $device, 'tab' => $tab]);
        $noteTime = fn ($value) => $value?->timezone('Asia/Makassar')->format('d/m/Y H:i');
    @endphp

    <div class="space-y-4" x-data="{
        noteOpen: false,
        edit: null,
        form: {},
        openCreate() {
            this.edit = null;
            this.form = { category: 'perbaikan', title: '', body: '', status: 'open', priority: 'normal', occurred_at: '', cost: '', vendor: '', tags: '', pinned: false };
            this.noteOpen = true;
        },
        openEdit(note) {
            this.edit = note;
            this.form = { ...note, occurred_at: note.occurred_at || '', pinned: !!note.pinned };
            this.noteOpen = true;
        }
    }">
        {{-- Header --}}
        <section class="card p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-navy-900 text-white">
                        <x-icon name="monitor" size="h-6 w-6" />
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-ink">{{ $device->label ?? $device->hostname }}</h2>
                            <x-status-chip :status="$status->value" />
                            @if ($openNotes->isNotEmpty())
                                <span class="badge-warn">{{ $openNotes->count() }} catatan terbuka</span>
                            @endif
                        </div>
                        <p class="font-mono text-xs text-ink-faint">
                            {{ $device->hostname }} · {{ $device->location?->name ?? $device->location_label ?? 'tanpa lokasi' }}
                        </p>
                    </div>
                </div>

                @if ($activeSession)
                    @php $start = $activeSession->started_at_server ?? $activeSession->started_at_client; @endphp
                    <div class="rounded-md border border-moss-200 bg-moss-50 px-4 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-moss-700">Sesi Berjalan</p>
                        <p class="text-sm font-semibold text-ink">{{ $activeSession->student?->name ?? $activeSession->staff?->name }}</p>
                        <p class="text-xs text-ink-soft">{{ $activeSession->student?->class ?? 'Guru / Pegawai' }} · {{ $activeSession->subject?->name ?? 'tanpa mapel' }}</p>
                        <p class="mt-1 font-mono text-xs text-moss-700">Durasi {{ $start ? \App\Support\Duration::human((int) $start->diffInMinutes(now())) : '—' }}</p>
                        @if ($isAdmin)
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

            @if ($pinnedNote)
                <div class="mt-4 rounded-md border border-gold-300 bg-gold-50 px-3 py-2 text-xs text-gold-900">
                    <span class="font-semibold">Catatan penting:</span> {{ $pinnedNote->title ?: \Illuminate\Support\Str::limit($pinnedNote->body, 140) }}
                </div>
            @endif

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
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">CPU</dt>
                    <dd class="mt-0.5 font-mono text-xs text-ink-soft">
                        {{ $device->cpu_usage_percent !== null ? $device->cpu_usage_percent.' %' : '—' }}
                        @if ($device->cpu_name)<span class="mt-0.5 block font-sans text-[11px] text-ink-faint">{{ $device->cpu_name }}</span>@endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">RAM</dt>
                    <dd class="mt-0.5 font-mono text-xs text-ink-soft">
                        @if ($device->ram_usage_percent !== null)
                            {{ $device->ram_usage_percent }}%{{ $device->ram_total_gb ? ' / '.$device->ram_total_gb.' GB' : '' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">GPU</dt>
                    <dd class="mt-0.5 text-ink-soft">{{ $device->gpu_name ?? '—' }}</dd>
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
                    <dt class="text-[11px] uppercase tracking-wide text-ink-faint">Auto-shutdown Idle</dt>
                    <dd class="mt-0.5 text-ink-soft">{{ ($idleShutdownMinutes ?? 0) > 0 ? $idleShutdownMinutes.' menit' : 'Nonaktif' }}</dd>
                </div>
            </dl>
        </section>

        {{-- Pengaturan perangkat --}}
        @if ($isAdmin)
            <section class="card p-4" x-data="{ newLocation: false }">
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
                        <div class="flex items-center justify-between gap-2">
                            <label class="label" for="location_id">Lokasi</label>
                            <button type="button" class="text-[11px] font-semibold text-navy-800 hover:underline" x-on:click="newLocation = !newLocation">+ Tambah</button>
                        </div>
                        <select id="location_id" name="location_id" class="input text-xs" x-show="!newLocation">
                            <option value="">Belum ditentukan</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('location_id', $device->location_id) === (string) $location->id)>
                                    {{ $location->name }}{{ $location->is_active ? '' : ' (disembunyikan)' }}
                                </option>
                            @endforeach
                        </select>
                        <input name="new_location" x-show="newLocation" x-cloak class="input text-xs" placeholder="Nama ruangan baru" maxlength="80" value="{{ old('new_location') }}">
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
                @if ($errors->any())
                    <div class="mt-3 rounded-md border border-brick-200 bg-brick-50 px-3 py-2 text-xs text-brick-700">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                <p class="mt-2 text-[11px] leading-relaxed text-ink-faint">
                    Status perawatan memblokir sesi baru. Menambah ruangan baru otomatis memakai nama yang sudah ada bila sama.
                </p>
            </section>
        @endif

        {{-- Tabs --}}
        <div class="flex flex-wrap gap-1 border-b border-line">
            @foreach ([['sessions', 'Riwayat Sesi'], ['notes', 'Catatan ('.$notes->total().')']] as [$key, $label])
                <a href="{{ $tabUrl($key) }}"
                   class="border-b-2 px-3 py-2 text-xs font-semibold {{ $tab === $key ? 'border-navy-900 text-navy-900' : 'border-transparent text-ink-faint hover:text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach
            @if ($isAdmin)
                <a href="{{ $tabUrl('activity') }}"
                   class="border-b-2 px-3 py-2 text-xs font-semibold {{ $tab === 'activity' ? 'border-navy-900 text-navy-900' : 'border-transparent text-ink-faint hover:text-ink' }}">
                    Aktivitas
                </a>
            @endif
        </div>

        @if ($tab === 'sessions')
            <section class="card overflow-hidden">
                <header class="border-b border-line px-4 py-3">
                    <h3 class="text-sm font-semibold text-ink">Riwayat 50 Sesi Terakhir</h3>
                </header>
                @if ($history->isEmpty())
                    <div class="px-4 py-12 text-center text-sm text-ink-faint">Belum ada sesi.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-sm">
                            <thead>
                                <tr class="table-head">
                                    <th class="px-4 py-2.5 text-left font-semibold">Waktu</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Pengguna</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Tujuan</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Durasi</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Penutupan</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Media</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line/70">
                                @foreach ($history as $session)
                                    <tr class="table-row">
                                        <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $session->started_at_server?->timezone('Asia/Makassar')->format('d/m/Y H:i') ?? '—' }} WITA</td>
                                        <td class="px-4 py-2.5 text-xs text-ink">{{ $session->student?->name ?? $session->staff?->name ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $session->usage_purpose }}<span class="block text-[11px] text-ink-faint">{{ $session->subject?->name }}</span></td>
                                        <td class="px-4 py-2.5 text-xs text-ink-soft">{{ \App\Support\Duration::human((int) $session->duration_minutes) }}</td>
                                        <td class="px-4 py-2.5 text-xs text-ink-soft">{{ $session->close_reason?->value ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-xs">
                                            @if ($session->screenshot)
                                                <a href="{{ route('screenshots.file', $session->screenshot) }}" target="_blank" class="font-semibold text-navy-800 hover:underline">Lihat</a>
                                                @if ($session->screenshots_count > 1)<span class="text-ink-faint"> ({{ $session->screenshots_count }}×)</span>@endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @elseif ($tab === 'notes')
            <section class="card p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-ink">Catatan & Riwayat Perbaikan</h3>
                        <p class="mt-0.5 text-[11px] text-ink-faint">Catat kerusakan, perbaikan, pemasangan software, serah-terima, dan hal penting lainnya.</p>
                    </div>
                    @if ($isAdmin)
                        <button type="button" class="btn-primary !py-1.5 text-xs" x-on:click="openCreate()">+ Tambah Catatan</button>
                    @endif
                </div>

                <form method="GET" action="{{ route('devices.show', $device) }}" class="mt-3 flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="notes">
                    <input type="text" name="note_q" value="{{ $noteSearch }}" placeholder="Cari catatan…" class="input !w-48 py-1.5 text-xs">
                    <select name="note_status" class="input !w-32 py-1.5 text-xs">
                        <option value="">Semua status</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($noteStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="note_category" class="input !w-36 py-1.5 text-xs">
                        <option value="">Semua kategori</option>
                        @foreach ($noteCategories as $category)
                            <option value="{{ $category }}" @selected($noteCategory === $category)>{{ $categoryLabels[$category] ?? $category }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-primary !py-1.5 text-xs">Terapkan</button>
                    <a href="{{ route('devices.show', ['device' => $device, 'tab' => 'notes']) }}" class="btn-secondary !py-1.5 text-xs">Reset</a>
                </form>

                <div class="mt-4 space-y-3">
                    @forelse ($notes as $note)
                        <article class="rounded-lg border border-line p-3 {{ $note->pinned ? 'border-gold-300 bg-gold-50/40' : '' }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="badge-muted">{{ $categoryLabels[$note->category] ?? $note->category }}</span>
                                    <span class="{{ $note->status === 'resolved' ? 'badge-ok' : ($note->status === 'in_progress' ? 'badge-warn' : 'badge-danger') }}">{{ $statusLabels[$note->status] ?? $note->status }}</span>
                                    <span class="{{ $priorityClasses[$note->priority] ?? 'badge-muted' }}">{{ $priorityLabels[$note->priority] ?? $note->priority }}</span>
                                    @if ($note->pinned)<span class="badge-warn">Disematkan</span>@endif
                                </div>
                                @if ($isAdmin)
                                    <div class="flex items-center gap-1">
                                        <button type="button" class="btn-secondary !px-2 !py-1 text-[10px]"
                                                x-on:click="openEdit({{ \Illuminate\Support\Js::from([
                                                    'id' => $note->id,
                                                    'category' => $note->category,
                                                    'title' => $note->title ?? '',
                                                    'body' => $note->body,
                                                    'status' => $note->status,
                                                    'priority' => $note->priority,
                                                    'occurred_at' => $note->occurred_at?->timezone('Asia/Makassar')->format('Y-m-d\TH:i') ?? '',
                                                    'cost' => $note->cost !== null ? (string) (int) $note->cost : '',
                                                    'vendor' => $note->vendor ?? '',
                                                    'tags' => implode(', ', $note->tags ?? []),
                                                    'pinned' => (bool) $note->pinned,
                                                    'usage_session_id' => $note->usage_session_id,
                                                ]) }})">Edit</button>
                                        <form method="POST" action="{{ route('devices.notes.destroy', [$device, $note]) }}" onsubmit="return confirm('Hapus catatan ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-secondary !px-2 !py-1 text-[10px] text-brick-600">Hapus</button>
                                        </form>
                                    </div>
                                @endif
                            </div>

                            <h4 class="mt-2 text-sm font-semibold text-ink">{{ $note->title ?: \Illuminate\Support\Str::limit($note->body, 80) }}</h4>
                            <p class="mt-1 whitespace-pre-line text-xs leading-relaxed text-ink-soft">{{ $note->body }}</p>

                            @if ($note->tags)
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach ($note->tags as $tag)
                                        <span class="rounded bg-paper px-1.5 py-0.5 font-mono text-[10px] text-ink-faint">#{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-ink-faint">
                                <span>{{ $noteTime($note->occurred_at ?? $note->created_at) }} WITA</span>
                                <span>oleh {{ $note->creator?->name ?? 'Sistem' }}</span>
                                @if ($note->vendor)<span>Vendor: {{ $note->vendor }}</span>@endif
                                @if ($note->cost !== null)<span>Biaya: Rp {{ number_format((float) $note->cost, 0, ',', '.') }}</span>@endif
                            </div>

                            @if ($note->attachments->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($note->attachments as $attachment)
                                        <div class="relative">
                                            <a href="{{ route('note-attachments.show', $attachment) }}" target="_blank" class="block">
                                                @if (str_starts_with((string) $attachment->mime, 'image/'))
                                                    <img src="{{ route('note-attachments.show', $attachment) }}" alt="{{ $attachment->original_name }}" class="h-16 w-20 rounded border border-line object-cover">
                                                @else
                                                    <span class="flex h-16 w-20 items-center justify-center rounded border border-line bg-paper text-[10px] text-ink-faint">PDF</span>
                                                @endif
                                            </a>
                                            @if ($isAdmin)
                                                <form method="POST" action="{{ route('devices.notes.attachments.destroy', [$device, $attachment]) }}" class="absolute -right-1 -top-1" onsubmit="return confirm('Hapus lampiran ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="flex h-4 w-4 items-center justify-center rounded-full bg-brick-600 text-[9px] text-white" title="Hapus lampiran">×</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-md border border-dashed border-line px-4 py-10 text-center text-sm text-ink-faint">
                            Belum ada catatan. Tambahkan catatan pertama untuk perangkat ini.
                        </div>
                    @endforelse
                </div>

                <div class="mt-3">{{ $notes->links() }}</div>
            </section>
        @else
            <section class="card overflow-hidden">
                <header class="border-b border-line px-4 py-3">
                    <h3 class="text-sm font-semibold text-ink">Aktivitas Perangkat</h3>
                    <p class="mt-0.5 text-[11px] text-ink-faint">Perubahan nama/lokasi, permintaan screenshot, dan tindakan admin.</p>
                </header>
                @if ($activity->isEmpty())
                    <div class="px-4 py-12 text-center text-sm text-ink-faint">Belum ada aktivitas.</div>
                @else
                    <ul class="divide-y divide-line/70">
                        @foreach ($activity as $log)
                            <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-2.5">
                                <div>
                                    <p class="text-xs font-semibold text-ink">{{ $log->action }}</p>
                                    <p class="text-[11px] text-ink-faint">{{ $log->actor_type ?? 'system' }}{{ $log->actor_id ? ' #'.$log->actor_id : '' }}</p>
                                </div>
                                <span class="font-mono text-[11px] text-ink-faint">{{ $log->created_at?->timezone('Asia/Makassar')->format('d/m/Y H:i:s') }} WITA</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- Modal catatan --}}
        @if ($isAdmin)
            <div x-show="noteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4" x-on:keydown.escape.window="noteOpen = false">
                <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-5 shadow-2xl" x-on:click.outside="noteOpen = false">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-ink" x-text="edit ? 'Edit Catatan' : 'Tambah Catatan'"></h2>
                            <p class="mt-0.5 text-[11px] text-ink-faint">Catatan fleksibel: kerusakan, perbaikan, software, serah-terima, biaya, dan lampiran.</p>
                        </div>
                        <button type="button" class="btn-secondary !p-2" x-on:click="noteOpen = false"><x-icon name="x" size="h-4 w-4" /></button>
                    </div>

                    <form method="POST" enctype="multipart/form-data"
                          x-bind:action="edit ? `/devices/{{ $device->id }}/notes/${edit.id}` : `/devices/{{ $device->id }}/notes`"
                          class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" x-bind:disabled="!edit">

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="label">Kategori</label>
                                <input name="category" list="note-categories" x-model="form.category" class="input text-sm" required maxlength="30">
                                <datalist id="note-categories">
                                    @foreach ($categoryLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div>
                                <label class="label">Status</label>
                                <select name="status" x-model="form.status" class="input text-sm">
                                    <option value="open">Terbuka</option>
                                    <option value="in_progress">Proses</option>
                                    <option value="resolved">Selesai</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Prioritas</label>
                                <select name="priority" x-model="form.priority" class="input text-sm">
                                    <option value="low">Rendah</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Tinggi</option>
                                    <option value="critical">Kritis</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Tanggal kejadian</label>
                                <input type="datetime-local" name="occurred_at" x-model="form.occurred_at" class="input text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="label">Judul (opsional)</label>
                            <input name="title" x-model="form.title" class="input text-sm" maxlength="120" placeholder="mis. LCD bergaris">
                        </div>
                        <div>
                            <label class="label">Isi catatan</label>
                            <textarea name="body" x-model="form.body" rows="4" class="input text-sm" required maxlength="5000" placeholder="Jelaskan kondisi, tindakan, atau informasi penting…"></textarea>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="label">Biaya (Rp)</label>
                                <input name="cost" x-model="form.cost" class="input text-sm" inputmode="numeric" placeholder="mis. 850000">
                            </div>
                            <div>
                                <label class="label">Vendor / tempat servis</label>
                                <input name="vendor" x-model="form.vendor" class="input text-sm" maxlength="120">
                            </div>
                        </div>

                        <div>
                            <label class="label">Tag (pisahkan koma)</label>
                            <input name="tags" x-model="form.tags" class="input text-sm" maxlength="255" placeholder="lcd, vendor, garansi">
                        </div>

                        <div>
                            <label class="label">Lampiran (maks. 5 file, 5 MB/file)</label>
                            <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="input text-xs">
                        </div>

                        <label class="flex items-center gap-2 text-sm text-ink-soft">
                            <input type="checkbox" name="pinned" value="1" x-model="form.pinned" class="rounded border-line text-navy-700">
                            Sematkan sebagai catatan penting
                        </label>

                        <div class="flex justify-end gap-2 border-t border-line pt-3">
                            <button type="button" class="btn-secondary" x-on:click="noteOpen = false">Batal</button>
                            <button type="submit" class="btn-primary" x-text="edit ? 'Simpan Perubahan' : 'Tambah Catatan'"></button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-dashboard-layout>
