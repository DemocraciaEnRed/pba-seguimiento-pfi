<?php

namespace App\Http\Middleware;

use App\Services\SearchIndexing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSearchIndexing
{
    public function __construct(private SearchIndexing $searchIndexing)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $isHtml = str_starts_with((string) $response->headers->get('Content-Type'), 'text/html');

        $response->headers->set('X-Robots-Tag', $response->isSuccessful() && $isHtml
            ? $this->searchIndexing->directives($request)
            : 'noindex, follow');

        if ($request->routeIs('robots', 'sitemap', 'sitemap.part')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
