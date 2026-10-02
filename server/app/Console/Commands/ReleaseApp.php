<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReleaseApp extends Command
{
    protected $signature = 'pelog:release-app
        {installer : Path ke installer (PELOG_Setup.exe)}
        {version : Versi rilis, mis. 1.1.0}
        {--notes= : Catatan rilis singkat}
        {--mandatory : Tandai sebagai update wajib}';

    protected $description = 'Mempublikasikan rilis installer client agar dapat diunduh & dipasang otomatis oleh kiosk';

    public function handle(): int
    {
        $source = (string) $this->argument('installer');
        $version = (string) $this->argument('version');

        if (! is_file($source)) {
            $this->error('Installer tidak ditemukan: '.$source);

            return self::FAILURE;
        }

        if (! preg_match('/^\d+(\.\d+){1,3}$/', $version)) {
            $this->error('Format versi tidak valid. Contoh: 1.1.0');

            return self::FAILURE;
        }

        $target = 'releases/PELOG_Setup_'.$version.'.exe';

        $stream = fopen($source, 'rb');

        if ($stream === false) {
            $this->error('Installer tidak dapat dibaca: '.$source);

            return self::FAILURE;
        }

        try {
            $stored = Storage::disk('local')->put($target, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            $this->error('Gagal menyimpan installer ke storage: '.$target);

            return self::FAILURE;
        }

        $fullPath = Storage::disk('local')->path($target);

        $hash = hash_file('sha256', $fullPath);
        $size = @filesize($fullPath);

        if ($hash === false || $size === false) {
            $this->error('Gagal menghitung hash/ukuran installer: '.$target);

            return self::FAILURE;
        }

        Setting::setValue('app_version', $version);
        Setting::setValue('app_installer_file', $target);
        Setting::setValue('app_installer_sha256', $hash);
        Setting::setValue('app_installer_size', $size);
        Setting::setValue('app_update_notes', (string) $this->option('notes'));
        Setting::setValue('app_update_mandatory', (bool) $this->option('mandatory'));
        Setting::setValue('app_updater_enabled', true);

        $this->newLine();
        $this->info("  Rilis v{$version} dipublikasikan.");
        $this->line('  File    : '.$target);
        $this->line('  SHA-256 : '.$hash);
        $this->line('  Ukuran  : '.number_format($size / 1048576, 1).' MB');
        $this->newLine();
        $this->warn('  Kiosk akan memeriksa & memasang pembaruan ini otomatis (cek tiap '.Setting::getValue('app_update_check_hours', 6).' jam).');

        return self::SUCCESS;
    }
}
