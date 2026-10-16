@extends('objective.manage.master')

@section('panelContent')

<section>
  <h3 class="is-700">Equipo del objetivo</h3>
  <p class="lead">El equipo de un objetivo está conformado por los usuarios que podrán coordinar objetivos y realizar reportes y usuarios que únicamente podrán realizar reportes.</p>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
    <form method="GET" action="{{ route('objectives.manage.team', ['objectiveId' => $objective->id]) }}" class="d-flex align-items-end gap-2">
      <div>
        <label for="rol" class="text-smaller mb-1">Rol</label>
        <select id="rol" name="rol" class="form-control form-control-sm" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="manager" @selected($filters['rol'] === 'manager')>Coordina</option>
          <option value="reporter" @selected($filters['rol'] === 'reporter')>Reporta</option>
        </select>
      </div>
      <noscript><button type="submit" class="btn btn-primary btn-sm">Filtrar</button></noscript>
    </form>
    @isManager($objective->id)
    <a href="{{ route('objectives.manage.team.add', ['objectiveId' => $objective->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus fa-fw"></i>Agregar miembro</a>
    @endisManager
  </div>
  <p class="text-smaller text-muted mb-2">{{ $members->count() }} resultado(s)</p>

  <div class="list-group shadow-sm mb-3">
    @forelse($members as $member)
    <div class="list-group-item d-flex align-items-center gap-3">
      @include('utils.avatar', ['avatar' => $member->avatar, 'size' => 36, 'thumbnail' => true])
      <div class="flex-grow-1 min-w-0">
        <span class="is-700">{{ $member->surname }}, {{ $member->name }}</span>
        @isManager($objective->id)
        <span class="d-block text-smaller text-muted text-truncate">{{ $member->email }}</span>
        @endisManager
      </div>
      <span class="badge {{ $member->pivot->role === 'manager' ? 'badge-primary' : 'badge-light' }}">{{ $member->pivot->role === 'manager' ? 'Coordina' : 'Reporta' }}</span>
      @isManager($objective->id)
      <form action="{{ route('objectives.manage.team.remove.form', ['objectiveId' => $objective->id, 'usrId' => $member->id]) }}" method="POST">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="fas fa-xmark fa-fw"></i>Quitar</button>
      </form>
      @endisManager
    </div>
    @empty
    <div class="list-group-item text-center text-muted py-4">
      @if($filters['rol'])
      No hay miembros con ese rol.
      @else
      <i class="far fa-face-surprise"></i>&nbsp;¡No se encontraron miembros del equipo!
      @endif
    </div>
    @endforelse
  </div>
</section>

@endsection
