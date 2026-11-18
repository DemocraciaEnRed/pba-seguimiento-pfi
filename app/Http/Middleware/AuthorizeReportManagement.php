<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        abort_unless($request->user()->can('viewInManagement', $report), 404);
        if (! $request->isMethod('GET') || $request->routeIs('*.edit', '*.map')) {
            Gate::authorize('update', $report);
        }
        if ($request->routeIs('*.configuration', '*.delete.form')) {
            Gate::authorize('delete', $report);
        }
        if ($request->routeIs('*.comments', '*.comments.download', '*.testimonies', '*.testimonies.download')) {
            abort_unless($report->isApproved(), 404);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
