<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Screenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScreenshotController extends Controller
{
    public function index(Request $request): View
    {
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $filters = [
            'from' => $from,
            'to' => $to,
            'device_id' => (string) $request->query('device_id', ''),
            'q' => trim((string) $request->query('q', '')),
            'sort' => (string) $request->query('sort', 'latest'),
            'kind' => (string) $request->query('kind', ''),
        ];

        if (! in_array($filters['kind'], ['screen', 'webcam'], true)) {
            $filters['kind'] = '';
        }

        $perPage = (int) $request->query('per_page');
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        // Input tanggal dari dashboard adalah tanggal WITA. Kolom captured_at
        // disimpan dalam UTC, jadi batas hari harus dikonversi agar capture
        // dini hari (00:00–07:59 WITA) tidak hilang dari filter.
        $fromUtc = $from !== '' ? Carbon::parse($from, 'Asia/Makassar')->startOfDay()->utc() : null;
        $toUtc = $to !== '' ? Carbon::parse($to, 'Asia/Makassar')->endOfDay()->utc() : null;

        $base = Screenshot::query()
            ->when($fromUtc !== null, fn ($query) => $query->where('captured_at', '>=', $fromUtc))
            ->when($toUtc !== null, fn ($query) => $query->where('captured_at', '<=', $toUtc))
            ->when($filters['device_id'] !== '', fn ($query) => $query->whereHas(
                'usageSession',
                fn ($session) => $session->where('device_id', (int) $filters['device_id'])
            ))
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $search = $filters['q'];

                $query->where(function ($outer) use ($search) {
                    $outer->whereHas('usageSession', function ($session) use ($search) {
                        $session->where('usage_purpose', 'like', "%{$search}%")
                            ->orWhereHas('student', function ($student) use ($search) {
                                $student->where('name', 'like', "%{$search}%")
                                    ->orWhere('nisn', 'like', "%{$search}%");
                            })
                            ->orWhereHas('staff', function ($staff) use ($search) {
                                $staff->where('name', 'like', "%{$search}%")
                                    ->orWhere('nip_id', 'like', "%{$search}%");
                            })
                            ->orWhereHas('device', function ($device) use ($search) {
                                $device->where('hostname', 'like', "%{$search}%")
                                    ->orWhere('label', 'like', "%{$search}%");
                            });
                    });
                });
            });

        $counts = [
            'all' => (clone $base)->count(),
            'screen' => (clone $base)->where('kind', 'screen')->count(),
            'webcam' => (clone $base)->where('kind', 'webcam')->count(),
        ];

        $screenshots = (clone $base)
            ->select('screenshots.*')
            ->with(['usageSession.device', 'usageSession.student', 'usageSession.staff'])
            ->when($filters['kind'] !== '', fn ($query) => $query->where('kind', $filters['kind']));

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
            'counts' => $counts,
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
