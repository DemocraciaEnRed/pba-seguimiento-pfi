@use('App\Goal')
@use('App\Services\Indicators\MeasurementMode')
@extends('objective.manage.master')

@section('panelContent')

<section>
  <h3 class="is-700">Metas del objetivo</h3>
  <p class="lead">A continuación, encontrarás todas las metas asociadas a tu objetivo</p>

  @if($hasGoals)
  <form method="GET" action="{{ route('objectives.manage.goals', ['objectiveId' => $objective->id]) }}" class="mb-3">
    <div class="d-grid grid-cols-1 grid-cols-md-3 gap-2 align-items-end">
      <div>
        <label for="q" class="text-smaller mb-1">Título</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Buscar por título">
      </div>
      <div>
        <label for="estado" class="text-smaller mb-1">Estado</label>
        <select id="estado" name="estado" class="form-control" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach(Goal::STATUSES as $value => $label)
          <option value="{{ $value }}" @selected($filters['estado'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="tipo" class="text-smaller mb-1">Medición</label>
        <select id="tipo" name="tipo" class="form-control" onchange="this.form.submit()">
          <option value="">Todas</option>
          @foreach(MeasurementMode::cases() as $mode)
          <option value="{{ $mode->value }}" @selected($filters['tipo'] === $mode->value)>{{ $mode->label() }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass fa-fw"></i>Filtrar</button>
      @if(array_filter($filters))
      <a href="{{ route('objectives.manage.goals', ['objectiveId' => $objective->id]) }}" class="btn btn-link">Limpiar filtros</a>
      @endif
    </div>
  </form>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="text-smaller text-muted">{{ $goals->count() }} resultado(s)</span>
    @isManager($objective->id)
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('objectives.manage.goals.download', ['objectiveId' => $objective->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i>Descargar CSV</a>
      <a href="{{ route('objectives.manage.goals.indicators.download', ['objectiveId' => $objective->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i>Indicadores por período CSV</a>
    </div>
    @endisManager
  </div>
  @endif

  <div class="list-group shadow-sm mb-3">
    @forelse($goals as $goal)
    <div class="list-group-item d-flex align-items-start gap-3">
      <i class="far fa-circle-dot fa-fw text-{{ $goal->status }} mt-1" title="Meta {{ $goal->status_label }}"></i>
      <div class="flex-grow-1 min-w-0">
        <a href="{{ route('objectives.manage.goals.index', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}" class="is-700 text-dark">{{ $goal->title }}</a>
        <div class="text-smaller text-muted">
          <span class="text-{{ $goal->status }}">{{ $goal->status_label }}</span>
          &middot; {{ $goal->measurement_mode->label() }}
          @if($goal->measurement_mode !== MeasurementMode::None)
          &middot; {{ $goal->isPeriodic() ? 'Cumplimiento' : 'Progreso' }} {{ $goal->progress_label }}
          @endif
        </div>
      </div>
      <div class="text-right text-smaller text-nowrap text-muted">
        <span title="Reportes"><i class="far fa-file-lines fa-fw"></i>{{ $goal->reports_count }}</span>
        <span class="ml-2" title="Hitos"><i class="far fa-star fa-fw"></i>{{ $goal->milestones_count }}</span>
      </div>
    </div>
    @empty
    <div class="list-group-item text-center text-muted py-4">
      @if($hasGoals)
      No hay metas que coincidan con los filtros.
      @else
      <i class="far fa-face-surprise"></i>&nbsp;¡No hay metas del objetivo! <a href="{{ route('objectives.manage.goals.add', ['objectiveId' => $objective->id]) }}">Crear la primera meta</a>
      @endif
    </div>
    @endforelse
  </div>
</section>

@endsection
