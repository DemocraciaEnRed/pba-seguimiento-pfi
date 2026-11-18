@extends('panel.master')
@use('App\Services\Workspace\WorkDesk')

@section('panelContent')

<section class="work-desk">
  <h3 class="is-700">Mesa de trabajo</h3>
  <p class="lead">Tus metas y las tareas que siguen abiertas, de la más antigua a la más reciente. La Mesa no amplía permisos: cada enlace vuelve a verificar tu acceso.</p>

  <div class="d-grid grid-cols-2 grid-cols-lg-3 gap-3 mb-4">
    @include('admin.partials.stat', ['label' => 'Requiere mi acción', 'icon' => 'fas fa-hand-point-right', 'value' => $summary['action'], 'route' => '#accion'])
    @include('admin.partials.stat', ['label' => 'Esperando a otra persona', 'icon' => 'far fa-hourglass-half', 'value' => $summary['waiting'], 'route' => '#esperando'])
    @include('admin.partials.stat', ['label' => 'Mis metas', 'icon' => 'fas fa-medal', 'value' => $summary['goals'], 'route' => '#metas'])
    @if($isAdmin)
    @include('admin.partials.stat', ['label' => 'Pendientes de Administración', 'icon' => 'fas fa-user-shield', 'value' => $summary['administration'], 'route' => '#administracion'])
    @include('admin.partials.stat', ['label' => 'Alertas de continuidad', 'icon' => 'fas fa-triangle-exclamation', 'value' => $summary['alerts'], 'route' => '#alertas'])
    @endif
  </div>

  <form method="GET" action="{{ route('panel.desk') }}" class="mb-4">
    <div class="d-grid grid-cols-1 grid-cols-md-2 grid-cols-lg-4 gap-2 align-items-end">
      <div>
        <label for="rol" class="text-smaller mb-1">Mi rol</label>
        <select id="rol" name="rol" class="form-control">
          <option value="">Todos</option>
          @foreach(WorkDesk::roleOptions() as $value => $label)
          <option value="{{ $value }}" @selected($filters['rol'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
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
        <select id="objetivo" name="objetivo" class="form-control">
          <option value="">Todos</option>
          @foreach($objectives as $objective)
          <option value="{{ $objective->id }}" @selected($filters['objetivo'] === $objective->id)>{{ Str::limit($objective->title, 90) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="tipo" class="text-smaller mb-1">Tipo de tarea</label>
        <select id="tipo" name="tipo" class="form-control">
          <option value="">Todas</option>
          @foreach(WorkDesk::taskOptions() as $value => $label)
          <option value="{{ $value }}" @selected($filters['tipo'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <p class="text-smaller text-muted mt-2 mb-0">«Mi rol» se aplica a tus metas y tareas{{ $isAdmin ? ', no a las secciones de Administración' : '' }}. «Tipo de tarea» se aplica a las listas de reportes. Los contadores de arriba no dependen de los filtros.</p>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <button type="submit" class="btn btn-primary"><i class="fas fa-filter fa-fw"></i>Filtrar</button>
      @if(array_filter($filters))
      <a href="{{ route('panel.desk') }}" class="btn btn-link">Limpiar filtros</a>
      @endif
    </div>
  </form>

  <h5 id="accion" class="is-700 mb-1"><i class="fas fa-hand-point-right fa-fw"></i>&nbsp;Requiere mi acción</h5>
  <p class="text-smaller text-muted">Reportes que te toca revisar como Responsable y reportes devueltos que tenés a cargo. Cuando alguien resuelve una revisión compartida, deja de figurar para el resto.</p>
  <div class="list-group shadow-sm mb-2">
    @forelse($actionRequired as $report)
      @include('panel.desk.partials.report', ['report' => $report, 'context' => 'action'])
    @empty
    <div class="list-group-item text-center text-muted">
      @if($summary['goals'] === 0)
      Sin metas asignadas no tenés tareas propias.
      @elseif(array_filter($filters))
      No hay tareas que coincidan con los filtros.
      @else
      No tenés tareas pendientes.
      @endif
    </div>
    @endforelse
  </div>
  {{ $actionRequired->links() }}

  <h5 id="esperando" class="is-700 mt-4 mb-1"><i class="far fa-hourglass-half fa-fw"></i>&nbsp;Esperando a otra persona</h5>
  <p class="text-smaller text-muted">Reportes que seguís pero cuyo próximo paso corresponde a otra persona. No son tareas tuyas.</p>
  <div class="list-group shadow-sm mb-2">
    @forelse($waiting as $report)
      @include('panel.desk.partials.report', ['report' => $report, 'context' => 'waiting'])
    @empty
    <div class="list-group-item text-center text-muted">
      {{ array_filter($filters) ? 'No hay reportes que coincidan con los filtros.' : 'No hay reportes esperando a otra persona.' }}
    </div>
    @endforelse
  </div>
  {{ $waiting->links() }}

  @if($isAdmin)
  <h5 id="administracion" class="is-700 mt-4 mb-1"><i class="fas fa-user-shield fa-fw"></i>&nbsp;Pendientes de Administración</h5>
  <p class="text-smaller text-muted">Validados por resolver (Publicar en metas públicas; Cerrar y consolidar en metas internas) y pendientes de metas sin Responsable, que Administración puede validar.</p>
  <div class="list-group shadow-sm mb-2">
    @forelse($administrationTasks as $report)
      @include('panel.desk.partials.report', ['report' => $report, 'context' => 'administration'])
    @empty
    <div class="list-group-item text-center text-muted">
      {{ array_filter($filters) ? 'No hay pendientes que coincidan con los filtros.' : 'No hay pendientes de Administración.' }}
    </div>
    @endforelse
  </div>
  {{ $administrationTasks->links() }}

  <h5 id="alertas" class="is-700 mt-4 mb-1"><i class="fas fa-triangle-exclamation fa-fw"></i>&nbsp;Alertas de continuidad</h5>
  <p class="text-smaller text-muted">Metas en progreso sin Responsable o sin Reporteros, y reportes pendientes o devueltos cuya persona a cargo ya no puede atenderlos. Nada se asigna automáticamente.</p>
  <h6 class="is-700">Metas sin cobertura</h6>
  <div class="list-group shadow-sm mb-2">
    @forelse($uncoveredGoals as $goal)
    @php($category = $goal->objective->strategicObjective?->category)
    <div class="list-group-item d-flex align-items-start gap-3">
      <div class="flex-grow-1 min-w-0">
        <p class="mb-0 text-smaller">
          @if((int) $goal->responsibles_count === 0)
          <span class="badge badge-danger">Sin Responsable</span>
          @endif
          @if((int) $goal->reporters_count === 0)
          <span class="badge badge-warning">Sin Reporteros</span>
          @endif
          @if($goal->visibility === 'internal')
          <span class="badge badge-dark">Interna</span>
          @endif
        </p>
        <span class="is-600 text-dark">{{ $goal->title }}</span>
        <p class="mb-0 text-smaller text-muted text-truncate" title="{{ $category ? $category->title.' · ' : '' }}{{ $goal->objective->title }}">
          @if($category)
          <span style="color: {{ $category->color }}">{{ $category->title }}</span> ·
          @endif
          {{ $goal->objective->title }}
        </p>
      </div>
      <a href="{{ route('admin.goal-assignments.goal', ['goalId' => $goal->id]) }}" class="btn btn-sm btn-primary text-nowrap">Asignar</a>
    </div>
    @empty
    <div class="list-group-item text-center text-muted">
      {{ $filters['eje'] || $filters['objetivo'] ? 'No hay metas sin cobertura que coincidan con los filtros.' : 'Todas las metas en progreso tienen Responsable y Reporteros.' }}
    </div>
    @endforelse
  </div>
  {{ $uncoveredGoals->links() }}

  <h6 class="is-700 mt-3">Reportes sin persona que pueda atenderlos</h6>
  <div class="list-group shadow-sm mb-2">
    @forelse($orphanedReports as $report)
      @include('panel.desk.partials.report', ['report' => $report, 'context' => 'orphaned'])
    @empty
    <div class="list-group-item text-center text-muted">
      {{ $filters['eje'] || $filters['objetivo'] || $filters['tipo'] ? 'No hay reportes sin atención que coincidan con los filtros.' : 'No hay reportes pendientes sin una persona que pueda atenderlos.' }}
    </div>
    @endforelse
  </div>
  {{ $orphanedReports->links() }}
  @endif

  <h5 id="metas" class="is-700 mt-4 mb-1"><i class="fas fa-medal fa-fw"></i>&nbsp;Mis metas</h5>
  <p class="text-smaller text-muted">Metas en las que sos Responsable, Reportero o ambas cosas. Cada meta figura una sola vez.</p>
  <div class="list-group shadow-sm mb-2">
    @forelse($goals as $goal)
    @php($category = $goal->objective->strategicObjective?->category)
    <div class="list-group-item d-flex align-items-start gap-3">
      <div class="flex-grow-1 min-w-0">
        <p class="mb-0 text-smaller">
          @if($goal->is_responsible)
          <span class="badge badge-primary">Responsable</span>
          @endif
          @if($goal->is_reporter)
          <span class="badge badge-info">Reportero</span>
          @endif
          @if($goal->visibility === 'internal')
          <span class="badge badge-dark">Interna</span>
          @endif
          <span class="badge badge-light">{{ $goal->status_label }}</span>
        </p>
        <a href="{{ route('objectives.manage.goals.index', ['objectiveId' => $goal->objective_id, 'goalId' => $goal->id]) }}" class="is-600 text-dark">{{ $goal->title }}</a>
        <p class="mb-0 text-smaller text-muted text-truncate" title="{{ $category ? $category->title.' · ' : '' }}{{ $goal->objective->title }}">
          @if($category)
          <span style="color: {{ $category->color }}">{{ $category->title }}</span> ·
          @endif
          {{ $goal->objective->title }}
        </p>
      </div>
      <div class="text-right text-smaller text-nowrap">
        <span class="d-block">{{ (int) $goal->tasks_count === 1 ? '1 tarea' : $goal->tasks_count.' tareas' }}</span>
        @if($goal->is_responsible)
        <a href="{{ route('panel.goal-assignments.goal', ['goalId' => $goal->id]) }}">Reporteros<i class="fas fa-arrow-right fa-fw"></i></a>
        @endif
      </div>
    </div>
    @empty
    <div class="list-group-item text-center text-muted">
      @if($summary['goals'] === 0)
      Todavía no tenés metas asignadas. Cuando te asignen una meta, aparecerá acá.
      @else
      No hay metas que coincidan con los filtros.
      @endif
    </div>
    @endforelse
  </div>
  {{ $goals->links() }}

  @if($isAdmin)
  <div class="card border-light mt-4">
    <div class="card-body">
      <h5 class="is-700 mb-1"><i class="fas fa-globe fa-fw"></i>&nbsp;Vista pública</h5>
      <p class="text-smaller text-muted mb-2">El portal muestra solo las metas públicas de objetivos visibles, con sus datos y cifras públicos. Las metas internas y los objetivos ocultos no aparecen allí.</p>
      <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Abrir el portal público<i class="fas fa-arrow-up-right-from-square fa-fw ml-1"></i></a>
    </div>
  </div>
  @endif
</section>

@endsection
