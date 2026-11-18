@extends('admin.master')

@section('adminContent')

<section>
  @include('admin.users.partials.header')

  <h5 class="is-700 mb-1"><i class="fas fa-users fa-fw"></i>&nbsp;Equipos que integra</h5>
  <p class="text-smaller text-muted">Los permisos corresponden exclusivamente a las metas asignadas. Las bajas requieren resolver la continuidad de sus pendientes.</p>
  @forelse($goals as $goal)
  <div class="card shadow-sm mb-2">
    <div class="card-body p-3 d-flex flex-wrap align-items-center gap-3">
      <div class="flex-grow-1 min-w-0">
        <p class="text-smaller text-muted mb-0">{{ $goal->objective->title }}</p>
        <a href="{{ route('admin.goal-assignments.goal', ['goalId' => $goal->id]) }}" class="is-600 text-dark">{{ $goal->title }}</a>
        <span class="badge badge-info">{{ $goal->pivot->role === 'responsible' ? 'Responsable' : 'Reportero' }}</span>
      </div>
      <a href="{{ route('admin.goal-assignments.goal', ['goalId' => $goal->id]) }}" class="btn btn-outline-primary btn-sm">Administrar asignaciones</a>
    </div>
  </div>
  @empty
  <p class="text-muted text-smaller">El usuario no tiene metas asignadas. Podés incorporarlo desde Equipos por meta.</p>
  @endforelse

  <h5 class="is-700 mt-4 mb-3"><i class="fas fa-envelope fa-fw"></i>&nbsp;Suscripciones</h5>
  @forelse($subscriptions as $objective)
  <p class="mb-1"><a href="{{ route('objectives.manage.index', ['objectiveId' => $objective->id]) }}" class="text-dark">{{ $objective->title }}</a> <span class="text-smaller text-muted">· {{ $objective->strategicObjective?->category?->title ?? 'Sin eje' }}</span></p>
  @empty
  <p class="text-muted text-smaller">El usuario no está suscripto a ningún objetivo.</p>
  @endforelse
</section>

@endsection
