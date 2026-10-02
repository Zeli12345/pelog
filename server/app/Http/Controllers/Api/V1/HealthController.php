<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

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
        $hasInstaller = is_string(Setting::getValue('client_installer_sha256'))
            && Setting::getValue('client_installer_sha256') !== '';

        return ApiResponse::ok([
            'app_version' => config('pelog.version', '1.0.0'),
            'min_client_version' => config('pelog.min_client_version', '1.0.0'),
            'latest_version' => Setting::getValue('client_latest_version', config('pelog.version', '1.0.0')),
            'update_url' => $hasInstaller ? url('/downloads/client-setup') : null,
            'sha256' => $hasInstaller ? Setting::getValue('client_installer_sha256') : null,
            'size' => $hasInstaller ? (int) Setting::getValue('client_installer_size', 0) : null,
            'notes' => Setting::getValue('client_update_notes'),
        ]);
    }
}
