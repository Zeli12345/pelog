<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Support\ApiResponse;
use App\Support\DeviceConfig;
use App\Support\StudentPayload;
use Illuminate\Http\JsonResponse;

class BootstrapController extends Controller
{
    public function index(): JsonResponse
    {
        $students = Student::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Student $student) => StudentPayload::forDevice($student))
            ->values();

        $staff = StaffMember::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (StaffMember $staff) => [
                'nip_id' => $staff->nip_id,
                'name' => $staff->name,
                'role' => $staff->role->value,
                'updated_at' => $staff->updated_at?->toIso8601String(),
            ])
            ->values();

        $subjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
            ])
            ->values();

        return ApiResponse::ok([
            'students' => $students,
            'staff' => $staff,
            'subjects' => $subjects,
            'config' => DeviceConfig::forClient(),
        ]);
    }
}
