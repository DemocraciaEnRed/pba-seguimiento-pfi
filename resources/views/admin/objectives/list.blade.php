@extends('admin.master')
@use('App\Services\Admin\ObjectiveDirectory')

@section('adminContent')

<section>
  <h3 class="is-700">Objetivos Generales</h3>
  <p class="lead">Acá encontrarás el listado de objetivos que podés administrar</p>

  <form method="GET" action="{{ route('admin.objectives') }}" class="mb-3">
    <div class="d-grid grid-cols-1 grid-cols-md-2 grid-cols-lg-3 gap-2 align-items-end">
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
        <label for="estrategico" class="text-smaller mb-1">Objetivo estratégico</label>
        <select id="estrategico" name="estrategico" class="form-control">
          <option value="">Todos</option>
          @foreach($strategicObjectives as $strategicObjective)
          <option value="{{ $strategicObjective->id }}" @selected($filters['estrategico'] === $strategicObjective->id)>{{ $strategicObjective->codigo }} · {{ Str::limit($strategicObjective->title, 80) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="visibilidad" class="text-smaller mb-1">Visibilidad</label>
        <select id="visibilidad" name="visibilidad" class="form-control">
          <option value="">Todos</option>
          <option value="{{ ObjectiveDirectory::VISIBLE }}" @selected($filters['visibilidad'] === ObjectiveDirectory::VISIBLE)>Públicos</option>
          <option value="{{ ObjectiveDirectory::HIDDEN }}" @selected($filters['visibilidad'] === ObjectiveDirectory::HIDDEN)>Ocultos</option>
        </select>
      </div>
      <div>
        <label for="metas" class="text-smaller mb-1">Metas y reportes</label>
        <select id="metas" name="metas" class="form-control">
          <option value="">Todos</option>
          @foreach(ObjectiveDirectory::goalOptions() as $value => $label)
          <option value="{{ $value }}" @selected($filters['metas'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="orden" class="text-smaller mb-1">Ordenar por</label>
        <select id="orden" name="orden" class="form-control">
          @foreach(ObjectiveDirectory::sortOptions() as $value => $label)
          <option value="{{ $value }}" @selected(($filters['orden'] ?? ObjectiveDirectory::SORT_TITLE) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass fa-fw"></i>Filtrar</button>
      @if(array_filter($filters))
      <a href="{{ route('admin.objectives') }}" class="btn btn-link">Limpiar filtros</a>
      @endif
    </div>
  </form>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-smaller text-muted">{{ $objectives->total() }} resultado(s)</span>
    <a href="{{ route('admin.objectives.download') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i>Descargar CSV</a>
  </div>

  @forelse($objectives as $objective)
  <div class="card mb-2 shadow-sm">
    <div class="card-body p-3 d-flex align-items-start gap-3">
      <div class="category-icon-container" style="background-color: {{$objective->category->background_color}}">
        <x-category-icon :icon="$objective->category->icon" size="2x" style="color: {{$objective->category->color}}" />
      </div>
      <div class="flex-grow-1 min-w-0">
        <span class="text-smaller" style="color:{{$objective->category->color}}">{{$objective->category->title}}</span>
        <span class="text-smaller text-muted">· {{ $objective->strategicObjective->codigo }}</span>
        @if($objective->hidden)
        <span class="badge badge-secondary align-middle">Oculto</span>
        @endif
        <h5 class="is-700 my-1">
          <a href="{{route('objectives.manage.index',['objectiveId' => $objective->id])}}" class="text-dark">{{$objective->title}}</a>
        </h5>
        <p class="text-muted text-smaller mb-0">{{Str::limit($objective->content, 200, ' [...]')}}</p>
      </div>
      <div class="text-right text-smaller text-nowrap">
        <span class="d-block" title="Metas"><i class="fas fa-bullseye fa-fw"></i>{{ $objective->goals_count }} {{ $objective->goals_count === 1 ? 'meta' : 'metas' }}</span>
        <a href="{{ route('admin.reports', ['eje' => $objective->category->id, 'objetivo' => $objective->id]) }}" class="d-block" title="Ver reportes"><i class="far fa-file-lines fa-fw"></i>{{ $objective->reports_count }} {{ $objective->reports_count === 1 ? 'reporte' : 'reportes' }}</a>
        <span class="d-block" title="Miembros del equipo"><i class="fas fa-users fa-fw"></i>{{ $objective->members_count }} {{ $objective->members_count === 1 ? 'miembro' : 'miembros' }}</span>
        <span class="text-muted">Último reporte: {{ $objective->last_report_date ? \Carbon\Carbon::parse($objective->last_report_date)->format('d/m/Y') : '-' }}</span>
      </div>
    </div>
  </div>
  @empty
  <div class="card my-3 shadow-sm">
    <div class="card-body text-center">
      <h6 class="mb-0">No se encontraron objetivos</h6>
    </div>
  </div>
  @endforelse
  {{ $objectives->links() }}
</section>

@endsection
