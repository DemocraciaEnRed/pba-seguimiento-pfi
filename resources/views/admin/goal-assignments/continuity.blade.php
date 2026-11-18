@extends($isAdmin ? 'admin.master' : 'panel.master')

@section($isAdmin ? 'adminContent' : 'panelContent')
<section>
  <a href="{{ $backUrl }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver</a>
  <h3 class="is-700 mt-3">Retirar a {{ $member->fullname }} como {{ $roleLabel }}</h3>
  <p>La baja revoca sus permisos por esta asignación. La autoría histórica de los reportes no cambia.</p>
  @if($roleLabel === 'integrante PFI')
  <p>Se quitarán todas sus asignaciones por meta y sus categorías PFI. Los roles generales de la cuenta no se modifican.</p>
  @endif
  @if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif
  @foreach($coverage as $goal)
    @php($remaining = $goal->responsibles->where('id', '!=', $member->id)->count())
    <div class="alert {{ $remaining === 0 ? 'alert-warning' : 'alert-info' }}">
      {{ $goal->title }}: {{ $remaining }} Responsable(s) restante(s).
      @if($remaining === 0)
      La meta quedará sin Responsables. Considerá asignar a alguien para mantener la cobertura; no se exige reemplazo para confirmar.
      @endif
    </div>
  @endforeach
  <form method="POST" action="{{ $action }}">
    @csrf
    @method('DELETE')
    @forelse($reports as $report)
    <div class="card mb-3">
      <div class="card-body">
        <h5>{{ $report->title }}</h5>
        <p class="text-smaller text-muted">{{ $report->goal->objective->title }} / {{ $report->goal->title }} · {{ $report->review_status_label }}</p>
        <label for="destination-{{ $report->id }}">Quién continuará este pendiente</label>
        <select id="destination-{{ $report->id }}" name="destinations[{{ $report->id }}]" class="form-control" required>
          <option value="">Elegir Reportero asignado</option>
          @foreach($report->goal->reporters as $candidate)
            @if($candidate->id !== $member->id && $candidate->isPfiMember())
            <option value="{{ $candidate->id }}" @selected((string) old('destinations.'.$report->id) === (string) $candidate->id)>{{ $candidate->fullname }}</option>
            @endif
          @endforeach
        </select>
        <p class="text-smaller mt-2 mb-0">Si falta una persona, <a href="{{ route($isAdmin ? 'admin.goal-assignments.goal' : 'panel.goal-assignments.goal', ['goalId' => $report->goal_id]) }}">asigná primero un Reportero del universo PFI a esta meta</a> y volvé a esta confirmación.</p>
      </div>
    </div>
    @empty
    <p>No hay pendientes que requieran reasignación por esta baja.</p>
    @endforelse
    <button class="btn btn-danger" type="submit">Confirmar baja y continuidad</button>
  </form>
</section>
@endsection
