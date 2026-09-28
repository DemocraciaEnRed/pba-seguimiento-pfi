@section('metatags')
  @include('objective.goal.metatags')
@endsection

@extends('layouts.app')

@section('hero')
<x-hero :image="$objective->cover ? asset($objective->cover->path) : null" :accent="$objective->category?->color" class="goal-hero">
  @include('partials.hierarchy-breadcrumb', ['objective' => $objective])
  <hr class="bg-white">
  <span class="badge badge-white text-black px-2 py-1 mt-2 mb-2 is-size-6"><i class="far fa-circle-dot fa-fw text-{{$goal->status}}"></i> Meta {{$goal->status_label}}</span>
  <h1 class="hero__title">{{ $goal->title }}</h1>
  @isMember($objective->id)
  <x-slot:actions>
    <a href="{{route('objectives.manage.goals.reports.add',['objectiveId'=> $objective->id, 'goalId' => $goal->id])}}" class="btn btn-white"><i class="fas fa-plus fa-fw"></i> Nuevo reporte</a>
    @isManager($objective->id)
    <a href="{{route('objectives.manage.goals.index',['objectiveId'=> $objective->id, 'goalId' => $goal->id])}}" class="btn btn-outline-white"><i class="fas fa-up-right-from-square fa-fw"></i> Panel meta</a>
    @endisManager
  </x-slot:actions>
  @endisMember
</x-hero>
@endsection

@section('content')
<div class="container py-5">
	<div class="row justify-content-center">
		<div class="col-md-4">
			@include('objective.menu')
		</div>
		<div class="col-md-8">
			{{-- @include('objective.subscribe') --}}
			<div class="card shadow-sm mb-3">
				<div class="card-body p-3">
          @if($goal->isSimple())
          <div class="row my-2">
            <div class="col-md-6">
					    <h6 class="is-700">Calculo del indicador</h6>
					    <p>{{$goal->indicator}}</p>
            </div>
            <div class="col-md-6">
					    <h6 class="is-700">Progreso</h6>
					   <div class="my-1 d-flex justify-content-between align-items-center goal-container">
								<div class="progress my-0 mx-1 w-100" style="height: 10px;">
									<div class="progress-bar bg-{{$goal->status}}" role="progressbar" style="width: {{ min(100, $goal->progress_percentage ?? 0) }}%" aria-valuenow="{{$goal->progress_percentage}}" aria-valuemin="0" aria-valuemax="100"></div>
								</div>
								<span class="goal-percentage text-smallest is-700 ml-1">{{$goal->progress_label}}</span>
							</div>
            </div>
          </div>
          <div class="row my-2">
            <div class="col-md-6">
					    <h6 class="is-700">Unidad del indicador</h6>
					    <p>{{$goal->indicator_unit}}</p>
            </div>
            <div class="col-md-6">
					    <h6 class="is-700">Frecuencia del indicador</h6>
					    <p>{{$goal->indicator_frequency}}</p>
            </div>
          </div>
          <div class="row my-2">
            <div class="col-md-6">
					    <h6 class="is-700">Valor a alcanzar</h6>
					    <p>{{ indicator_number($goal->indicator_goal) }}</p>
            </div>
            <div class="col-md-6">
					    <h6 class="is-700">Valor actual</h6>
					    <p>{{ indicator_number($goal->indicator_progress) }}</p>
            </div>
          </div>
          @elseif($goal->isPeriodic())
          <div class="row my-2">
            <div class="col-md-6">
					    <h6 class="is-700">Indicador</h6>
					    <p>{{$goal->indicator}} <small class="text-muted">({{$goal->indicator_unit}})</small></p>
            </div>
            <div class="col-md-6">
					    <h6 class="is-700">Lectura</h6>
					    <p>{{ $goal->indicator_direction->label() }}</p>
            </div>
          </div>
          @include('partials.indicatorPeriods', ['goal' => $goal, 'summary' => $goal->indicatorSummary()])
          @endif
					@if($goal->source)
          <div class="my-2">
					    <h6 class="is-700">Fuente</h6>
					    <p>{{$goal->source}}</p>
          </div>
					@endif
					@if(!$goal->milestones->isEmpty())
          <hr>
					<div class="clearfix is-clickable" data-toggle="collapse" data-target="#collapseMilestones">
						<h5 class="is-700 h5 text-body my-2 float-left">Hitos de la meta</h5>
						<h5 class="is-700 h5 text-body my-2 float-right"><i class="fas fa-angle-down"></i></h5>
					</div>
					<div id="collapseMilestones" class="collapse">
						@forelse ($goal->milestones as $milestone)
							<p>
								<span class="text-muted">Hito #{{$milestone->order}} - </span><span class="is-700">{{$milestone->title}}</span><br/>
								<span class="text-smallest text-muted">
								 	@if(is_null($milestone->completed))
                  <i class="text-danger fas fa-xmark fa-fw"></i>
                  No completado
                  @else
                  <i class="text-success fas fa-check"></i>
                  Completado -
									<span class="text-muted">Fecha de completado: @justdate($milestone->completed) - <a href="{{route('reports.index',['reportId' => $milestone->report->id])}}">Ver el reporte<i class="fas fa-arrow-right fa-fw"></i></a></span>
                  @endif
								</span>
							</p>
							@empty
							<p class="my-2 text-muted">No hay hitos asociados</p>
							@endforelse
					</div>
					@endif
          <hr>
					<h5 class="is-700 mt-2 mb-4">Reportes</h5>
					<report-list fetch-url="{{route('apiService.goals.reports',['goalId'=> $goal->id, 'size' => 3, 'with' =>'report_hierarchy,report_cover,report_excerpt,report_highlights', 'order_by'=>'date,DESC'])}}" context="none">
						@include('partials.loading')
					</report-list>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
