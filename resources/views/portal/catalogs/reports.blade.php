@extends('layouts.app')

@section('hero')
<x-hero title="Catálogo de reportes" />
@endsection

@section('content')
<div class="container">
  <div class="py-5">
  <search-reports fetch-url="{{route('apiService.reports')}}" querystring="" map-enabled="{{app_setting('app_map_enabled')}}">
    @include('partials.loading')
  </search-reports>
  </div>
</div>
@endsection
