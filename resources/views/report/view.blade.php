@section('metatags')
  @include('report.metatags')
@endsection

@if(app_setting('app_map_enabled'))
@section('stylesheets')
<link href='https://api.mapbox.com/mapbox-gl-js/v2.9.1/mapbox-gl.css' rel='stylesheet' />
@endsection

@section('headscripts')
<script src='https://api.mapbox.com/mapbox-gl-js/v2.9.1/mapbox-gl.js'></script>
@endsection
@endif

@extends('layouts.app')

@php
  $coverPhoto = $report->photos->sortBy('id')->first();
@endphp

@section('hero')
<x-hero :align="'center'" :image="$coverPhoto ? asset($coverPhoto->path) : null" size="md" class="report-hero">
  {{--
  @include('partials.hierarchy-breadcrumb', ['objective' => $objective])
  <p class="my-1 text-smaller">
    <a href="{{route('goals.index', ['goalId' => $goal->id])}}"><i class="far fa-circle-dot fa-fw"></i> Meta: {{$goal->title}}</a>
  </p>
  --}}
  <span class="badge badge-white text-black px-2 py-1 mt-4 mb-2"><i class="{{$report->type_icon}} fa-fw"></i> Reporte de {{$report->type_label}}</span>
  <h1 class="hero__title">{{$report->title}}</h1>
  <ul class="list-inline report-hero__meta mt-3 mb-0">
    <li class="list-inline-item mr-4 mb-2">
      @include('utils.avatar',['avatar' => $report->author->avatar, 'size' => 28, 'thumbnail' => true])
      {{$report->author->name}} {{$report->author->surname}}
    </li>
    <li class="list-inline-item mr-4 mb-2"><i class="far fa-calendar fa-fw"></i> Fecha del reporte: @justdate($report->date)</li>
    <li class="list-inline-item mb-2"><i class="far fa-clock fa-fw"></i> Publicado el @datetime($report->created_at)</li>
  </ul>
  @if(!empty($report->tags))
  <ul class="list-inline mb-0">
    @foreach ($report->tags as $tag)
    <li class="list-inline-item mb-1"><span class="badge badge-pill report-hero__tag">#{{$tag}}</span></li>
    @endforeach
  </ul>
  @endif
  <x-slot:actions>
    <report-like-button
      toggle-url="{{route('apiService.reports.testimonies.run', ['reportId' => $report->id])}}"
      :initial-liked="@json(!is_null($testimony))"
      :initial-count="{{(int) $report->positive_testimonies}}"
      :is-authenticated="@json(Auth::check())"
      :is-verified="@json(Auth::check() && Auth::user()->hasVerifiedEmail())"
      login-url="{{route('login')}}"
      verify-url="{{route('panel.account.verify')}}"></report-like-button>
    <a href="#comentarios" class="btn btn-outline-white is-700"><i class="far fa-comment fa-fw"></i> Comentar <span class="badge badge-white ml-1">{{$report->comments()->count()}}</span></a>
    @isMember($objective->id)
    <a href="{{route('objectives.manage.goals.reports.index',['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])}}"
      class="btn btn-white is-700"><i class="fas fa-pen-to-square fa-fw"></i> Editar</a>
    @endisMember
  </x-slot:actions>
</x-hero>
@endsection

@section('content')
<div class="container py-4">
  <div class="card shadow-sm mb-3">
    <div class="card-body p-3 p-lg-5 is-size-5">
      {!! nl2br(e($report->content)) !!}
    </div>
  </div>
  @include('report.progress')
  @include('report.milestone')
  @include('report.status')
  @include('report.data')
  @include('report.files')
  @include('report.album')
  @if(app_setting('app_map_enabled'))
    @include('report.map')
  @endif
  @include('report.comments')
  @include('report.communities')
</div>
@endsection
