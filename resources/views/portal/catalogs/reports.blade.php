@extends('layouts.app')

@section('hero')
<x-hero title="Seguimiento" subtitle="Novedades, avances e hitos que publican los equipos sobre cada objetivo." />
@endsection

@section('content')
<div class="container">
  <div class="py-5">
  <search-reports fetch-url="{{route('apiService.reports')}}" :categories='@json($categories)'>
    @include('partials.loading')
  </search-reports>
  </div>
</div>
@endsection
