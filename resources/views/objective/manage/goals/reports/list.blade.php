@extends('objective.manage.goals.master')
@use('App\Report')

@section('panelContent')

<section>
  <h3 class="is-700">Reportes</h3>
  <p class="lead">A continuación encontrarás el listado de reportes asociados a la meta</p>

  @if($hasReports)
  <form method="GET" action="{{ route('objectives.manage.goals.reports', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}" class="mb-3">
    <div class="d-grid grid-cols-1 grid-cols-md-2 gap-2 align-items-end">
      <div>
        <label for="q" class="text-smaller mb-1">Título</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Buscar por título">
      </div>
      <div>
        <label for="tipo" class="text-smaller mb-1">Tipo</label>
        <select id="tipo" name="tipo" class="form-control" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach(Report::TYPES as $value => $label)
          <option value="{{ $value }}" @selected($filters['tipo'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass fa-fw"></i>Filtrar</button>
      @if(array_filter($filters))
      <a href="{{ route('objectives.manage.goals.reports', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}" class="btn btn-link">Limpiar filtros</a>
      @endif
    </div>
  </form>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="text-smaller text-muted">{{ $reports->total() }} resultado(s)</span>
    <div class="d-flex flex-wrap gap-2">
      @isManager($objective->id)
      <a href="{{ route('objectives.manage.goals.reports.download', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i>Descargar CSV</a>
      @endisManager
      <a href="{{ route('objectives.manage.goals.reports.add', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus fa-fw"></i>Nuevo reporte</a>
    </div>
  </div>
  @endif

  <div class="list-group shadow-sm mb-3">
    @forelse($reports as $report)
    <div class="list-group-item d-flex align-items-start gap-3">
      <i class="{{ $report->type_icon }} fa-fw text-muted mt-1" title="{{ $report->type_label }}"></i>
      <div class="flex-grow-1 min-w-0">
        <a href="{{ route('objectives.manage.goals.reports.index', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id]) }}" class="is-700 text-dark">{{ $report->title }}</a>
        <div class="text-smaller text-muted">
          {{ $report->type_label }} &middot; <span title="{{ $report->date }}">@justdate($report->date)</span>
          @if($report->author)
          &middot; {{ $report->author->fullname }}
          @endif
        </div>
        @if($report->period)
        <div class="text-smaller"><i class="far fa-calendar fa-fw text-muted"></i>Período {{ $report->period->label() }} &middot; {{ indicator_number($report->progress) }} {{ $goal->indicator_unit }}</div>
        @elseif($report->type === 'progress' && $report->progress !== null)
        <div class="text-smaller">Progreso declarado: {{ indicator_number($report->progress) }} {{ $goal->indicator_unit }}</div>
        @elseif($report->milestone)
        <div class="text-smaller"><i class="far fa-star fa-fw text-muted"></i>Hito #{{ $report->milestone->order }}: {{ $report->milestone->title }}</div>
        @endif
        @if($report->status)
        <div class="text-smaller">Estado declarado: <span class="text-{{ $report->status }}">{{ $report->status_label }}</span></div>
        @endif
      </div>
      <div class="text-right text-smaller text-nowrap text-muted">
        <span title="Comentarios"><i class="far fa-comments fa-fw"></i>{{ $report->comments_count }}</span>
        <span class="ml-2" title="Me gusta"><i class="far fa-thumbs-up fa-fw"></i>{{ $report->positive_testimonies_count }}</span>
      </div>
    </div>
    @empty
    <div class="list-group-item text-center text-muted py-4">
      @if($hasReports)
      No hay reportes que coincidan con los filtros.
      @else
      No hay reportes creados. <a href="{{ route('objectives.manage.goals.reports.add', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}">Crear el primer reporte</a>
      @endif
    </div>
    @endforelse
  </div>
  {{ $reports->links() }}
</section>

@endsection
