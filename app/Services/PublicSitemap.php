<?php

namespace App\Services;

use App\Event;
use App\Goal;
use App\Objective;
use App\Report;
use App\Services\Goals\GoalAudience;
use Illuminate\Database\Eloquent\Builder;
use LengthException;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class PublicSitemap
{
    public const URLS_PER_FILE = 1000;

    public const MAX_XML_BYTES = 50 * 1024 * 1024;

    public function render(): string
    {
        $counts = ['pages' => count(SearchIndexing::PUBLIC_PAGE_ROUTES)];
        foreach ($this->queries() as $section => $query) {
            $counts[$section] = $query->count();
        }

        if (array_sum($counts) <= self::URLS_PER_FILE) {
            $sitemap = Sitemap::create();
            foreach (array_keys($counts) as $section) {
                $sitemap->add($this->entries($section, 1));
            }

            return $this->checkSize($sitemap->render());
        }

        $sitemaps = [];
        foreach ($counts as $section => $count) {
            for ($page = 1; $page <= (int) ceil($count / self::URLS_PER_FILE); $page++) {
                $sitemaps[] = route('sitemap.part', ['section' => $section, 'page' => $page]);
            }
        }
        if (count($sitemaps) > 50000) {
            throw new LengthException('The sitemap index exceeds the protocol limit of 50,000 files.');
        }

        // Spatie's index tags default lastmod to now; omit it rather than invent modification dates.
        return $this->checkSize(view('partials.sitemap-index', ['sitemaps' => $sitemaps])->render());
    }

    public function renderPart(string $section, int $page): string
    {
        abort_unless($page >= 1, 404);
        $sitemap = Sitemap::create()->add($this->entries($section, $page));
        abort_if($sitemap->getTags() === [], 404);

        return $this->checkSize($sitemap->render());
    }

    /** @return array<string, Builder> */
    private function queries(): array
    {
        $publicGoals = app(GoalAudience::class)->publicQuery();

        return [
            'objectives' => Objective::query()->where('hidden', false),
            'goals' => Goal::query()->visibleIn($publicGoals),
            'reports' => Report::query()->approved()->visibleIn($publicGoals),
            'events' => Event::query(),
        ];
    }

    /** @return iterable<Url> */
    private function entries(string $section, int $page): iterable
    {
        if ($section === 'pages') {
            if ($page === 1) {
                foreach (SearchIndexing::PUBLIC_PAGE_ROUTES as $name) {
                    yield Url::create(route($name));
                }
            }

            return;
        }

        $query = $this->queries()[$section] ?? null;
        abort_if($query === null, 404);
        [$route, $parameter] = match ($section) {
            'objectives' => ['objectives.index', 'objectiveId'],
            'goals' => ['goals.index', 'goalId'],
            'reports' => ['reports.index', 'reportId'],
            'events' => ['events.index', 'eventId'],
        };

        foreach ($query->select(['id', 'updated_at'])->orderBy('id')->forPage($page, self::URLS_PER_FILE)->get() as $model) {
            $url = Url::create(route($route, [$parameter => $model->id]));
            if ($model->updated_at !== null) {
                $url->setLastModificationDate($model->updated_at);
            }

            yield $url;
        }
    }

    private function checkSize(string $xml): string
    {
        if (strlen($xml) > self::MAX_XML_BYTES) {
            throw new LengthException('The sitemap exceeds the protocol limit of 50 MB.');
        }

        return $xml;
    }
}
