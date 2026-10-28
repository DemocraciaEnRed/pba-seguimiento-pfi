@extends('layouts.admin')

@section('hero')
<x-hero :accent="$objective->category?->color" class="manage-hero">
  @if($objective->category)
  @include('partials.hierarchy-breadcrumb', ['objective' => $objective])
  <hr class="bg-white">
  @endif
  <p class="mb-1"><i class="fas fa-medal fa-fw"></i> Meta: <a href="{{ route('objectives.manage.goals.index', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}">{{ $goal->title }}</a></p>
  <p class="mb-1 text-smaller text-uppercase is-700"><i class="fas fa-gear fa-fw"></i> Panel del reporte</p>
  <h1 class="hero__title">{{ $report->title }}</h1>
  <x-slot:actions>
    @if($report->isApproved())
    <a href="{{ route('reports.index', ['reportId' => $report->id]) }}" class="btn btn-outline-white btn-sm"><i class="fas fa-up-right-from-square fa-fw"></i> Ver en el portal</a>
    @endif
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
          @include('objective.manage.goals.reports.menu')
        </div>
      </div>
    </div>
    <div class="col-md-8 col-lg-9">
      <div class="card shadow-sm rounded">
        <div class="card-body p-3 p-lg-4">
          <div class="alert alert-info">
            <strong>{{ $report->review_status_label }}</strong>
            @if(!$report->isApproved()) — Esta propuesta no modifica todavía la meta ni es visible en el portal. @endif
            @if($report->return_reason)<p class="mb-0 mt-1">Última devolución: {{ $report->return_reason }}</p>@endif
          </div>
          @if($errors->any())
          <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
          @endif
          @yield('panelContent')
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
