<?php

namespace App\Support;

use App\Models\Setting;

class DeviceConfig
{
    /**
     * Konfigurasi yang dikirim ke aplikasi client (kiosk).
     *
     * @return array<string, mixed>
     */
    public static function forClient(): array
    {
        return [
            'school_name' => Setting::getValue('school_name', 'SMK Negeri 1 Mas Ubud'),
            'school_motto' => Setting::getValue('school_motto', 'Kriya Kencana Raksa'),
            'screenshot_enabled' => (bool) Setting::getValue('screenshot_enabled', true),
            'image_format' => Setting::getValue('image_format', 'webp_fallback_jpeg'),
            'webp_quality' => (int) Setting::getValue('webp_quality', 70),
            'jpeg_quality' => (int) Setting::getValue('jpeg_quality', 60),
            'max_width' => (int) Setting::getValue('max_width', 1280),
            'screenshot_minute' => (int) Setting::getValue('screenshot_minute', 30),
            'stale_session_minutes' => (int) Setting::getValue('stale_session_minutes', 15),
            'bootstrap_refresh_minutes' => (int) Setting::getValue('bootstrap_refresh_minutes', 15),
            'device_online_window_seconds' => (int) Setting::getValue('device_online_window_seconds', 300),
            'idle_shutdown_minutes' => (int) Setting::getValue('idle_shutdown_minutes', 90),
        ];
    }
}
