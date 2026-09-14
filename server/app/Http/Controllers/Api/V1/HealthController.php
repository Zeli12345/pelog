<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
        return ApiResponse::ok([
            'app_version' => config('balilog.version', '1.0.0'),
            'min_client_version' => config('balilog.min_client_version', '1.0.0'),
        ]);
    }
}
