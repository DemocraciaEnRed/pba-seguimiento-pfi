@extends('layouts.admin')

@section('hero')
<x-hero :accent="$objective->category?->color" class="manage-hero">
  @if($objective->category)
  @include('partials.hierarchy-breadcrumb', ['objective' => $objective])
  <hr class="bg-white">
  @endif
  <p class="mb-1 text-smaller text-uppercase is-700"><i class="fas fa-gear fa-fw"></i> Panel de la meta</p>
  <h1 class="hero__title">{{ $goal->title }}</h1>
  <x-slot:actions>
    <a href="{{ route('goals.index', ['goalId' => $goal->id]) }}" class="btn btn-outline-white btn-sm"><i class="fas fa-up-right-from-square fa-fw"></i> Ver en el portal</a>
  </x-slot:actions>
</x-hero>
@endsection

@section('content')

<div class="container py-4 py-lg-5">
  @if($objective->hidden)
  <div class="alert alert-info">
    <i class="fas fa-circle-info"></i>&nbsp;Nota: El objetivo se encuentra <b>oculto</b>. <a href="{{route('objectives.manage.configuration', ['objectiveId' => $objective->id]) }}">Cambiar<i class="fas fa-arrow-right fa-fw"></i></a>
  </div>
  @endif
  <div class="row">
    <div class="col-md-4 col-lg-3">
      <div id="menu" class="card shadow-sm rounded">
        <div class="card-body">

      @include('objective.manage.goals.menu')
        </div>
      </div>
    </div>
    <div class="col-md-8 col-lg-9">
      <div class="card shadow-sm rounded">
        <div class="card-body p-3 p-lg-4">
      @yield('panelContent')
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
