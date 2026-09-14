<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        'pin_setup_requires_online' => 'bool',
        'pin_length' => 'int',
        'pin_forbid_weak' => 'bool',
        'pin_max_attempts' => 'int',
        'pin_lock_minutes' => 'int',
        'single_active_session' => 'bool',
        'pin_activation_required' => 'bool',
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
            'screenshot_minute' => ['required', 'integer', 'min:1', 'max:240'],
            'retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'disk_budget_gb' => ['required', 'integer', 'min:1', 'max:2000'],
            'stale_session_minutes' => ['required', 'integer', 'min:5', 'max:180'],
            'bootstrap_refresh_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'device_online_window_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
            'pin_setup_requires_online' => ['nullable', 'boolean'],
            'pin_length' => ['required', 'integer', 'min:4', 'max:6'],
            'pin_forbid_weak' => ['nullable', 'boolean'],
            'pin_max_attempts' => ['required', 'integer', 'min:3', 'max:10'],
            'pin_lock_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'single_active_session' => ['nullable', 'boolean'],
            'pin_activation_required' => ['nullable', 'boolean'],
            'school_name' => ['required', 'string', 'max:100'],
            'school_motto' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['screenshot_enabled'] = $request->boolean('screenshot_enabled');
        $validated['pin_setup_requires_online'] = $request->boolean('pin_setup_requires_online');
        $validated['pin_forbid_weak'] = $request->boolean('pin_forbid_weak');
        $validated['single_active_session'] = $request->boolean('single_active_session');
        $validated['pin_activation_required'] = $request->boolean('pin_activation_required');

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
}
