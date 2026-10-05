<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\ClientDownloadController;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const SETTING_TYPES = [
        'screenshot_enabled' => 'bool',
        'image_format' => 'string',
        'webp_quality' => 'int',
        'jpeg_quality' => 'int',
        'max_width' => 'int',
        'screenshot_minute' => 'int',
        'retention_days' => 'int',
        'disk_budget_gb' => 'int',
        'stale_session_minutes' => 'int',
        'bootstrap_refresh_minutes' => 'int',
        'device_online_window_seconds' => 'int',
        'idle_shutdown_minutes' => 'int',
        'single_active_session' => 'bool',
        'school_name' => 'string',
        'school_motto' => 'string',
    ];

    public function index(): View
    {
        return view('dashboard.settings.index', [
            'settings' => collect(self::SETTING_TYPES)
                ->mapWithKeys(fn (string $type, string $key) => [$key => Setting::getValue($key)])
                ->all(),
            'enrollmentEnabled' => is_string(Setting::getValue('enrollment_code_hash')),
            'lastEnrollmentCode' => session('enrollment_code'),
            'clientUpdate' => [
                'latest_version' => Setting::getValue('client_latest_version'),
                'sha256' => Setting::getValue('client_installer_sha256'),
                'size' => Setting::getValue('client_installer_size'),
                'notes' => Setting::getValue('client_update_notes'),
                'uploaded_at' => Setting::getValue('client_installer_uploaded_at'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'screenshot_enabled' => ['nullable', 'boolean'],
            'image_format' => ['required', 'in:webp_fallback_jpeg,jpeg_only'],
            'webp_quality' => ['required', 'integer', 'min:10', 'max:100'],
            'jpeg_quality' => ['required', 'integer', 'min:10', 'max:100'],
            'max_width' => ['required', 'integer', 'min:640', 'max:3840'],
            'screenshot_minute' => ['required', 'integer', 'min:0', 'max:240'],
            'retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'disk_budget_gb' => ['required', 'integer', 'min:1', 'max:2000'],
            'stale_session_minutes' => ['required', 'integer', 'min:5', 'max:180'],
            'bootstrap_refresh_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'device_online_window_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
            'idle_shutdown_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'single_active_session' => ['nullable', 'boolean'],
            'school_name' => ['required', 'string', 'max:100'],
            'school_motto' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['screenshot_enabled'] = $request->boolean('screenshot_enabled');
        $validated['single_active_session'] = $request->boolean('single_active_session');

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value);
        }

        Audit::log(
            action: 'settings_updated',
            metadata: ['keys' => array_keys($validated)],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('settings.index')->with('status', 'Pengaturan berhasil disimpan.');
    }

    public function generateEnrollmentCode(Request $request): RedirectResponse
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $part = function () use ($alphabet): string {
            $out = '';
            for ($i = 0; $i < 4; $i++) {
                $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            return $out;
        };

        $code = 'BLG-'.$part().'-'.$part();

        Setting::setValue('enrollment_code_hash', hash('sha256', $code));

        Audit::log(
            action: 'enrollment_code_generated',
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('settings.index')
            ->with('status', 'Kode enrollment baru dibuat. Salin sekarang — kode hanya ditampilkan sekali.')
            ->with('enrollment_code', $code);
    }

    // / <summary>Unggah installer client baru untuk pembaruan otomatis.</summary>
    public function uploadClientInstaller(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_latest_version' => ['required', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/'],
            'client_installer' => ['required', 'file', 'max:204800'], // maks 200 MB
            'client_update_notes' => ['nullable', 'string', 'max:200'],
        ]);

        $file = $request->file('client_installer');

        if (! in_array(strtolower((string) $file->getClientOriginalExtension()), ['exe', 'msi'], true)) {
            return back()->withErrors([
                'client_installer' => 'Berkas harus berupa installer Windows (.exe).',
            ]);
        }

        Storage::disk('local')->putFileAs(
            'client',
            $file,
            'PELOG_Setup.exe',
        );

        $absolutePath = Storage::disk('local')->path(ClientDownloadController::INSTALLER_PATH);

        // Sekaligus publikasikan sebagai rilis auto-update. Tanpa langkah ini
        // installer hanya tersedia untuk unduhan manual (penyebab umum keluhan
        // "pembaruan otomatis tidak jalan" karena agen kiosk membaca app_*).
        $releaseTarget = 'releases/PELOG_Setup_'.$data['client_latest_version'].'.exe';
        Storage::disk('local')->makeDirectory('releases');
        Storage::disk('local')->copy(ClientDownloadController::INSTALLER_PATH, $releaseTarget);
        $releasePath = Storage::disk('local')->path($releaseTarget);

        Setting::setValue('client_latest_version', $data['client_latest_version']);
        Setting::setValue('client_installer_sha256', hash_file('sha256', $absolutePath));
        Setting::setValue('client_installer_size', filesize($absolutePath));
        Setting::setValue('client_update_notes', $data['client_update_notes'] ?? null);
        Setting::setValue('client_installer_uploaded_at', now()->toIso8601String());

        Setting::setValue('app_version', $data['client_latest_version']);
        Setting::setValue('app_installer_file', $releaseTarget);
        Setting::setValue('app_installer_sha256', hash_file('sha256', $releasePath));
        Setting::setValue('app_installer_size', filesize($releasePath));
        Setting::setValue('app_update_notes', $data['client_update_notes'] ?? null);
        Setting::setValue('app_updater_enabled', true);

        Audit::log(
            action: 'client_installer_uploaded',
            metadata: [
                'version' => $data['client_latest_version'],
                'size' => filesize($absolutePath),
                'release' => $releaseTarget,
            ],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()->route('settings.index')
            ->with('status', 'Installer client v'.$data['client_latest_version'].
                ' tersimpan dan dipublikasikan sebagai rilis auto-update. '.
                'Laptop akan memperbarui otomatis (agen cek tiap jam / saat boot).');
    }
}
