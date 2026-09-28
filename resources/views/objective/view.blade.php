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
  @isManager($objective->id)
  <x-slot:actions>
    <a href="{{ route('objectives.manage.index', ['objectiveId' => $objective->id]) }}" class="btn btn-white"><i class="fas fa-up-right-from-square fa-fw"></i> Panel objetivo</a>
  </x-slot:actions>
  @endisManager
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
			<portal-objective-stats fetch-url="{{route('apiService.objectives.stats',['objectiveId' => $objective->id])}}">
				@include('partials.loading')
			</portal-objective-stats>
			<div class="card shadow-sm">
				<div class="card-body">
					<h5 class="is-700 mb-3">Sobre el objetivo</h5>
					<p>{!! nl2br(e($objective->content)) !!}</p>
					@if(!$objective->organizations->isEmpty())
					<hr>
					<div class="clearfix is-clickable" data-toggle="collapse" data-target="#collapseOrganizations">
						<h5 class="is-700 h5 text-body my-2 float-left">Organizaciones</h5>
						<h5 class="is-700 h5 text-body my-2 float-right"><i class="fas fa-angle-down fa-lg"></i></h5>
					</div>
					<div id="collapseOrganizations" class="collapse show">
						<objective-organizations-carrousel :slides='@json($objective->organizations)'>
						</objective-organizations-carrousel>
					</div>
					@endif
					@if(!$objective->members->isEmpty())
					<hr>
					<div class="clearfix is-clickable" data-toggle="collapse" data-target="#collapseMembers">
						<h5 class="is-700 h5 text-body my-2 float-left">Miembros del equipo</h5>
						<h5 class="is-700 h5 text-body my-2 float-right"><i class="fas fa-angle-down fa-lg"></i></h5>
					</div>
					<div id="collapseMembers" class="collapse">
						<div class="my-1 row justify-content-center">
								@foreach ($objective->members as $member)
								<div class="col-6 col-sm-4 col-md-3 text-center py-1">
									<p class="mb-1">@include('utils.avatar',['avatar' => $member->avatar, 'size' => 50, 'thumbnail' => true])</p>
									<span class="is-700 text-smaller">{{$member->name}} {{$member->surname}}</span>
									<br><span class="text-smaller text-muted">{{$member->pivot->role == 'reporter' ? 'Reporta' : 'Coordina'}}</span>
								</div>
								@endforeach
							</div>
					</div>
					@endif
					@if(!$objective->files->isEmpty())
					<hr>
					<div class="clearfix is-clickable" data-toggle="collapse" data-target="#collapseFiles">
						<h5 class="is-700 h5 text-body my-2 float-left">Archivos</h5>
						<h5 class="is-700 h5 text-body my-2 float-right"><i class="fas fa-angle-down fa-lg"></i></h5>
					</div>
					<div id="collapseFiles" class="collapse">
							@forelse ($objective->files as $file)
							<div class="card my-2 shadow-sm">
								<div class="card-body p-2 pl-4 pr-4 d-flex">
									<div class="mr-3 mt-2">
										<i class="far fa-file fa-lg"></i>
									</div>
									<div class="flex-fill">
										<p class="mb-0 text-smaller word-wrap-anywhere">{{ $file->name }}</p>
										<p class="text-card text-smaller text-muted mb-0">{{ $file->mime }}</p>
									</div>
									<div class="ml-3 mt-2">
										<a href="{{ asset($file->path) }}" class="card-link text-smaller"><i class="fas fa-download fa-lg"></i></a>
									</div>
								</div>
							</div>
							@empty
							<p class="my-2 text-muted">No hay archivos adjuntos al objetivo</p>
							@endforelse
					</div>
					@endif
				</div>
			</div>
		</div>
	</div>
	<section class="mt-5" id="reportes">
		<h3 class="is-700 mb-3">Reportes</h3>
		<search-reports fetch-url="{{route('apiService.reports')}}" :fixed-objective="{{ $objective->id }}">
			@include('partials.loading')
		</search-reports>
	</section>
</div>
@endsection
