@extends('layouts.app')

@section('hero')
<x-hero title="Objetivos" subtitle="Buscá objetivos por nombre, eje u objetivo estratégico y seguí el avance de sus metas." />
@endsection

@section('content')
<div class="container">
  <div class="py-5">
  <search-objectives fetch-url="{{route('apiService.objectives')}}" :categories='@json($categories)'>
    @include('partials.loading')
  </search-objectives>
  </div>
</div>
@endsection
