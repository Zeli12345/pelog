<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Screenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScreenshotController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $screenshots = Screenshot::query()
            ->with(['usageSession.device', 'usageSession.student', 'usageSession.staff'])
            ->when($from, fn ($query, $value) => $query->whereDate('captured_at', '>=', $value))
            ->when($to, fn ($query, $value) => $query->whereDate('captured_at', '<=', $value))
            ->orderByDesc('captured_at')
            ->paginate(24)
            ->withQueryString();

        return view('dashboard.screenshots.index', [
            'screenshots' => $screenshots,
            'from' => $from,
            'to' => $to,
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
