<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ComprehensionLevel;
use App\Exports\SessionsExport;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\UsageSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $query = $this->baseQuery($filters);

        $summary = (clone $query)->reorder()->selectRaw('
                COUNT(*) as total_sessions,
                COALESCE(SUM(duration_minutes), 0) as total_minutes,
                SUM(CASE WHEN user_type = "student" THEN 1 ELSE 0 END) as student_sessions,
                SUM(CASE WHEN user_type = "staff" THEN 1 ELSE 0 END) as staff_sessions
            ')->first();

        $comprehension = (clone $query)
            ->whereNotNull('comprehension_level')
            ->selectRaw('comprehension_level, COUNT(*) as total')
            ->groupBy('comprehension_level')
            ->pluck('total', 'comprehension_level');

        $bySubject = (clone $query)
            ->join('subjects', 'subjects.id', '=', 'usage_sessions.subject_id')
            ->selectRaw('subjects.name as subject_name, COUNT(*) as total, COALESCE(SUM(usage_sessions.duration_minutes), 0) as total_minutes')
            ->groupBy('subjects.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $sessions = $query
            ->with(['device', 'student', 'staff', 'subject'])
            ->orderByDesc('started_at_server')
            ->orderByDesc('started_at_client')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.reports.index', [
            'filters' => $filters,
            'summary' => $summary,
            'comprehension' => $comprehension,
            'bySubject' => $bySubject,
            'sessions' => $sessions,
            'levels' => ComprehensionLevel::cases(),
            'devices' => Device::query()->orderByRaw('label IS NULL, label')->orderBy('hostname')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $format = (string) $request->query('format', 'csv');
        $filename = 'laporan-sesi-'.$filters['from'].'_'.$filters['to'];

        if ($format === 'xlsx') {
            return Excel::download(new SessionsExport($filters), $filename.'.xlsx');
        }

        $sessions = $this->baseQuery($filters)
            ->with(['device', 'student', 'staff', 'subject'])
            ->orderByDesc('started_at_server')
            ->orderByDesc('started_at_client')
            ->get();

        return response()->streamDownload(function () use ($sessions) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Tanggal', 'Perangkat', 'Tipe Pengguna', 'Nama', 'Kelas/Unit',
                'Mapel', 'Tujuan', 'Mulai', 'Selesai', 'Durasi (menit)',
                'Pemahaman', 'Refleksi', 'Status Tutup',
            ]);

            foreach ($sessions as $session) {
                fputcsv($output, [
                    ($session->started_at_server ?? $session->started_at_client)?->timezone('Asia/Makassar')->format('Y-m-d') ?? '-',
                    $session->device?->label ?? $session->device?->hostname ?? '-',
                    $session->user_type->label(),
                    $session->student?->name ?? $session->staff?->name ?? '-',
                    $session->student?->class ?? $session->staff?->role?->label() ?? '-',
                    $session->subject?->name ?? '-',
                    $session->usage_purpose,
                    ($session->started_at_server ?? $session->started_at_client)?->timezone('Asia/Makassar')->format('H:i') ?? '-',
                    $session->closed_at?->timezone('Asia/Makassar')->format('H:i') ?? '-',
                    $session->duration_minutes,
                    $session->comprehension_level?->label() ?? '-',
                    $session->student_feedback ?? '-',
                    $session->close_reason?->label() ?? '-',
                ]);
            }

            fclose($output);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, string|null>
     */
    private function filters(Request $request): array
    {
        return [
            'from' => (string) $request->query('from', now()->startOfMonth()->toDateString()),
            'to' => (string) $request->query('to', now()->toDateString()),
            'user_type' => $request->query('user_type'),
            'device_id' => $request->query('device_id'),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        return UsageSession::query()
            ->when($filters['from'], fn ($query, $from) => $query->whereDate('started_at_server', '>=', $from))
            ->when($filters['to'], fn ($query, $to) => $query->whereDate('started_at_server', '<=', $to))
            ->when($filters['user_type'], fn ($query, $type) => $query->where('user_type', $type))
            ->when($filters['device_id'], fn ($query, $deviceId) => $query->where('device_id', $deviceId))
            ->when(($filters['q'] ?? '') !== '', function ($query) use ($filters) {
                $search = $filters['q'];
                $query->where(function ($inner) use ($search) {
                    $inner->where('usage_purpose', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($student) use ($search) {
                            $student->where('name', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%");
                        })
                        ->orWhereHas('staff', fn ($staff) => $staff->where('name', 'like', "%{$search}%"));
                });
            });
    }
}
