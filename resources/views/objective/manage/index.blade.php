@extends('objective.manage.master')

@section('panelContent')

<section>
  <h3 class="is-700">Resumen del objetivo</h3>
  <p class="lead">Estado de las metas y del seguimiento del objetivo general.</p>
  <div class="d-grid grid-cols-2 grid-cols-md-4 gap-2 mb-3">
    <x-stat label="Metas" :value="$stats['goals_total']" icon="fas fa-medal" tone="reached" :hint="$stats['goals_reached'].' alcanzadas'" :href="route('objectives.manage.goals', ['objectiveId' => $objective->id])" />
    <x-stat label="Reportes" :value="$stats['reports_total']" icon="far fa-file-lines" tone="primary" />
    <x-stat label="Último reporte" :value="$stats['last_report_date'] ? \Carbon\Carbon::parse($stats['last_report_date'])->translatedFormat('j M Y') : 'Sin reportes'" icon="far fa-calendar" tone="info" />
    <x-stat label="Comentarios" :value="$commentsCount" icon="far fa-comments" tone="secondary" :href="route('objectives.manage.comments', ['objectiveId' => $objective->id])" />
  </div>
  @if($stats['goals_total'] > 0)
  <div class="card border-light mb-3">
    <div class="card-body p-3">
      @include('objective.manage.partials.stacked-bar', [
        'title' => 'Estado declarado',
        'total' => $stats['goals_total'],
        'segments' => [
          ['label' => 'Alcanzadas', 'count' => $stats['goals_reached'], 'color' => 'reached'],
          ['label' => 'En progreso', 'count' => $stats['goals_ongoing'], 'color' => 'ongoing'],
          ['label' => 'No cumplidas', 'count' => $stats['goals_delayed'], 'color' => 'delayed'],
          ['label' => 'Inactivas', 'count' => $stats['goals_inactive'], 'color' => 'inactive'],
        ],
      ])
      @include('objective.manage.partials.stacked-bar', [
        'title' => 'Cumplimiento medido',
        'total' => $stats['traffic_lights']['measured'] + $stats['traffic_lights']['unmeasured'],
        'segments' => [
          ['label' => 'Cumplido', 'count' => $stats['traffic_lights']['green'], 'color' => 'success'],
          ['label' => 'Levemente por debajo', 'count' => $stats['traffic_lights']['yellow'], 'color' => 'warning'],
          ['label' => 'Incumplido', 'count' => $stats['traffic_lights']['red'], 'color' => 'danger'],
          ['label' => 'Sin medición', 'count' => $stats['traffic_lights']['unmeasured'], 'color' => 'secondary'],
        ],
      ])
    </div>
  </div>
  @endif
  @if($stats['goals_total'] === 0)
  <div class="card border-secondary my-2">
    <div class="card-body">
      <h6><b><i class="fas fa-triangle-exclamation"></i> ¡El objetivo no cuenta con metas!</b></h6>
      <span class="text-muted">Debe comenzar creando las metas para el objetivo para que luego se puedan crear reportes a futuro.</span>
    </div>
  </div>
  @endif
  @include('objective.manage.partials.latest-comments', [
    'listUrl' => route('objectives.manage.comments', ['objectiveId' => $objective->id]),
  ])
</section>

@endsection
