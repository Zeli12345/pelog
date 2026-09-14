<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\UsageSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'user_type' => $request->query('user_type'),
            'device_id' => $request->query('device_id'),
            'q' => trim((string) $request->query('q', '')),
        ];

        $sessions = UsageSession::query()
            ->with(['device', 'student', 'staff', 'subject', 'screenshot'])
            ->when($filters['from'], fn ($query, $from) => $query->whereDate('started_at_server', '>=', $from))
            ->when($filters['to'], fn ($query, $to) => $query->whereDate('started_at_server', '<=', $to))
            ->when($filters['user_type'], fn ($query, $type) => $query->where('user_type', $type))
            ->when($filters['device_id'], fn ($query, $deviceId) => $query->where('device_id', $deviceId))
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $search = $filters['q'];
                $query->where(function ($inner) use ($search) {
                    $inner->where('usage_purpose', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($student) use ($search) {
                            $student->where('name', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%");
                        })
                        ->orWhereHas('staff', fn ($staff) => $staff->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('started_at_server')
            ->orderByDesc('started_at_client')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.sessions.index', [
            'sessions' => $sessions,
            'filters' => $filters,
            'devices' => Device::query()->orderByRaw('label IS NULL, label')->orderBy('hostname')->get(),
            'userTypes' => UserType::cases(),
        ]);
    }
}
