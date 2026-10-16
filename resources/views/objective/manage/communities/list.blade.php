@extends('objective.manage.master')

@section('panelContent')

<section>
  <h3 class="is-700">Comunidades</h3>
  <p class="lead">Las comunidades de los objetivos son puntos de acceso para que los ciudadanos puedan interactuar en otros espacios. Estos son los "botones" para invitar a ser parte de la comunidad.</p>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="text-smaller text-muted">{{ $communities->total() }} comunidad(es)</span>
    <a href="{{ route('objectives.manage.communities.add', ['objectiveId' => $objective->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus fa-fw"></i>Nueva comunidad</a>
  </div>

  <div class="list-group shadow-sm mb-3">
    @forelse($communities as $community)
    <div class="list-group-item d-flex align-items-center gap-3">
      <a href="{{ $community->url }}" target="_blank" rel="noopener" class="py-1 px-3 rounded d-inline-block text-nowrap" style="border: 2px solid {{ $community->color }}; color: {{ $community->color }}"><i class="{{ $community->icon }}"></i>&nbsp;{{ $community->label }}</a>
      <span class="flex-grow-1 min-w-0 text-smaller text-muted text-truncate" title="{{ $community->url }}">{{ $community->url }}</span>
      <form action="{{ route('objectives.manage.communities.remove.form', ['objectiveId' => $objective->id, 'communityId' => $community->id]) }}" method="POST">
        @method('DELETE')
        @csrf
        <button type="submit" class="btn btn-link btn-sm text-danger p-0 text-nowrap"><i class="fas fa-trash-can fa-fw"></i>Eliminar</button>
      </form>
    </div>
    @empty
    <div class="list-group-item text-center text-muted py-4">
      <i class="far fa-face-surprise"></i>&nbsp;¡No hay comunidades del objetivo!
    </div>
    @endforelse
  </div>
  {{ $communities->links() }}
</section>

@endsection
