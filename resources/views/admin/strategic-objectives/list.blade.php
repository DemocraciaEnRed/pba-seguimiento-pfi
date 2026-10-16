@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Objetivos Estratégicos</h3>
  <p class="lead">A continuación encontrarán los objetivos estratégicos, agrupados por Eje:</p>

  <form method="GET" action="{{ route('admin.strategic-objectives') }}" class="mb-3">
    <div class="d-grid grid-cols-1 grid-cols-md-3 gap-2 align-items-end">
      <div class="col-span-md-2">
        <label for="q" class="text-smaller mb-1">Buscar</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Código o título">
      </div>
      <div>
        <label for="eje" class="text-smaller mb-1">Eje</label>
        <select id="eje" name="eje" class="form-control">
          <option value="">Todos</option>
          @foreach($categories as $category)
          <option value="{{ $category->id }}" @selected($filters['eje'] === $category->id)>{{ $category->title }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass fa-fw"></i>Filtrar</button>
      @if(array_filter($filters))
      <a href="{{ route('admin.strategic-objectives') }}" class="btn btn-link">Limpiar filtros</a>
      @endif
    </div>
  </form>

  @forelse($strategicObjectivesByCategory as $strategicObjectives)
  @php($category = $strategicObjectives->first()->category)
  <div class="d-flex align-items-center gap-2 mt-4 mb-2">
    <div class="category-icon-container" style="background-color: {{ $category->background_color }}">
      <x-category-icon :icon="$category->icon" style="color: {{ $category->color }}" />
    </div>
    <h5 class="is-700 mb-0 flex-grow-1" style="color: {{ $category->color }}">{{ $category->title }}</h5>
    <a href="{{ route('admin.categories.edit', ['categoryId' => $category->id]) }}" class="btn btn-link btn-sm"><i class="fas fa-pencil fa-fw"></i>Editar Eje</a>
  </div>
  <div class="list-group shadow-sm">
    @foreach($strategicObjectives as $strategicObjective)
    <div class="list-group-item d-flex align-items-center gap-3">
      <div class="flex-grow-1 min-w-0">
        <span class="text-smaller text-muted">{{ $strategicObjective->codigo }}</span>
        <p class="mb-0">{{ $strategicObjective->title }}</p>
      </div>
      <a href="{{ route('admin.objectives', ['eje' => $category->id, 'estrategico' => $strategicObjective->id]) }}" class="text-smaller text-nowrap" title="Ver objetivos generales">
        <i class="fas fa-flag-checkered fa-fw"></i>{{ $strategicObjective->objectives_count }} {{ $strategicObjective->objectives_count === 1 ? 'objetivo general' : 'objetivos generales' }}
      </a>
      <a href="{{ route('admin.strategic-objectives.delete', ['strategicObjectiveId' => $strategicObjective->id]) }}" class="btn btn-link btn-sm text-nowrap"><i class="fas fa-trash-can fa-fw"></i>Eliminar</a>
    </div>
    @endforeach
  </div>
  @empty
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      @if(array_filter($filters))
      <h6 class="mb-0">No se encontraron objetivos estratégicos</h6>
      @else
      <h6 class="card-title">No hay objetivos estratégicos cargados</h6>
      <a href="{{ route('admin.categories.create') }}" class="card-link"><b>Haga clic para crear un eje con objetivos estratégicos <i class="fas fa-arrow-right"></i></b></a>
      @endif
    </div>
  </div>
  @endforelse
</section>

@endsection
