<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientDownloadController extends Controller
{
    public const INSTALLER_PATH = 'client/BALI-LOG_Setup.exe';

    /**
     * Unduhan installer client untuk pembaruan otomatis.
     * Publik (rate-limited) karena diakses agen SYSTEM di laptop.
     */
    public function download(): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists(self::INSTALLER_PATH), 404);

        return response()->file(Storage::disk('local')->path(self::INSTALLER_PATH), [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="BALI-LOG_Setup.exe"',
        ]);
    }
}
