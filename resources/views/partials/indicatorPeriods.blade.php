@php
  use App\Services\Indicators\Nature;
  use App\Services\Indicators\PeriodState;
  use App\Services\Indicators\TrafficLight;

  $canSkip = $canSkip ?? false;
  $toDateLight = TrafficLight::fromCompliance($summary->toDateCompliance);
  $stateColors = [
    PeriodState::Reported->value => 'primary',
    PeriodState::Overdue->value => 'danger',
    PeriodState::Open->value => 'info',
    PeriodState::Upcoming->value => 'light',
    PeriodState::Skipped->value => 'secondary',
    PeriodState::NoTarget->value => 'light',
  ];
@endphp

<div class="row mb-3">
  <div class="col-md-4 mb-2">
    <div class="card h-100 border-{{ $toDateLight?->bootstrapColor() ?? 'light' }}">
      <div class="card-body text-center">
        <h6 class="card-subtitle text-muted mb-2">Cumplimiento a la fecha</h6>
        <h3 class="is-700 text-{{ $toDateLight?->bootstrapColor() ?? 'muted' }} mb-1">{{ indicator_percentage($summary->toDateCompliance) }}</h3>
        <small class="text-muted">Desvío {{ indicator_percentage($summary->toDateRelativeDeviation(), signed: true) }}</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card h-100 border-light">
      <div class="card-body text-center">
        <h6 class="card-subtitle text-muted mb-2">
          {{ $goal->indicator_nature === Nature::Intensive ? 'Promedio a la fecha' : 'Resultado a la fecha' }}
        </h6>
        <h3 class="is-700 mb-1">{{ indicator_number($summary->toDateMeasured) }} <small class="text-muted">{{ $goal->indicator_unit }}</small></h3>
        <small class="text-muted">Objetivo a la fecha: {{ indicator_number($summary->toDateTarget) }} {{ $goal->indicator_unit }}</small>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card h-100 border-light">
      <div class="card-body text-center">
        @if($summary->windowProgress !== null)
          <h6 class="card-subtitle text-muted mb-2">Avance sobre la meta total</h6>
          <h3 class="is-700 mb-1">{{ indicator_percentage($summary->windowProgress) }}</h3>
          <div class="progress" style="height: .5rem">
            <div class="progress-bar bg-info" role="progressbar" style="width: {{ min(100, max(0, round($summary->windowProgress * 100))) }}%"></div>
          </div>
          <small class="text-muted">Meta total: {{ indicator_number($summary->windowTarget) }} {{ $goal->indicator_unit }}</small>
        @else
          <h6 class="card-subtitle text-muted mb-2">Ventana de monitoreo</h6>
          <h3 class="is-700 mb-1">{{ $goal->period_count }} <small class="text-muted">períodos</small></h3>
          <small class="text-muted">{{ $goal->period_type->label() }} desde {{ $goal->period_start->translatedFormat('F Y') }}</small>
        @endif
      </div>
    </div>
  </div>
</div>

@if($summary->overdueCount > 0)
<div class="alert alert-danger">
  <i class="fas fa-triangle-exclamation fa-fw"></i>&nbsp;Hay {{ $summary->overdueCount }} {{ $summary->overdueCount === 1 ? 'período vencido' : 'períodos vencidos' }} sin informar. Cuentan como 0% de cumplimiento hasta que se informen.
</div>
@endif

<div class="table-responsive">
  <table class="table table-sm table-hover">
    <thead class="thead-light">
      <tr>
        <th>Período</th>
        <th class="text-right">Objetivo</th>
        <th class="text-right">Medido</th>
        <th class="text-right">Cumplimiento</th>
        <th class="text-right">Desvío</th>
        <th class="text-right">Diferencia</th>
        <th>Estado</th>
        @if($canSkip)<th></th>@endif
      </tr>
    </thead>
    <tbody>
      @foreach($goal->periods as $period)
        @php
          $result = $summary->period($period->number);
          $light = TrafficLight::fromCompliance($result->compliance);
        @endphp
        <tr>
          <td>
            {{ $period->label() }}
            <small class="d-block text-muted">{{ $period->rangeLabel() }}</small>
          </td>
          <td class="text-right align-middle">{{ indicator_number($result->target) }}</td>
          <td class="text-right align-middle">{{ indicator_number($result->measured) }}</td>
          <td class="text-right align-middle is-700 text-{{ $light?->bootstrapColor() ?? 'muted' }}">
            @if($light)<i class="fas fa-circle fa-xs"></i>&nbsp;@endif{{ indicator_percentage($result->compliance) }}
          </td>
          <td class="text-right align-middle">{{ indicator_percentage($result->relativeDeviation, signed: true) }}</td>
          <td class="text-right align-middle">
            @if($result->absoluteDeviation !== null)
              {{ $result->absoluteDeviation > 0 ? '+' : '' }}{{ indicator_number($result->absoluteDeviation) }} <small class="text-muted">{{ $goal->indicator_unit }}</small>
            @else
              —
            @endif
          </td>
          <td class="align-middle">
            <span class="badge badge-{{ $stateColors[$result->state->value] }}">{{ $result->state->label() }}</span>
            @if($period->isSkipped())
              <small class="d-block text-muted" title="Omitido por {{ $period->skippedBy?->fullname }}">{{ $period->skip_reason }}</small>
            @endif
          </td>
          @if($canSkip)
          <td class="align-middle text-right">
            @if($period->isSkipped())
              <form method="POST" action="{{ route('objectives.manage.goals.periods.unskip.form', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'periodId' => $period->id]) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link btn-sm p-0">Revertir omisión</button>
              </form>
            @elseif(in_array($result->state, [PeriodState::Overdue, PeriodState::Open, PeriodState::Upcoming], true))
              <form method="POST" class="form-inline justify-content-end flex-nowrap" action="{{ route('objectives.manage.goals.periods.skip.form', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'periodId' => $period->id]) }}">
                @csrf
                <input type="text" name="skip_reason" class="form-control form-control-sm mr-1" placeholder="Motivo" required maxlength="1000">
                <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">Omitir</button>
              </form>
            @endif
          </td>
          @endif
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

@if($goal->indicator_nature === Nature::Intensive)
<small class="text-muted d-block">El valor a la fecha es el promedio simple de los períodos informados; no se pondera por la cantidad de casos de cada período.</small>
@endif
@if($goal->indicator_formula)
<small class="text-muted d-block"><b>Fórmula:</b> {{ $goal->indicator_formula }}</small>
@endif
