@extends($isAdmin ? 'admin.master' : 'panel.master')

@section($isAdmin ? 'adminContent' : 'panelContent')

<section>
  <a href="{{ $backUrl }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver</a>
  <h3 class="is-700 mt-3">Equipo por meta</h3>
  <p class="lead mb-1">{{ $goal->title }}</p>
  <p class="text-smaller text-muted">Objetivo: {{ $goal->objective->title }}</p>
  @if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif
  <p class="text-smaller text-muted">Estas asignaciones delimitan los permisos sobre esta meta. Al retirar a alguien se debe resolver la continuidad de sus pendientes.</p>

  <h5 class="is-700 mt-4">Responsables</h5>
  <div class="list-group shadow-sm mb-4">
    @forelse($goal->responsibles as $responsible)
    <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
      <span>{{ $responsible->fullname }}</span>
      @if($isAdmin)
      <a class="btn btn-link btn-sm text-danger" href="{{ route('admin.goal-assignments.responsibles.remove', ['goalId' => $goal->id, 'userId' => $responsible->id]) }}">Quitar</a>
      @endif
    </div>
    @empty
    <div class="list-group-item text-muted">Sin Responsables asignados.</div>
    @endforelse
  </div>

  <h5 class="is-700">Reporteros</h5>
  <div class="list-group shadow-sm mb-4">
    @forelse($goal->reporters as $reporter)
    <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
      <span>{{ $reporter->fullname }}</span>
      <a class="btn btn-link btn-sm text-danger" href="{{ $isAdmin ? route('admin.goal-assignments.reporters.remove', ['goalId' => $goal->id, 'userId' => $reporter->id]) : route('panel.goal-assignments.reporters.remove', ['goalId' => $goal->id, 'userId' => $reporter->id]) }}">Quitar</a>
    </div>
    @empty
    <div class="list-group-item text-muted">Sin Reporteros asignados.</div>
    @endforelse
  </div>

  <h5 class="is-700">Agregar integrantes PFI</h5>
  <p class="text-smaller text-muted">La pertenencia PFI solo habilita la selección; no otorga por sí misma acceso a esta meta. La búsqueda de Responsables queda reservada a Administración.</p>
  <form method="GET" action="{{ $isAdmin ? route('admin.goal-assignments.goal', ['goalId' => $goal->id]) : route('panel.goal-assignments.goal', ['goalId' => $goal->id]) }}" class="mb-3">
    <label for="q" class="text-smaller mb-1">Nombre, apellido o correo</label>
    <div class="d-flex gap-2">
      <input type="search" id="q" name="q" value="{{ $search }}" class="form-control" placeholder="Buscar integrante PFI" minlength="3">
      <button type="submit" class="btn btn-primary text-nowrap"><i class="fas fa-magnifying-glass fa-fw"></i>Buscar</button>
    </div>
  </form>
  @if($candidates)
  <div class="list-group shadow-sm">
    @forelse($candidates as $candidate)
    @php
      $isResponsible = $goal->responsibles->contains('id', $candidate->id);
      $isReporter = $goal->reporters->contains('id', $candidate->id);
    @endphp
    <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="min-w-0">
        <span class="is-700">{{ $candidate->fullname }}</span>
        @if($isAdmin)
        <span class="d-block text-smaller text-muted">{{ $candidate->email }}</span>
        @endif
      </span>
      <span class="d-flex flex-wrap gap-2">
        @if($isAdmin)
          @if($isResponsible)
          <span class="badge badge-primary align-self-center">Responsable</span>
          @else
          <form method="POST" action="{{ route('admin.goal-assignments.responsibles.store', ['goalId' => $goal->id, 'userId' => $candidate->id]) }}">
            @csrf
            <input type="hidden" name="q" value="{{ $search }}">
            <button type="submit" class="btn btn-outline-secondary btn-sm">Asignar Responsable</button>
          </form>
          @endif
        @endif
        @if($isReporter)
        <span class="badge badge-info align-self-center">Reportero</span>
        @else
        <form method="POST" action="{{ $isAdmin ? route('admin.goal-assignments.reporters.store', ['goalId' => $goal->id, 'userId' => $candidate->id]) : route('panel.goal-assignments.reporters.store', ['goalId' => $goal->id, 'userId' => $candidate->id]) }}">
          @csrf
          <input type="hidden" name="q" value="{{ $search }}">
          <button type="submit" class="btn btn-outline-primary btn-sm">Asignar Reportero</button>
        </form>
        @endif
      </span>
    </div>
    @empty
    <div class="list-group-item text-center text-muted">No se encontraron integrantes PFI.</div>
    @endforelse
  </div>
  {{ $candidates->links() }}
  @endif
</section>

@endsection
