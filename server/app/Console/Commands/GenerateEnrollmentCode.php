<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class GenerateEnrollmentCode extends Command
{
    protected $signature = 'balilog:enrollment-code';

    protected $description = 'Membuat kode enrollment perangkat BALI-LOG (hanya ditampilkan sekali)';

    public function handle(): int
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // tanpa karakter mirip (0/O, 1/I/L)
        $part = function () use ($alphabet): string {
            $out = '';
            for ($i = 0; $i < 4; $i++) {
                $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            return $out;
        };

        $code = 'BLG-'.$part().'-'.$part();

        Setting::setValue('enrollment_code_hash', hash('sha256', $code));

        $this->newLine();
        $this->info('  Kode enrollment baru: '.$code);
        $this->newLine();
        $this->warn('  Simpan kode ini sekarang - hanya ditampilkan sekali.');
        $this->line('  Masukkan ke installer (enrollment.txt) atau dialog enroll saat pertama kali aplikasi client berjalan.');
        $this->newLine();

        return self::SUCCESS;
    }
}
