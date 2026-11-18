<?php

namespace App\Services;

use App\Event;
use App\Goal;
use App\Objective;
use App\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoMetadata
{
    /** @return array{title: string, description: string, url: ?string, image: string} */
    public function page(Request $request): array
    {
        [$title, $description] = match ($request->route()?->getName()) {
            'home' => ['', ''],
            'catalog' => ['El Plan', 'Recorré el plan completo: ejes, objetivos estratégicos, objetivos generales y sus metas.'],
            'objectives' => ['Objetivos', 'Conocé los objetivos del plan, sus metas y el avance de su cumplimiento.'],
            'reports' => ['Seguimiento', 'Novedades, avances e hitos que publican los equipos sobre cada objetivo y sus metas.'],
            'about.general' => ['Acerca de', 'Conocé la plataforma de seguimiento del plan y cómo consultar sus objetivos, metas y avances.'],
            'about.faq' => ['Ayuda y preguntas frecuentes', 'Encontrá respuestas a las preguntas frecuentes sobre la plataforma y el seguimiento del plan.'],
            'about.legal' => ['Información legal', 'Consultá la información legal y las condiciones de uso de la plataforma.'],
            'events.upcoming' => ['Próximas actividades', 'Conocé las próximas actividades del plan, sus fechas y cómo participar.'],
            'events.past' => ['Actividades pasadas', 'Consultá las actividades realizadas en el marco del plan.'],
            default => ['', ''],
        };

        $url = null;
        if ($request->routeIs(...SearchIndexing::PUBLIC_PAGE_ROUTES)) {
            $parameters = [];
            $page = $request->query('page');
            if ($request->routeIs('events.upcoming', 'events.past') && is_string($page) && ctype_digit($page) && (int) $page > 1) {
                $parameters['page'] = (int) $page;
            }
            $url = route($request->route()->getName(), $parameters);
        }

        return $this->build($title, $description, $url);
    }

    /** @return array{title: string, description: string, url: ?string, image: string} */
    public function objective(Objective $objective): array
    {
        return $this->build(
            $objective->title,
            $this->text($objective->content) ?: $objective->title,
            ! $objective->hidden ? route('objectives.index', ['objectiveId' => $objective->id]) : null,
            $objective->cover?->path,
        );
    }

    /** @return array{title: string, description: string, url: ?string, image: string} */
    public function goal(Goal $goal, Objective $objective): array
    {
        $description = $goal->title.'. ';
        if ($this->text($goal->indicator) !== '') {
            $description .= 'Indicador: '.$goal->indicator.'. ';
        }
        $description .= 'Objetivo: '.$objective->title.'.';

        return $this->build(
            $goal->title,
            $description,
            ! $objective->hidden && $goal->visibility === 'public'
                ? route('goals.index', ['goalId' => $goal->id])
                : null,
            $objective->cover?->path,
        );
    }

    /** @return array{title: string, description: string, url: ?string, image: string} */
    public function report(Report $report, Goal $goal, Objective $objective): array
    {
        $title = $report->title;
        if (! Str::contains(Str::lower($this->text($title)), Str::lower($this->text($goal->title)))) {
            $title .= ' - '.$goal->title;
        }

        return $this->build(
            $title,
            $this->text($report->content) ?: 'Reporte de la meta '.$goal->title.'. Objetivo: '.$objective->title.'.',
            $report->isApproved() && ! $objective->hidden && $goal->visibility === 'public'
                ? route('reports.index', ['reportId' => $report->id])
                : null,
            $report->photos->sortBy('id')->first()?->path,
        );
    }

    /** @return array{title: string, description: string, url: ?string, image: string} */
    public function event(Event $event): array
    {
        return $this->build(
            $event->title,
            $this->text($event->content) ?: $event->title,
            route('events.index', ['eventId' => $event->id]),
            $event->photos->sortBy('id')->first()?->path,
        );
    }

    public function imageUrl(?string $path = null): string
    {
        $path = trim($path ?? '');
        if ($path === '') {
            $path = trim(app_setting('app_social_image', '/social-sharer.png')) ?: '/social-sharer.png';
        }
        if (str_starts_with($path, '//')) {
            $path = parse_url(url('/'), PHP_URL_SCHEME).':'.$path;
        }

        return asset($path);
    }

    /** @return array{title: string, description: string, url: ?string, image: string} */
    private function build(string $title, string $description, ?string $url, ?string $image = null): array
    {
        $siteTitle = $this->text(app_setting('app_social_title')) ?: $this->text(config('app.name', 'Laravel'));
        $title = $this->text($title);
        $description = $this->text($description)
            ?: $this->text(app_setting('app_social_description'))
            ?: $siteTitle;

        return [
            'title' => $title === '' || $title === $siteTitle ? $siteTitle : $title.' - '.$siteTitle,
            'description' => Str::limit($description, 157, '...', preserveWords: true),
            'url' => $url,
            'image' => $this->imageUrl($image),
        ];
    }

    private function text(?string $value): string
    {
        $value = html_entity_decode($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<\/(?:p|div|li|h[1-6])\s*>|<br\b[^>]*>/i', ' ', $value);

        return Str::squish(strip_tags($value));
    }
}
