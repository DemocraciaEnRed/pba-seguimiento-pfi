@extends('admin.master')

@section('adminContent')

<section>
  <a href="{{ route('admin.goal-assignments') }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver a los equipos por meta</a>
  <h3 class="is-700 mt-3">Equipos por meta</h3>
  <p class="lead mb-1">{{ $objective->title }}</p>
  <p class="text-smaller text-muted">Estas asignaciones delimitan los permisos sobre cada meta. Asignar Reporteros por objetivo alcanza solo las metas existentes; las futuras requieren una asignación expresa.</p>
  @if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <h5 class="is-700 mt-4">Metas y asignaciones</h5>
  <div class="list-group shadow-sm mb-4">
    @forelse($objective->goals as $goal)
    <div class="list-group-item">
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div class="min-w-0">
          <a class="is-700 text-dark" href="{{ route('admin.goal-assignments.goal', ['goalId' => $goal->id]) }}">{{ $goal->title }}</a>
          <div class="text-smaller mt-1">
            <span class="d-block"><b>Responsables:</b> {{ $goal->responsibles->pluck('fullname')->join(', ') ?: 'Sin asignar' }}</span>
            <span class="d-block"><b>Reporteros:</b> {{ $goal->reporters->pluck('fullname')->join(', ') ?: 'Sin asignar' }}</span>
          </div>
        </div>
        <a class="btn btn-outline-primary btn-sm text-nowrap" href="{{ route('admin.goal-assignments.goal', ['goalId' => $goal->id]) }}">Administrar equipo</a>
      </div>
    </div>
    @empty
    <div class="list-group-item text-center text-muted">Este objetivo todavía no tiene metas.</div>
    @endforelse
  </div>

  <h5 class="is-700">Asignar Reporteros a las metas existentes</h5>
  <p class="text-smaller text-muted">Buscá una persona integrante PFI y agregala como Reportera a todas las metas actuales de este objetivo. No se asignará a metas creadas después.</p>
  <form method="GET" action="{{ route('admin.goal-assignments.objective', ['objectiveId' => $objective->id]) }}" class="mb-3">
    <label for="q" class="text-smaller mb-1">Buscar persona PFI</label>
    <div class="d-flex gap-2">
      <input type="search" id="q" name="q" value="{{ $search }}" class="form-control" placeholder="Nombre, apellido o correo" minlength="3">
      <button type="submit" class="btn btn-primary text-nowrap"><i class="fas fa-magnifying-glass fa-fw"></i>Buscar</button>
    </div>
  </form>
  @if($candidates)
  <div class="list-group shadow-sm mb-4">
    @forelse($candidates as $candidate)
    <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
      <span class="min-w-0"><span class="is-700">{{ $candidate->fullname }}</span><span class="d-block text-smaller text-muted">{{ $candidate->organization ?: 'Sin organismo informado' }} · {{ $candidate->email }}</span></span>
      <form method="POST" action="{{ route('admin.goal-assignments.objective.reporters.store', ['objectiveId' => $objective->id, 'userId' => $candidate->id]) }}">
        @csrf
        <input type="hidden" name="q" value="{{ $search }}">
        <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap" @disabled($objective->goals->isEmpty())>Agregar a metas actuales</button>
      </form>
    </div>
    @empty
    <div class="list-group-item text-center text-muted">No se encontraron integrantes PFI.</div>
    @endforelse
  </div>
  {{ $candidates->links() }}
  @endif
</section>

@endsection
