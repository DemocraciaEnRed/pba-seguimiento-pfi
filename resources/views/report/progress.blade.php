@php
  use App\Services\Indicators\TrafficLight;
@endphp
@if($report->type == 'progress' && $goal->isPeriodic() && $report->period)
  @php
    $summary = $goal->indicatorSummary();
    $result = $summary->period($report->period->number);
    $light = TrafficLight::fromCompliance($result?->compliance);
    $toDateLight = TrafficLight::fromCompliance($summary->toDateCompliance);
  @endphp
  <div class="card shadow-sm my-3">
    <div class="card-body p-3 p-lg-5">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div class="animate__animated animate__flipInX">
          <h4 class="is-700 m-0">Medición del {{ $report->period->label() }}</h4>
          <p class="mb-0 mt-1 text-muted">{{ $report->period->rangeLabel() }} · {{ $goal->indicator_direction->label() }}</p>
        </div>
        <h3 class="is-700 ml-2 mb-0 text-{{ $light?->bootstrapColor() ?? 'muted' }} animate__animated animate__bounceIn animate__delay-1s">
          {{ indicator_percentage($result?->compliance) }}
        </h3>
      </div>
      <div class="row text-center">
        <div class="col-6 col-md-3 my-2">
          <h6 class="is-700 mb-1">Objetivo del período</h6>
          <span>{{ indicator_number($result?->target) }} {{ $goal->indicator_unit }}</span>
        </div>
        <div class="col-6 col-md-3 my-2">
          <h6 class="is-700 mb-1">Valor medido</h6>
          <span>{{ indicator_number($report->measured_value) }} {{ $goal->indicator_unit }}</span>
        </div>
        <div class="col-6 col-md-3 my-2">
          <h6 class="is-700 mb-1">Desvío</h6>
          <span class="text-{{ $light?->bootstrapColor() ?? 'muted' }}">{{ indicator_percentage($result?->relativeDeviation, signed: true) }}</span>
          @if($light)<small class="d-block text-muted">{{ $light->label() }}</small>@endif
        </div>
        <div class="col-6 col-md-3 my-2">
          <h6 class="is-700 mb-1">Diferencia</h6>
          @if($result?->absoluteDeviation !== null)
            <span>{{ $result->absoluteDeviation > 0 ? '+' : '' }}{{ indicator_number($result->absoluteDeviation) }} {{ $goal->indicator_unit }}</span>
          @else
            <span>—</span>
          @endif
        </div>
      </div>
      <hr>
      <p class="mb-0 text-muted">
        Cumplimiento de la meta a la fecha:
        <b class="text-{{ $toDateLight?->bootstrapColor() ?? 'muted' }}">{{ indicator_percentage($summary->toDateCompliance) }}</b>
        <a href="{{ route('goals.index', ['goalId' => $goal->id]) }}" class="ml-1">Ver todos los períodos <i class="fas fa-arrow-right"></i></a>
      </p>
    </div>
  </div>
@elseif($report->type == 'progress' && $goal->isSimple())
  <div class="card shadow-sm my-3">
    <div class="card-body p-3 p-lg-5 d-flex justify-content-between align-items-center">
      <div class="align-self-center animate__animated animate__flipInX">
        <h4 class="is-700 m-0">Progreso declarado</h4>
        <p class="mb-0 mt-1">Unidad del indicador: {{$goal->indicator_unit}}</p>
        <p class="mb-0 mt-1">Al momento de publicar el reporte, la meta pasó de {{ indicator_number($report->previous_progress ?: 0) }} a {{ indicator_number($report->previous_progress + $report->progress) }}</p>
      </div>
      <h3 class="is-700 text-info ml-2 mb-0 align-self-center animate__animated animate__bounceIn animate__delay-1s">{{ indicator_number($report->progress) }}</h3>
    </div>
  </div>
@endif
