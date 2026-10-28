<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeReportManagement
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $report = $request->report;
        abort_unless($report->canViewInManagement($request->user()), 404);
        if (! $request->isMethod('GET') || $request->routeIs('*.edit', '*.map')) {
            abort_unless($report->canManage($request->user()), 403, 'No autorizado');
        }
        if ($request->routeIs('*.configuration', '*.delete.form')) {
            abort_unless($report->canDelete($request->user()), 403);
        }
        if ($request->routeIs('*.comments', '*.comments.download', '*.testimonies', '*.testimonies.download')) {
            abort_unless($report->isApproved(), 404);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
