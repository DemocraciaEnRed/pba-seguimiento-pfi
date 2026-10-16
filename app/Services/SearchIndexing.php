<?php

namespace App\Services;

use Illuminate\Http\Request;

class SearchIndexing
{
    /** @var list<string> */
    public const PUBLIC_PAGE_ROUTES = [
        'home',
        'catalog',
        'objectives',
        'reports',
        'about.general',
        'about.faq',
        'about.legal',
        'events.upcoming',
        'events.past',
    ];

    public function enabled(): bool
    {
        return (bool) app_setting('app_search_indexing_enabled', false);
    }

    public function directives(Request $request): string
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return 'noindex, follow';
        }

        if ($request->routeIs('objectives', 'reports') && array_intersect(
            ['s', 'category', 'strategic_objective', 'type', 'objective', 'goal', 'date_range', 'sort', 'order_by'],
            array_keys($request->query()),
        ) !== []) {
            return 'noindex, follow';
        }

        if ($request->routeIs('objectives.index', 'goals.index', 'reports.index')) {
            $isPublic = $request->attributes->get('search_indexing_public') === true;
        } else {
            $isPublic = $request->routeIs(...[...self::PUBLIC_PAGE_ROUTES, 'events.index']);
        }

        return $isPublic && $this->enabled()
            ? 'index, follow'
            : 'noindex, follow';
    }
}
