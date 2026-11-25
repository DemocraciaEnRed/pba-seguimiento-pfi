@section('metatags')
  @include('objective.metatags')
@endsection

@extends('layouts.app')

@section('hero')
<x-hero :image="$objective->cover ? asset($objective->cover->path) : null" :accent="$objective->category?->color" size="md" class="objective-hero">
  <a href="{{ route('catalog') }}#eje-{{ $objective->category->id }}" class="badge badge-white text-black px-2 py-1 mb-2 is-size-6">
    <x-category-icon :icon="$objective->category->icon" style="color: {{$objective->category->color}}" /> {{ $objective->category->title }}
  </a>
  <p class="mb-1"><i class="fas fa-compass fa-fw"></i> {{ $objective->strategicObjective->title }}</p>
  <hr class="bg-white">
  <h1 class="hero__title">{{ $objective->title }}</h1>
  @if(!empty($objective->tags))
  <ul class="list-inline mt-3 mb-0">
    @foreach ($objective->tags as $tag)
    <li class="list-inline-item mb-1"><span class="badge hero__tag">#{{ $tag }}</span></li>
    @endforeach
  </ul>
  @endif
  @can('update', $objective)
  <x-slot:actions>
    <a href="{{ route('objectives.manage.index', ['objectiveId' => $objective->id]) }}" class="btn btn-white"><i class="fas fa-up-right-from-square fa-fw"></i> Panel objetivo</a>
  </x-slot:actions>
  @endcan
</x-hero>
@endsection

@section('content')
<div class="container py-5">
	<div class="row">
		<div class="col-12">
			<div class="card shadow-sm mb-4">
				<div class="card-body">
					<h2 class="h5 is-700 mb-3">Acerca del objetivo general</h2>
					<p>{!! nl2br(e($objective->content)) !!}</p>
					@if($objective->communities->isNotEmpty())
					<div class="mt-3">
						<p class="text-smaller text-muted mb-2">¡Unite a nuestra comunidad!</p>
						@foreach($objective->communities as $community)
						<a href="{{ $community->url }}" target="_blank" rel="noopener" class="py-1 px-2 text-smallest rounded d-inline-block my-1" style="border: 1px solid {{ $community->color }}; color: {{ $community->color }}"><i class="{{ $community->icon }}"></i>&nbsp;{{ $community->label }}</a>
						@endforeach
					</div>
					@endif
					@if(!$objective->files->isEmpty())
					<hr>
					<div class="clearfix is-clickable" data-toggle="collapse" data-target="#collapseFiles">
						<h5 class="is-700 h5 text-body my-2 float-left">Archivos</h5>
						<h5 class="is-700 h5 text-body my-2 float-right"><i class="fas fa-angle-down fa-lg"></i></h5>
					</div>
					<div id="collapseFiles" class="collapse">
						@foreach ($objective->files as $file)
						<div class="card my-2 shadow-sm">
							<div class="card-body p-2 pl-4 pr-4 d-flex">
								<div class="mr-3 mt-2"><i class="far fa-file fa-lg"></i></div>
								<div class="flex-fill">
									<p class="mb-0 text-smaller word-wrap-anywhere">{{ $file->name }}</p>
									<p class="text-card text-smaller text-muted mb-0">{{ $file->mime }}</p>
								</div>
								<div class="ml-3 mt-2">
									<a href="{{ asset($file->path) }}" class="card-link text-smaller" aria-label="Descargar {{ $file->name }}"><i class="fas fa-download fa-lg"></i></a>
								</div>
							</div>
						</div>
						@endforeach
					</div>
					@endif
				</div>
			</div>
			<section class="mb-4" aria-labelledby="objective-indicators">
				<h2 id="objective-indicators" class="h3 is-700 mb-3">Indicadores claves</h2>
				<portal-objective-stats fetch-url="{{route('apiService.objectives.stats',['objectiveId' => $objective->id])}}">
					@include('partials.loading')
				</portal-objective-stats>
			</section>
			@if($objective->goals->isNotEmpty())
			{{-- El padding inferior del carrusel completa la separación entre secciones. --}}
			<x-card-carousel id="objective-goals" label="Metas" :color="$objective->category->color" class="mb-1 objective-cards">
				<x-slot:header><h2 class="h3 is-700 mb-0">Metas</h2></x-slot:header>
				@foreach($objective->goals as $goal)
				@php
					$summary = $goal->indicatorSummary();
					$light = \App\Services\Indicators\TrafficLight::fromCompliance($summary?->toDateCompliance);
					$lastReport = $goal->reports->first();
				@endphp
				<li class="initiatives-carousel__item">
					<article class="card shadow-sm h-100">
						<div class="card-body d-flex flex-column">
							<span class="text-smaller text-{{ $goal->status }} mb-2"><i class="fas fa-circle fa-fw" aria-hidden="true"></i> {{ $goal->status_label }}</span>
							<h3 class="h5 is-700"><a href="{{ route('goals.index', ['goalId' => $goal->id]) }}">{{ $goal->title }}</a></h3>
							@if($goal->acceptsProgressReports())
							<p class="text-smaller mb-2">{{ $goal->indicator }} <span class="text-muted">({{ $goal->indicator_unit }})</span></p>
							@if($goal->isSimple())
							<dl class="text-smaller mb-3">
								<dt>Valor actual / Meta</dt>
								<dd>{{ indicator_number($goal->indicator_progress) }} / {{ indicator_number($goal->indicator_goal) }}</dd>
								<dt>Progreso</dt>
								<dd>{{ $goal->progress_label }}</dd>
							</dl>
							@else
							<dl class="text-smaller mb-3">
								<dt>Cumplimiento a la fecha</dt>
								<dd class="text-{{ $light?->bootstrapColor() ?? 'muted' }}">{{ indicator_percentage($summary->toDateCompliance) }} · {{ $light?->label() ?? 'Sin medición' }}</dd>
								<dt>Resultado / Meta a la fecha</dt>
								<dd>{{ indicator_number($summary->toDateMeasured) }} / {{ indicator_number($summary->toDateTarget) }}</dd>
							</dl>
							@endif
							@else
							<p class="text-smaller text-muted">Sin indicador numérico</p>
							@endif
							<div class="mt-auto pt-3 border-top">
								<p class="text-smaller text-muted mb-2">{{ $goal->reports_count }} reportes publicados</p>
								<h4 class="h6 is-700">Último reporte publicado</h4>
								@if($lastReport)
								<a class="text-smaller" href="{{ route('reports.index', ['reportId' => $lastReport->id]) }}">{{ $lastReport->title }}</a>
								<p class="text-smallest text-muted mb-0">@justdate($lastReport->date)</p>
								@else
								<p class="text-smaller text-muted mb-0">Sin reportes publicados</p>
								@endif
							</div>
						</div>
					</article>
				</li>
				@endforeach
			</x-card-carousel>
			@else
			<section class="mb-4">
				<h2 class="h3 is-700 mb-3">Metas</h2>
				<p class="text-muted">No hay metas disponibles.</p>
			</section>
			@endif
			@if($organizations->isNotEmpty())
			<x-card-carousel id="objective-organizations" label="Organizaciones participantes" :color="$objective->category->color" class="mb-1 objective-cards">
				<x-slot:header><h2 class="h3 is-700 mb-0">Organizaciones participantes</h2></x-slot:header>
				@foreach($organizations as $organization)
				<li class="initiatives-carousel__item">
					<article class="card shadow-sm h-100">
						<div class="card-body">
							<img src="{{ $organization->logo ? asset($organization->logo->path) : asset('img/default-organization.png') }}" alt="" width="64" height="64" class="rounded mb-3" style="object-fit: contain">
							<h3 class="h5 is-700">{{ $organization->name }}</h3>
							<p class="text-smaller text-muted mb-0">{{ $organization->description }}</p>
						</div>
					</article>
				</li>
				@endforeach
			</x-card-carousel>
			@endif
			<section id="reportes">
				<h2 class="h3 is-700 mb-3">Reportes</h2>
				<search-reports fetch-url="{{route('apiService.reports')}}" :fixed-objective="{{ $objective->id }}">
					@include('partials.loading')
				</search-reports>
			</section>
		</div>
	</div>
</div>
@endsection
