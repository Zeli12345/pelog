<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Screenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScreenshotController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'device_id' => $request->query('device_id'),
            'q' => trim((string) $request->query('q', '')),
            'sort' => $request->query('sort', 'latest'),
        ];

        // Jumlah kartu per halaman (10/25/50/100); nilai lain kembali ke 25.
        $perPage = (int) $request->query('per_page');
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $screenshots = Screenshot::query()
            ->select('screenshots.*')
            ->with(['usageSession.device', 'usageSession.student', 'usageSession.staff'])
            ->when($filters['from'], fn ($query, $value) => $query->whereDate('screenshots.captured_at', '>=', $value))
            ->when($filters['to'], fn ($query, $value) => $query->whereDate('screenshots.captured_at', '<=', $value))
            ->when($filters['device_id'], function ($query, $value) {
                $query->whereHas('usageSession', fn ($session) => $session->where('device_id', $value));
            })
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $search = $filters['q'];

                $query->whereHas('usageSession', function ($session) use ($search) {
                    $session->where('usage_purpose', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($student) use ($search) {
                            $student->where('name', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%");
                        })
                        ->orWhereHas('staff', function ($staff) use ($search) {
                            $staff->where('name', 'like', "%{$search}%")
                                ->orWhere('nip_id', 'like', "%{$search}%");
                        });
                });
            });

        match ($filters['sort']) {
            'oldest' => $screenshots->orderBy('screenshots.captured_at'),
            'device' => $screenshots
                ->leftJoin('usage_sessions as filter_session', 'filter_session.id', '=', 'screenshots.usage_session_id')
                ->leftJoin('devices as filter_device', 'filter_device.id', '=', 'filter_session.device_id')
                ->orderByRaw('COALESCE(filter_device.label, filter_device.hostname) asc')
                ->orderByDesc('screenshots.captured_at'),
            'student' => $screenshots
                ->leftJoin('usage_sessions as filter_session', 'filter_session.id', '=', 'screenshots.usage_session_id')
                ->leftJoin('students as filter_student', 'filter_student.id', '=', 'filter_session.student_id')
                ->orderByRaw("COALESCE(filter_student.name, 'zzz') asc")
                ->orderByDesc('screenshots.captured_at'),
            default => $screenshots->orderByDesc('screenshots.captured_at'),
        };

        return view('dashboard.screenshots.index', [
            'screenshots' => $screenshots->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
            'filters' => $filters,
            'devices' => Device::query()->orderByRaw('label IS NULL, label')->orderBy('hostname')->get(),
        ]);
    }

    public function thumb(Screenshot $screenshot): BinaryFileResponse
    {
        $path = $screenshot->thumb_path ?? $screenshot->path;

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    public function file(Screenshot $screenshot): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($screenshot->path), 404);

        return response()->file(Storage::disk('local')->path($screenshot->path));
    }
}
