<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Student;
use App\Services\PinHasher;
use App\Services\PinPolicy;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\StudentPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentPinController extends Controller
{
    public function store(Request $request, string $nisn): JsonResponse
    {
        $pinLength = (int) Setting::getValue('pin_length', 4);

        $data = $request->validate([
            'pin' => ['required', 'string', 'digits:'.$pinLength],
        ]);

        $student = Student::query()->where('nisn', $nisn)->first();

        if ($student === null) {
            return ApiResponse::error('student_not_found', 'NISN tidak terdaftar.', 404);
        }

        if (! $student->is_active) {
            return ApiResponse::error('student_inactive', 'Akun siswa dinonaktifkan.', 403);
        }

        if ($student->hasPin()) {
            return ApiResponse::error(
                'pin_already_set',
                'PIN sudah pernah dibuat. Masukkan PIN yang pernah di-set, atau minta admin melakukan reset.',
                409,
            );
        }

        if ((bool) Setting::getValue('pin_forbid_weak', true) && PinPolicy::isWeak($data['pin'])) {
            return ApiResponse::error(
                'pin_too_weak',
                'PIN terlalu mudah ditebak. Hindari angka berulang atau berurutan.',
                422,
            );
        }

        $hashed = PinHasher::make($data['pin']);

        $pinSet = DB::transaction(function () use ($student, $hashed) {
            $locked = Student::query()->whereKey($student->id)->lockForUpdate()->first();

            if ($locked === null || $locked->hasPin()) {
                return false;
            }

            $locked->forceFill([
                'pin_algo' => $hashed['algo'],
                'pin_salt' => $hashed['salt'],
                'pin_iterations' => $hashed['iterations'],
                'pin_hash' => $hashed['hash'],
                'pin_set_at' => now(),
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
            ])->save();

            return true;
        });

        if (! $pinSet) {
            return ApiResponse::error(
                'pin_already_set',
                'PIN sudah pernah dibuat. Masukkan PIN yang pernah di-set, atau minta admin melakukan reset.',
                409,
            );
        }

        Audit::log(
            action: 'pin_set',
            entityType: Student::class,
            entityId: $student->id,
            metadata: ['nisn' => $student->nisn],
            actorType: 'device',
            actorId: $request->attributes->get('balilog_device')?->id,
            request: $request,
        );

        return ApiResponse::ok(StudentPayload::forDevice($student->refresh()));
    }
}
