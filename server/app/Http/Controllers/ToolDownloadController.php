<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ToolDownloadController extends Controller
{
    public const REPAIR_TOOL_PATH = 'tools/PELOG-Revert.exe';

    /**
     * Unduhan alat perbaikan laptop (PELOG-Revert.exe) — anti laptop terkunci.
     * Khusus Admin (middleware auth + admin): hanya dipakai IT ketika sebuah
     * laptop bermasalah, sehingga tidak mengganggu tampilan dashboard lain.
     * Berkas harus diunggah manual ke storage/app/private/tools/ (terpisah
     * dari rilis installer karena bukan bagian kios).
     */
    public function download(): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists(self::REPAIR_TOOL_PATH), 404);

        return response()->file(Storage::disk('local')->path(self::REPAIR_TOOL_PATH), [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="PELOG-Revert.exe"',
        ]);
    }
}
