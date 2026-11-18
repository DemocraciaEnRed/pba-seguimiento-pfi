@extends('objective.manage.goals.master')

@section('panelContent')
  <h3 class="is-700">Resumen de la meta</h3>
  <p class="lead">Avance, indicador y actividad de la meta.</p>
  @php
    $milestonesCompleted = $goal->milestones->whereNotNull('completed')->count();
  @endphp
  <div class="d-grid grid-cols-2 grid-cols-md-4 gap-2 mb-3">
    <x-stat label="Estado" :value="$goal->status_label" icon="far fa-circle-dot" :tone="$goal->status" :hint="$goal->isSimple() ? 'Progreso '.$goal->progress_label : null" />
    <x-stat label="Reportes" :value="$goal->reports->count()" icon="far fa-file-lines" tone="primary" :href="route('objectives.manage.goals.reports', ['objectiveId' => $objective->id, 'goalId' => $goal->id])" />
    <x-stat label="Hitos" :value="$milestonesCompleted.' / '.$goal->milestones->count()" icon="far fa-star" tone="warning" hint="completados" :href="route('objectives.manage.goals.milestones', ['objectiveId' => $objective->id, 'goalId' => $goal->id])" />
    <x-stat label="Comentarios" :value="$commentsCount" icon="far fa-comments" tone="secondary" :href="route('objectives.manage.goals.comments', ['objectiveId' => $objective->id, 'goalId' => $goal->id])" />
  </div>
  @if($goal->isSimple())
  <div class="progress mb-3">
    <div class="progress-bar bg-{{$goal->status}}" role="progressbar" style="width: {{ min(100, $goal->progress_percentage ?? 0) }}%;" aria-valuenow="{{$goal->progress_percentage}}" aria-valuemin="0" aria-valuemax="100">{{$goal->progress_label}}</div>
  </div>
  @endif
  <dl class="d-grid grid-cols-1 grid-cols-md-2 gap-2 mb-3">
    <div>
      <dt class="text-smaller text-muted">Indicador</dt>
      <dd class="mb-0">{{$goal->indicator ?: 'Meta sin indicador numérico'}}</dd>
    </div>
    <div>
      <dt class="text-smaller text-muted">Unidad del indicador</dt>
      <dd class="mb-0">{{$goal->indicator_unit ?: '—'}}</dd>
    </div>
    @if($goal->isSimple())
    <div>
      <dt class="text-smaller text-muted">Valor a alcanzar</dt>
      <dd class="mb-0">{{ indicator_number($goal->indicator_goal) }}</dd>
    </div>
    <div>
      <dt class="text-smaller text-muted">Valor actual</dt>
      <dd class="mb-0">{{ indicator_number($goal->indicator_progress) }} <small class="text-muted">({{$goal->progress_label}})</small></dd>
    </div>
    <div>
      <dt class="text-smaller text-muted">Frecuencia</dt>
      <dd class="mb-0">{{$goal->indicator_frequency ?: '—'}}</dd>
    </div>
    @elseif($goal->isPeriodic())
    <div>
      <dt class="text-smaller text-muted">Lectura</dt>
      <dd class="mb-0">{{ $goal->indicator_direction->label() }}</dd>
    </div>
    @endif
    <div>
      <dt class="text-smaller text-muted">Organización responsable</dt>
      <dd class="mb-0">{{ $goal->responsibleOrganization?->name ?? 'Sin organización responsable' }}</dd>
    </div>
    <div>
      <dt class="text-smaller text-muted">Organizaciones participantes</dt>
      <dd class="mb-0">{{ $goal->participantOrganizations->pluck('name')->join(', ') ?: 'Sin organizaciones participantes' }}</dd>
    </div>
  </dl>
  @if($goal->isPeriodic())
  <h5 class="is-700">Seguimiento por períodos</h5>
  @include('partials.indicatorPeriods', ['goal' => $goal, 'summary' => $goal->indicatorSummary(), 'objective' => $objective, 'canSkip' => Auth::user()->hasRole('admin'), 'reportLinks' => 'panel'])
  <br>
  @endif
  <h5 class="is-700">Fuente</h5>
  @if ($goal->source)
    <p>{{$goal->source}}</p>
  @else
    <p class="text-muted">Sin información cargada</p>
  @endif
  @if($goal->milestones->isEmpty())
  <div class="card border-secondary my-2">
    <div class="card-body">
      <h6><b><i class="fas fa-circle-info"></i>&nbsp;La meta no cuenta con hitos</b></h6>
      @can('update', $goal)
      <span class="text-muted">Puede crearlos haciendo <a href="{{ route('objectives.manage.goals.milestones.add', ['objectiveId' => $objective->id,'goalId' => $goal->id]) }}">click aquí <i class="fas fa-arrow-right"></i></a></span>
      @endcan
    </div>
  </div>
  @endif
  @if($goal->reports->isEmpty())
  <div class="card border-secondary my-2">
    <div class="card-body">
      <h6><b><i class="fas fa-circle-info"></i>&nbsp;La meta no cuenta con reportes</b></h6>
      @can('create', [\App\Report::class, $goal])
      <span class="text-muted">Puede crearlos haciendo <a href="{{ route('objectives.manage.goals.reports.add', ['objectiveId' => $objective->id,'goalId' => $goal->id]) }}">click aquí <i class="fas fa-arrow-right"></i></a></span>
      @endcan
    </div>
  </div>
  @endif
  @include('objective.manage.partials.latest-comments', [
    'listUrl' => route('objectives.manage.goals.comments', ['objectiveId' => $objective->id, 'goalId' => $goal->id]),
    'showGoal' => false,
  ])
@endsection
