<x-dashboard-layout title="Pengaturan" subtitle="Konfigurasi sistem & kebijakan">
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                {{-- Identitas --}}
                <section class="card p-5">
                    <h2 class="text-sm font-semibold text-ink">Identitas Sekolah</h2>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <div>
                            <label class="label" for="school_name">Nama Sekolah</label>
                            <input type="text" id="school_name" name="school_name" value="{{ old('school_name', $settings['school_name']) }}" class="input text-sm" required>
                        </div>
                        <div>
                            <label class="label" for="school_motto">Motto</label>
                            <input type="text" id="school_motto" name="school_motto" value="{{ old('school_motto', $settings['school_motto']) }}" class="input text-sm">
                        </div>
                    </div>
                </section>

                {{-- Screenshot --}}
                <section class="card p-5">
                    <h2 class="text-sm font-semibold text-ink">Screenshot</h2>
                    <p class="text-xs text-ink-faint">Diambil otomatis 1× per sesi siswa, lalu diunggah ke server.</p>
                    <div class="mt-4 space-y-4">
                        <label class="flex items-center gap-2 text-sm text-ink-soft">
                            <input type="checkbox" name="screenshot_enabled" value="1" @checked(old('screenshot_enabled', $settings['screenshot_enabled'])) class="rounded border-line text-navy-700 focus:ring-navy-500">
                            Aktifkan screenshot otomatis
                        </label>

                        <div class="grid gap-3 lg:grid-cols-3">
                            <div>
                                <label class="label" for="image_format">Format</label>
                                <select id="image_format" name="image_format" class="input text-sm">
                                    <option value="webp_fallback_jpeg" @selected(old('image_format', $settings['image_format']) === 'webp_fallback_jpeg')>WebP (fallback JPEG)</option>
                                    <option value="jpeg_only" @selected(old('image_format', $settings['image_format']) === 'jpeg_only')>JPEG saja</option>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="webp_quality">Kualitas WebP</label>
                                <input type="number" id="webp_quality" name="webp_quality" min="10" max="100" value="{{ old('webp_quality', $settings['webp_quality']) }}" class="input text-sm">
                            </div>
                            <div>
                                <label class="label" for="jpeg_quality">Kualitas JPEG</label>
                                <input type="number" id="jpeg_quality" name="jpeg_quality" min="10" max="100" value="{{ old('jpeg_quality', $settings['jpeg_quality']) }}" class="input text-sm">
                            </div>
                            <div>
                                <label class="label" for="max_width">Lebar Maks (px)</label>
                                <input type="number" id="max_width" name="max_width" min="640" max="3840" value="{{ old('max_width', $settings['max_width']) }}" class="input text-sm">
                            </div>
                            <div>
                                <label class="label" for="screenshot_minute">Menit ke-</label>
                                <input type="number" id="screenshot_minute" name="screenshot_minute" min="1" max="240" value="{{ old('screenshot_minute', $settings['screenshot_minute']) }}" class="input text-sm">
                            </div>
                            <div>
                                <label class="label" for="retention_days">Retensi (hari)</label>
                                <input type="number" id="retention_days" name="retention_days" min="0" max="3650" value="{{ old('retention_days', $settings['retention_days']) }}" class="input text-sm">
                                <p class="mt-1 text-[11px] text-ink-faint">0 = simpan permanen</p>
                            </div>
                        </div>

                        <div class="grid gap-3 lg:grid-cols-3">
                            <div>
                                <label class="label" for="disk_budget_gb">Budget Disk (GB)</label>
                                <input type="number" id="disk_budget_gb" name="disk_budget_gb" min="1" max="2000" value="{{ old('disk_budget_gb', $settings['disk_budget_gb']) }}" class="input text-sm">
                                <p class="mt-1 text-[11px] text-ink-faint">Alert jika pemakaian screenshot melewati angka ini.</p>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Sesi & Sinkronisasi --}}
                <section class="card p-5">
                    <h2 class="text-sm font-semibold text-ink">Sesi &amp; Sinkronisasi</h2>
                    <div class="mt-4 grid gap-3 lg:grid-cols-3">
                        <div>
                            <label class="label" for="stale_session_minutes">Sesi menggantung (menit)</label>
                            <input type="number" id="stale_session_minutes" name="stale_session_minutes" min="5" max="180" value="{{ old('stale_session_minutes', $settings['stale_session_minutes']) }}" class="input text-sm">
                        </div>
                        <div>
                            <label class="label" for="bootstrap_refresh_minutes">Refresh data client (menit)</label>
                            <input type="number" id="bootstrap_refresh_minutes" name="bootstrap_refresh_minutes" min="1" max="240" value="{{ old('bootstrap_refresh_minutes', $settings['bootstrap_refresh_minutes']) }}" class="input text-sm">
                        </div>
                        <div>
                            <label class="label" for="device_online_window_seconds">Jendela online (detik)</label>
                            <input type="number" id="device_online_window_seconds" name="device_online_window_seconds" min="60" max="3600" value="{{ old('device_online_window_seconds', $settings['device_online_window_seconds']) }}" class="input text-sm">
                        </div>
                        <div>
                            <label class="label" for="idle_shutdown_minutes">Matikan otomatis jika idle (menit)</label>
                            <input type="number" id="idle_shutdown_minutes" name="idle_shutdown_minutes" min="0" max="1440" value="{{ old('idle_shutdown_minutes', $settings['idle_shutdown_minutes']) }}" class="input text-sm">
                            <p class="mt-1 text-[11px] text-ink-faint">Tanpa input mouse/keyboard. 0 = nonaktif; default 90 menit.</p>
                        </div>
                    </div>

                    <label class="mt-4 flex items-center gap-2 text-sm text-ink-soft">
                        <input type="checkbox" name="single_active_session" value="1" @checked(old('single_active_session', $settings['single_active_session'])) class="rounded border-line text-navy-700 focus:ring-navy-500">
                        Batasi 1 sesi aktif per NISN
                    </label>
                </section>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn-primary">Simpan Pengaturan</button>
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            {{-- Installer Client --}}
            <section class="card p-5">
                <h2 class="text-sm font-semibold text-ink">Installer Client</h2>
                <p class="mt-1 text-xs text-ink-faint">Pasang di laptop baru — kode enrollment sudah terisi otomatis bila installer dibangun dari sini.</p>

                @if ($clientUpdate['sha256'])
                    <dl class="mt-3 space-y-1 text-xs text-ink-soft">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-faint">Versi</dt>
                            <dd class="font-semibold text-ink">{{ $clientUpdate['latest_version'] ?? '1.0.0' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-faint">Ukuran</dt>
                            <dd>{{ number_format(((int) ($clientUpdate['size'] ?? 0)) / 1048576, 1) }} MB</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-faint">SHA-256</dt>
                            <dd class="max-w-[150px] truncate font-mono" title="{{ $clientUpdate['sha256'] }}">{{ \Illuminate\Support\Str::limit($clientUpdate['sha256'], 20) }}</dd>
                        </div>
                        @if ($clientUpdate['uploaded_at'])
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-faint">Dipublikasikan</dt>
                                <dd>{{ \Illuminate\Support\Carbon::parse($clientUpdate['uploaded_at'])->timezone('Asia/Makassar')->translatedFormat('d M Y H:i') }}</dd>
                            </div>
                        @endif
                        @if ($clientUpdate['notes'])
                            <p class="pt-1 text-ink-faint">Catatan: {{ $clientUpdate['notes'] }}</p>
                        @endif
                    </dl>

                    <a href="{{ route('client.download') }}" class="btn-primary mt-3 w-full">
                        <x-icon name="download" size="h-4 w-4" />
                        Unduh Installer (PELOG_Setup.exe)
                    </a>
                @else
                    <p class="mt-3 rounded-md border border-gold-300 bg-gold-50 p-3 text-[11px] leading-relaxed text-gold-800">
                        Belum ada installer yang dipublikasikan. Unggah installer di bawah agar tombol unduh &amp; pembaruan otomatis aktif.
                    </p>
                @endif

                <form method="POST" action="{{ route('settings.client-installer') }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-line pt-4">
                    @csrf
                    <div>
                        <label class="label" for="client_latest_version">Versi installer</label>
                        <input type="text" id="client_latest_version" name="client_latest_version" value="{{ old('client_latest_version', $clientUpdate['latest_version'] ?? '1.0.0') }}" placeholder="1.0.0" class="input text-sm" required>
                        @error('client_latest_version')<p class="mt-1 text-[11px] text-brick-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="client_installer">Berkas installer (.exe, maks 200 MB)</label>
                        <input type="file" id="client_installer" name="client_installer" accept=".exe,.msi" class="input text-xs" required>
                        @error('client_installer')<p class="mt-1 text-[11px] text-brick-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="client_update_notes">Catatan rilis (opsional)</label>
                        <input type="text" id="client_update_notes" name="client_update_notes" value="{{ old('client_update_notes', $clientUpdate['notes']) }}" maxlength="200" class="input text-sm">
                    </div>
                    <button type="submit" class="btn-secondary w-full">
                        <x-icon name="upload" size="h-4 w-4" />
                        Unggah &amp; Publikasikan
                    </button>
                </form>
            </section>

            {{-- Enrollment --}}
            <section class="card p-5">
                <h2 class="text-sm font-semibold text-ink">Enrollment Perangkat</h2>
                @if ($enrollmentEnabled)
                    <p class="mt-2 flex items-center gap-2 text-xs text-moss-700">
                        <span class="h-2 w-2 rounded-full bg-moss-500"></span>
                        Enrollment aktif — kode sudah pernah dibuat.
                    </p>
                @else
                    <p class="mt-2 flex items-center gap-2 text-xs text-gold-700">
                        <span class="h-2 w-2 rounded-full bg-gold-500"></span>
                        Enrollment belum diaktifkan.
                    </p>
                @endif

                @if ($lastEnrollmentCode)
                    <div class="mt-3 rounded-md border border-gold-300 bg-gold-50 p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gold-700">Kode baru (hanya sekali tampil)</p>
                        <p class="mt-1 select-all font-mono text-lg font-bold tracking-wider text-gold-900">{{ $lastEnrollmentCode }}</p>
                        <p class="mt-1 text-[11px] text-gold-800">Masukkan kode ini di aplikasi kiosk saat pertama kali dijalankan.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.enrollment-code') }}" class="mt-3" onsubmit="return confirm('Buat kode enrollment baru? Kode lama tidak berlaku lagi.')">
                    @csrf
                    <button type="submit" class="btn-secondary w-full">
                        <x-icon name="key" size="h-4 w-4" />
                        Buat Kode Enrollment Baru
                    </button>
                </form>

                <p class="mt-3 text-[11px] leading-relaxed text-ink-faint">
                    Kode hanya ditampilkan sekali. Jika hilang, buat kode baru — perangkat yang sudah enrolled tidak terpengaruh.
                </p>
            </section>

        </aside>
    </div>
</x-dashboard-layout>
