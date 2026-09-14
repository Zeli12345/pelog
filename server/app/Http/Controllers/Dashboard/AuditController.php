<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $action = (string) $request->query('action', '');
        $from = $request->query('from');
        $to = $request->query('to');

        $logs = AuditLog::query()
            ->when($action !== '', fn ($query) => $query->where('action', 'like', "%{$action}%"))
            ->when($from, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($to, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('dashboard.audit.index', [
            'logs' => $logs,
            'actions' => $actions,
            'action' => $action,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
