<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIt
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdminIt()) {
            abort(403, 'Hanya Admin IT yang boleh melakukan aksi ini.');
        }

        return $next($request);
    }
}
