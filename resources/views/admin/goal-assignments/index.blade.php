@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Equipos por meta</h3>
  <p class="lead">Administrá Responsables y Reporteros de las metas. Estas asignaciones delimitan los permisos sobre cada meta.</p>
  <form method="GET" action="{{ route('admin.goal-assignments') }}" class="mb-3">
    <label for="q" class="text-smaller mb-1">Buscar objetivo general</label>
    <div class="d-flex gap-2">
      <input type="search" id="q" name="q" value="{{ $search }}" class="form-control" placeholder="Título del objetivo" minlength="3">
      <button type="submit" class="btn btn-primary text-nowrap"><i class="fas fa-magnifying-glass fa-fw"></i>Buscar</button>
    </div>
  </form>
  <div class="list-group shadow-sm mb-3">
    @forelse($objectives as $objective)
    <a href="{{ route('admin.goal-assignments.objective', ['objectiveId' => $objective->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3">
      <span class="min-w-0">
        <span class="d-block text-smaller text-muted">{{ $objective->strategicObjective->category->title }} · {{ $objective->strategicObjective->codigo }}</span>
        <span class="d-block is-700 text-dark">{{ $objective->title }}</span>
      </span>
      <span class="text-smaller text-nowrap text-muted">{{ $objective->goals_count }} {{ $objective->goals_count === 1 ? 'meta' : 'metas' }} <i class="fas fa-arrow-right fa-fw"></i></span>
    </a>
    @empty
    <div class="list-group-item text-center text-muted">No se encontraron objetivos generales.</div>
    @endforelse
  </div>
  {{ $objectives->links() }}
</section>

@endsection
