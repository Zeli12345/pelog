<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\StudentPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function show(Request $request, string $nisn): JsonResponse
    {
        $student = Student::query()->where('nisn', $nisn)->first();

        if ($student === null) {
            Audit::log(
                action: 'nisn_lookup_failed',
                metadata: ['nisn' => $nisn],
                actorType: 'device',
                actorId: $request->attributes->get('pelog_device')?->id,
                request: $request,
            );

            return ApiResponse::error('student_not_found', 'NISN tidak terdaftar.', 404);
        }

        if (! $student->is_active) {
            return ApiResponse::error('student_inactive', 'Akun siswa dinonaktifkan. Lapor guru/IT.', 403);
        }

        return ApiResponse::ok(StudentPayload::forDevice($student));
    }
}
