<?php

namespace App\Http\Controllers;

use App\Services\PublicSitemap;
use App\Services\SearchIndexing;
use Illuminate\Http\Response;

class SearchEngineController extends Controller
{
    public function __construct(private SearchIndexing $searchIndexing, private PublicSitemap $sitemap)
    {
    }

    public function sitemap(): Response
    {
        abort_unless($this->searchIndexing->enabled(), 404);

        return response($this->sitemap->render(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function sitemapPart(string $section, string $page): Response
    {
        abort_unless($this->searchIndexing->enabled(), 404);

        return response($this->sitemap->renderPart($section, (int) $page), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $content = "User-agent: *\nDisallow:\n";
        if ($this->searchIndexing->enabled()) {
            $content .= "\nSitemap: ".route('sitemap')."\n";
        }

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
