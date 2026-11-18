@extends('admin.master')
@use('App\Services\Admin\ReportDirectory')

@section('adminContent')

<section>
  <h3 class="is-700">Reportes</h3>
  <p class="lead">Todos los reportes cargados en la plataforma, incluidos los de objetivos ocultos.</p>
  <a href="{{ route('admin.reports', ['revision' => 'pending']) }}" class="btn btn-outline-primary mb-3">Pendientes de revisión: {{ $pendingCount }}</a>
  <a href="{{ route('admin.reports', ['revision' => 'validated']) }}" class="btn btn-outline-primary mb-3">Validados pendientes de Administración: {{ $validatedCount }}</a>

  <form method="GET" action="{{ route('admin.reports') }}" class="mb-3">
    <div class="d-grid grid-cols-1 grid-cols-md-2 grid-cols-lg-3 gap-2 align-items-end">
      <div>
        <label for="revision">Revisión</label>
        <select id="revision" name="revision" class="form-control">
          <option value="">Todos</option>
          @foreach(\App\Services\Reports\ReviewStatus::cases() as $state)
          <option value="{{ $state->value }}" @selected($filters['revision'] === $state->value)>{{ $state->label() }}</option>
          @endforeach
          <option value="closed" @selected($filters['revision'] === 'closed')>Cerrado y consolidado</option>
        </select>
      </div>
      <div>
        <label for="q" class="text-smaller mb-1">Título</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Buscar por título">
      </div>
      <div>
        <label for="eje" class="text-smaller mb-1">Eje</label>
        <select id="eje" name="eje" class="form-control" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach($categories as $category)
          <option value="{{ $category->id }}" @selected($filters['eje'] === $category->id)>{{ $category->title }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="objetivo" class="text-smaller mb-1">Objetivo general</label>
        <select id="objetivo" name="objetivo" class="form-control" onchange="this.form.submit()">
          <option value="">Todos</option>
          @foreach($objectives as $objective)
          <option value="{{ $objective->id }}" @selected($filters['objetivo'] === $objective->id)>{{ Str::limit($objective->title, 90) }}</option>
          @endforeach
        </select>
      </div>
      @if($filters['objetivo'])
      <div>
        <label for="meta" class="text-smaller mb-1">Meta</label>
        <select id="meta" name="meta" class="form-control" onchange="this.form.submit()">
          <option value="">Todas</option>
          @foreach($goals as $goal)
          <option value="{{ $goal->id }}" @selected($filters['meta'] === $goal->id)>{{ Str::limit($goal->title, 90) }}</option>
          @endforeach
        </select>
      </div>
      @endif
      <div>
        <label for="autor" class="text-smaller mb-1">Autor</label>
        <input type="search" id="autor" name="autor" value="{{ $filters['autor'] }}" class="form-control" placeholder="Nombre, apellido o email">
      </div>
      <div class="d-grid grid-cols-2 gap-2">
        <div>
          <label for="desde" class="text-smaller mb-1">Cargado desde</label>
          <input type="date" id="desde" name="desde" value="{{ $filters['desde'] }}" class="form-control">
        </div>
        <div>
          <label for="hasta" class="text-smaller mb-1">Hasta</label>
          <input type="date" id="hasta" name="hasta" value="{{ $filters['hasta'] }}" class="form-control">
        </div>
      </div>
      <div>
        <label for="comentarios" class="text-smaller mb-1">Comentarios</label>
        <select id="comentarios" name="comentarios" class="form-control">
          <option value="">Todos</option>
          <option value="{{ ReportDirectory::WITH_COMMENTS }}" @selected($filters['comentarios'] === ReportDirectory::WITH_COMMENTS)>Con comentarios</option>
          <option value="{{ ReportDirectory::WITHOUT_COMMENTS }}" @selected($filters['comentarios'] === ReportDirectory::WITHOUT_COMMENTS)>Sin comentarios</option>
        </select>
      </div>
      <div>
        <label for="orden" class="text-smaller mb-1">Ordenar por</label>
        <select id="orden" name="orden" class="form-control">
          @foreach(ReportDirectory::sortOptions() as $value => $label)
          <option value="{{ $value }}" @selected(($filters['orden'] ?? ReportDirectory::SORT_RECENT) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass fa-fw"></i>Filtrar</button>
      @if(array_filter($filters))
      <a href="{{ route('admin.reports') }}" class="btn btn-link">Limpiar filtros</a>
      @endif
    </div>
  </form>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-smaller text-muted">{{ $reports->total() }} resultado(s)</span>
    <a href="{{ route('admin.reports.download', array_filter($filters)) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i>Descargar CSV</a>
  </div>

  <div class="list-group shadow-sm mb-3">
    @forelse($reports as $report)
    @php($objective = $report->goal->objective)
    <div class="list-group-item d-flex align-items-start gap-3">
      <i class="{{ $report->type_icon }} fa-fw text-muted mt-1" title="{{ $report->type_label }}"></i>
      <div class="flex-grow-1 min-w-0">
        <p class="mb-0 text-smaller text-muted">
          {{ $report->date?->format('d/m/Y') ?? '-' }} · {{ $report->type_label }}
          <span class="badge badge-secondary">{{ $report->review_status_label }}</span>
          @if($objective->hidden)
          <span class="badge badge-secondary align-middle">Objetivo oculto</span>
          @endif
        </p>
        <a href="{{ route('objectives.manage.goals.reports.index', ['objectiveId' => $objective->id, 'goalId' => $report->goal_id, 'reportId' => $report->id]) }}" class="is-600 text-dark">{{ $report->title }}</a>
        <p class="mb-0 text-smaller text-muted text-truncate">
          <span style="color: {{ $objective->category->color }}">{{ $objective->category->title }}</span> · {{ $objective->title }} › {{ $report->goal->title }}
        </p>
        <p class="mb-0 text-smaller text-muted">Por {{ $report->author?->fullname ?? 'usuario eliminado' }} · cargado el {{ $report->created_at?->format('d/m/Y') ?? '-' }}</p>
      </div>
      <div class="text-right text-smaller text-nowrap">
        <span class="d-block" title="Comentarios">{{ $report->comments_count }}<i class="far fa-comments fa-fw ml-1"></i></span>
        <span class="d-block" title="Me gusta">{{ $report->positive_testimonies_count }}<i class="far fa-thumbs-up fa-fw ml-1"></i></span>
        @if($report->isApproved())
        <a href="{{ route('reports.index', ['reportId' => $report->id]) }}" target="_blank" rel="noopener" title="Ver en el portal">Portal<i class="fas fa-arrow-up-right-from-square fa-fw ml-1"></i></a>
        @endif
      </div>
    </div>
    @empty
    <div class="list-group-item text-center">
      <h6 class="mb-0">No se encontraron reportes</h6>
    </div>
    @endforelse
  </div>
  {{ $reports->links() }}
</section>

@endsection
