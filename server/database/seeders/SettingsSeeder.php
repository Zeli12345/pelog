<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Identitas
            'school_name' => 'SMK Negeri 1 Mas Ubud',
            'school_motto' => 'Kriya Kencana Raksa',

            // Screenshot
            'screenshot_enabled' => true,
            'image_format' => 'webp_fallback_jpeg',
            'webp_quality' => 70,
            'jpeg_quality' => 60,
            'max_width' => 1280,
            'retention_days' => 0,          // 0 = simpan permanen
            'disk_budget_gb' => 20,
            'screenshot_minute' => 30,      // ambil screenshot di menit ke-30

            // Sesi & sinkronisasi
            'stale_session_minutes' => 15,  // sesi tanpa heartbeat > X menit dianggap menggantung
            'bootstrap_refresh_minutes' => 15,
            'device_online_window_seconds' => 300,
            'single_active_session' => false,

            // Enrollment (hash diisi dari dashboard saat kode dibuat)
            'enrollment_code_hash' => null,

            // Auto-update client
            'app_updater_enabled' => true,
            'app_version' => '1.0.0',
            'app_installer_file' => null,
            'app_installer_sha256' => null,
            'app_installer_size' => 0,
            'app_update_notes' => null,
            'app_update_mandatory' => false,
            'app_update_check_hours' => 6,
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::query()->where('key', $key)->doesntExist()) {
                Setting::setValue($key, $value);
            }
        }
    }
}
