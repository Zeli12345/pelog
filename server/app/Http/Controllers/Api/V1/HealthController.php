<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok([
            'status' => 'healthy',
            'app' => config('app.name'),
        ]);
    }

    public function version(): JsonResponse
    {
        $hash = Setting::getValue('app_installer_sha256');
        $hasInstaller = is_string($hash) && $hash !== ''
            && Storage::disk('local')->exists((string) Setting::getValue('app_installer_file'));

        $latest = (string) Setting::getValue('app_version', config('pelog.version', '1.0.0'));

        return ApiResponse::ok([
            'app_version' => $latest,
            'latest_version' => $latest,
            'min_client_version' => (string) Setting::getValue('min_supported_version', config('pelog.min_client_version', '1.0.0')),
            'min_supported_version' => (string) Setting::getValue('min_supported_version', config('pelog.min_client_version', '1.0.0')),
            'mandatory' => (bool) Setting::getValue('app_update_mandatory', false),
            'update_url' => $hasInstaller ? url('/downloads/client-setup') : null,
            'installer_url' => $hasInstaller ? url('/downloads/client-setup') : null,
            'sha256' => $hasInstaller ? $hash : null,
            'size' => $hasInstaller ? (int) Setting::getValue('app_installer_size', 0) : null,
            'size_bytes' => $hasInstaller ? (int) Setting::getValue('app_installer_size', 0) : null,
            'check_hours' => (int) Setting::getValue('app_update_check_hours', 6),
            'published_at' => Setting::getValue('client_installer_uploaded_at'),
            'notes' => Setting::getValue('app_update_notes'),
        ]);
    }
}
