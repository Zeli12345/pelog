<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppUpdateController extends Controller
{
    public function latest(): JsonResponse
    {
        $enabled = (bool) Setting::getValue('app_updater_enabled', true);

        if (! $enabled) {
            return ApiResponse::ok([
                'available' => false,
                'version' => Setting::getValue('app_version', config('pelog.version')),
            ]);
        }

        $file = Setting::getValue('app_installer_file');

        if (! is_string($file) || $file === '' || ! Storage::disk('local')->exists($file)) {
            return ApiResponse::ok([
                'available' => false,
                'version' => Setting::getValue('app_version', config('pelog.version')),
            ]);
        }

        return ApiResponse::ok([
            'available' => true,
            'version' => Setting::getValue('app_version', config('pelog.version')),
            'latest_version' => Setting::getValue('app_version', config('pelog.version')),
            'min_supported_version' => (string) Setting::getValue('min_supported_version', config('pelog.min_client_version', '1.0.0')),
            'sha256' => Setting::getValue('app_installer_sha256'),
            'size_bytes' => (int) Setting::getValue('app_installer_size', 0),
            'mandatory' => (bool) Setting::getValue('app_update_mandatory', false),
            'check_hours' => (int) Setting::getValue('app_update_check_hours', 6),
            'published_at' => Setting::getValue('client_installer_uploaded_at'),
            'notes' => Setting::getValue('app_update_notes', ''),
            'url' => url('/api/v1/app/installer'),
            'installer_url' => url('/api/v1/app/installer'),
        ]);
    }

    public function download(): BinaryFileResponse|JsonResponse
    {
        $file = Setting::getValue('app_installer_file');

        if (! is_string($file) || $file === '' || ! Storage::disk('local')->exists($file)) {
            return ApiResponse::error('release_not_found', 'Belum ada rilis installer di server.', 404);
        }

        return response()->file(Storage::disk('local')->path($file), [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
