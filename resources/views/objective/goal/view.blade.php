@section('metatags')
  @include('objective.goal.metatags')
@endsection

@extends('layouts.app')

@section('hero')
<x-hero :image="$objective->cover ? asset($objective->cover->path) : null" :accent="$objective->category?->color" class="goal-hero">
  <a href="{{ route('catalog') }}#eje-{{ $objective->category->id }}" class="badge badge-white text-black px-2 py-1 mb-2 is-size-6">
    <x-category-icon :icon="$objective->category->icon" style="color: {{ $objective->category->color }}" /> {{ $objective->category->title }}
  </a>
  <p class="mb-1"><i class="far fa-circle-dot fa-fw"></i> Meta</p>
  <hr class="bg-white">
  <h1 class="hero__title">{{ $goal->title }}</h1>
  @can('accessManagement', $objective)
  <x-slot:actions>
    @can('create', [\App\Report::class, $goal])
    <a href="{{route('objectives.manage.goals.reports.add',['objectiveId'=> $objective->id, 'goalId' => $goal->id])}}" class="btn btn-white"><i class="fas fa-plus fa-fw"></i> Nuevo reporte</a>
    @endcan
    <a href="{{route('objectives.manage.goals.index',['objectiveId'=> $objective->id, 'goalId' => $goal->id])}}" class="btn btn-outline-white"><i class="fas fa-up-right-from-square fa-fw"></i> Panel meta</a>
  </x-slot:actions>
  @endcan
</x-hero>
@endsection

@section('content')
<div class="container py-5">
	<div class="row justify-content-center">
		<div class="col-md-4">
			@include('objective.menu')
		</div>
		<div class="col-md-8">
			<section class="mb-4" aria-labelledby="goal-about">
				<h2 id="goal-about" class="h3 is-700 mb-3">Acerca de la meta</h2>
				<div class="row">
					<div class="col-sm-6 mb-3">
						<x-goal-fact label="Estado declarado" icon="far fa-circle-dot">
							<p class="is-700 text-{{ $goal->status }} mb-0">{{ $goal->status_label }}</p>
						</x-goal-fact>
					</div>
					<div class="col-sm-6 mb-3">
						<x-goal-fact label="Modo de seguimiento" icon="fas fa-chart-line">
							<p class="is-700 mb-0">{{ $goal->measurement_mode->label() }}</p>
						</x-goal-fact>
					</div>
				</div>
				<div class="card shadow-sm">
					<div class="card-body">
						@include('partials.hierarchy', ['objective' => $objective, 'goal' => null])
					</div>
				</div>
			</section>
			@if($goal->responsibleOrganization || $goal->participantOrganizations->isNotEmpty())
			<section class="mb-4" aria-labelledby="goal-organizations">
				<h2 id="goal-organizations" class="h3 is-700 mb-3">Organizaciones</h2>
				<div class="card shadow-sm">
					<div class="card-body">
						@if($goal->responsibleOrganization)
						<h3 class="h6 is-700">Organización responsable</h3>
						<p class="{{ $goal->participantOrganizations->isNotEmpty() ? 'mb-3' : 'mb-0' }}"><i class="far fa-building fa-fw text-muted"></i> <b>{{ $goal->responsibleOrganization->name }}</b></p>
						@endif
						@if($goal->participantOrganizations->isNotEmpty())
						<h3 class="h6 is-700">Organizaciones participantes</h3>
						<ul class="list-unstyled text-smaller mb-0">
							@foreach($goal->participantOrganizations as $organization)
							<li><i class="far fa-building fa-fw text-muted"></i> {{ $organization->name }}</li>
							@endforeach
						</ul>
						@endif
					</div>
				</div>
			</section>
			@endif
			<section class="mb-4" aria-labelledby="goal-indicator">
				<h2 id="goal-indicator" class="h3 is-700 mb-3">Indicador y seguimiento</h2>
				@if($goal->isSimple())
				<div class="row">
					<div class="col-12 mb-3">
						<x-goal-fact label="Indicador" icon="fas fa-bullseye">
							<p class="is-700 mb-3">{{ $goal->indicator }}</p>
							<div class="d-flex justify-content-between align-items-center goal-container">
								<div class="progress my-0 mr-2 w-100" style="height: 10px;">
									<div class="progress-bar bg-{{ $goal->status }}" role="progressbar" style="width: {{ min(100, $goal->progress_percentage ?? 0) }}%" aria-valuenow="{{ $goal->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
								</div>
								<span class="goal-percentage text-smallest is-700">{{ $goal->progress_label }}</span>
							</div>
						</x-goal-fact>
					</div>
					<div class="col-sm-6 col-lg-3 mb-3">
						<x-goal-fact label="Valor actual">
							<p class="h4 is-700 mb-0">{{ indicator_number($goal->indicator_progress) }}</p>
						</x-goal-fact>
					</div>
					<div class="col-sm-6 col-lg-3 mb-3">
						<x-goal-fact label="Valor a alcanzar">
							<p class="h4 is-700 mb-0">{{ indicator_number($goal->indicator_goal) }}</p>
						</x-goal-fact>
					</div>
					<div class="col-sm-6 col-lg-3 mb-3">
						<x-goal-fact label="Unidad del indicador">
							<p class="mb-0">{{ $goal->indicator_unit }}</p>
						</x-goal-fact>
					</div>
					<div class="col-sm-6 col-lg-3 mb-3">
						<x-goal-fact label="Frecuencia del indicador">
							<p class="mb-0">{{ $goal->indicator_frequency }}</p>
						</x-goal-fact>
					</div>
				</div>
				@elseif($goal->isPeriodic())
				<div class="row">
					<div class="col-md-8 mb-3">
						<x-goal-fact label="Indicador" icon="fas fa-bullseye">
							<p class="is-700 mb-0">{{ $goal->indicator }} <small class="text-muted">({{ $goal->indicator_unit }})</small></p>
						</x-goal-fact>
					</div>
					<div class="col-md-4 mb-3">
						<x-goal-fact label="Lectura" icon="fas fa-arrow-trend-up">
							<p class="mb-0">{{ $goal->indicator_direction->label() }}</p>
						</x-goal-fact>
					</div>
					@if($goal->indicator_formula)
					<div class="col-12 mb-3">
						<x-goal-fact label="Fórmula de cálculo" icon="fas fa-calculator">
							<p class="word-wrap-anywhere mb-0">{!! nl2br(e($goal->indicator_formula)) !!}</p>
						</x-goal-fact>
					</div>
					@endif
				</div>
				<div class="card shadow-sm mb-3">
					<div class="card-body">
						@include('partials.indicatorPeriods', ['goal' => $goal, 'summary' => $summary])
					</div>
				</div>
				@else
				<div class="card shadow-sm mb-3">
					<div class="card-body">
						<p class="text-muted mb-0">Esta meta se sigue mediante novedades e hitos, sin un indicador numérico.</p>
					</div>
				</div>
				@endif
				@if($goal->source)
				<x-goal-fact label="Fuente" icon="fas fa-database" class="h-auto">
					<p class="mb-0">{{ $goal->source }}</p>
				</x-goal-fact>
				@endif
			</section>
			@if($goal->milestones->isNotEmpty())
			<section class="mb-4" aria-labelledby="goal-milestones">
				<h2 id="goal-milestones" class="h3 is-700 mb-3">Hitos de la meta</h2>
				<div class="card shadow-sm">
					<div class="card-body">
						<button class="btn btn-link text-body is-700 p-0 text-left" type="button" data-toggle="collapse" data-target="#collapseMilestones" aria-controls="collapseMilestones" aria-expanded="false">
							Ver {{ $goal->milestones->count() }} {{ $goal->milestones->count() === 1 ? 'hito' : 'hitos' }} <i class="fas fa-angle-down ml-2" aria-hidden="true"></i>
						</button>
						<div id="collapseMilestones" class="collapse mt-3">
							@foreach ($goal->milestones as $milestone)
							<p>
								<span class="text-muted">Hito #{{$milestone->order}} - </span><span class="is-700">{{$milestone->title}}</span><br/>
								<span class="text-smallest text-muted">
									@if(is_null($milestone->completed))
									<i class="text-danger fas fa-xmark fa-fw"></i>
									No completado
									@else
									<i class="text-success fas fa-check"></i>
									Completado -
									<span class="text-muted">Fecha de completado: @justdate($milestone->completed)
									@if($milestone->report)
									 - <a href="{{ $isPublic ? route('reports.index', ['reportId' => $milestone->report->id]) : route('objectives.manage.goals.reports.index', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $milestone->report->id]) }}">Ver el reporte<i class="fas fa-arrow-right fa-fw"></i></a>
									@endif
									</span>
									@endif
								</span>
							</p>
							@endforeach
						</div>
					</div>
				</div>
			</section>
			@endif
		</div>
	</div>
	<section id="reportes">
		<h2 class="h3 is-700 mb-3">Reportes</h2>
		<search-reports fetch-url="{{ $reportsFetchUrl }}" :fixed-goal="{{ $goal->id }}">
			@include('partials.loading')
		</search-reports>
	</section>
</div>
@endsection
