@extends('admin.master')

@section('adminContent')

<section>
  @include('admin.users.partials.header')

  <h5 class="is-700 mb-1"><i class="fas fa-users fa-fw"></i>&nbsp;Equipos que integra</h5>
  <p class="text-smaller text-muted">El rol de coordinación permite gestionar metas y el equipo del objetivo; el de reporte, cargar reportes.</p>
  @forelse($objectives as $objective)
  <div class="card shadow-sm mb-2">
    <div class="card-body p-3 d-flex flex-wrap align-items-center gap-3">
      <div class="flex-grow-1 min-w-0">
        <p class="text-smaller text-muted mb-0">{{ $objective->strategicObjective?->category?->title ?? 'Sin eje' }}</p>
        <a href="{{ route('objectives.manage.index', ['objectiveId' => $objective->id]) }}" class="is-600 text-dark">{{ $objective->title }}</a>
        @if($objective->hidden)
        <span class="badge badge-secondary align-middle">Oculto</span>
        @endif
      </div>
      <form method="POST" action="{{ route('admin.users.objectives.role.form', ['userId' => $user->id, 'objectiveId' => $objective->id]) }}" class="d-flex align-items-center gap-2">
        @csrf
        @method('PUT')
        <label for="role{{ $objective->id }}" class="sr-only">Rol en {{ $objective->title }}</label>
        <select id="role{{ $objective->id }}" name="role" class="form-control form-control-sm">
          <option value="manager" @selected($objective->pivot->role === 'manager')>Coordina</option>
          <option value="reporter" @selected($objective->pivot->role === 'reporter')>Reporta</option>
        </select>
        <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">Cambiar rol</button>
      </form>
      <form method="POST" action="{{ route('admin.users.objectives.remove.form', ['userId' => $user->id, 'objectiveId' => $objective->id]) }}" onsubmit="return confirm('¿Quitar a {{ $user->fullname }} del equipo de este objetivo?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-link btn-sm text-danger"><i class="fas fa-xmark fa-fw"></i>Quitar del equipo</button>
      </form>
    </div>
  </div>
  @empty
  <p class="text-muted text-smaller">El usuario no integra el equipo de ningún objetivo. Para sumarlo, entrá al panel del objetivo › Equipo.</p>
  @endforelse

  <h5 class="is-700 mt-4 mb-3"><i class="fas fa-envelope fa-fw"></i>&nbsp;Suscripciones</h5>
  @forelse($subscriptions as $objective)
  <p class="mb-1"><a href="{{ route('objectives.manage.index', ['objectiveId' => $objective->id]) }}" class="text-dark">{{ $objective->title }}</a> <span class="text-smaller text-muted">· {{ $objective->strategicObjective?->category?->title ?? 'Sin eje' }}</span></p>
  @empty
  <p class="text-muted text-smaller">El usuario no está suscripto a ningún objetivo.</p>
  @endforelse
</section>

@endsection
