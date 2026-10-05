<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CloseReason;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\UsageSession;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
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
            ->filterByDateRange($filters['from'], $filters['to'])
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

    public function close(Request $request, UsageSession $session): RedirectResponse
    {
        if (! $session->isActive()) {
            return back()->with('status', 'Sesi sudah selesai.');
        }

        $closedAt = now();
        $startedAt = $session->started_at_server ?? $session->started_at_client ?? $session->created_at;

        $session->forceFill([
            'closed_at' => $closedAt,
            'close_reason' => CloseReason::Admin,
            'duration_minutes' => $startedAt !== null
                ? max(0, (int) $startedAt->diffInMinutes($closedAt))
                : 0,
        ])->save();

        $session->device?->forceFill(['status' => 'available'])->saveQuietly();

        Audit::log(
            action: 'session_closed_admin',
            entityType: UsageSession::class,
            entityId: $session->id,
            metadata: [
                'session_uuid' => $session->session_uuid,
                'device_id' => $session->device_id,
            ],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with('status', 'Sesi berhasil ditutup.');
    }
}
