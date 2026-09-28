@php
    $titleEncode = urlencode($event->title);
    $descriptionEncode = urlencode('Mas información: ' . route('events.index',['eventId' => $event->id]));
    $locationEncode = urlencode($event->address);
    $start = urlencode($event->date->format('Ymd\THis'));
    $end = urlencode($event->date->addHour()->format('Ymd\THis'));
    $ctz = urlencode('America/Argentina/Buenos_Aires');
@endphp

@extends('layouts.app')

@section('metatags')
  @include('portal.events.metatags')
@endsection

@section('hero')
<x-hero :image="$event->photos->isNotEmpty() ? asset($event->photos[0]->path) : null" align="center" size="lg">
  <p class="h5 is-400">{{$event->moment}}</p>
  <h1 class="hero__title">{{$event->title}}</h1>
  @hasRole('admin')
  <x-slot:actions>
    <a href="{{route('admin.events.edit',['eventId'=> $event->id])}}" class="btn btn-light"><i class="fas fa-pen-to-square"></i> Editar</a>
  </x-slot:actions>
  @endhasRole
</x-hero>
@endsection

@section('content')
  <div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8">
      <div class="card my-3 p-4">
        <div class="card-body">
          <p>{!! nl2br(e($event->content)) !!}<p>

          <h5 class="is-700 my-4">Links</h5>
          @if($event->urls)
          @foreach ($event->urls as $label => $url)
          <span class="py-1 px-3 bg-primary rounded mr-2 mb-1 d-inline-block"><i class="fas fa-link text-white"></i>&nbsp;<a href="{{$url}}" target="_blank" class="text-white is-600">{{$label}}</a></span>
          @endforeach
          @else
          <span class="text-muted">No se han previsto links acerca del evento</span>
          @endif
        </div>
      </div>
      <div class="card my-3 p-4">
        <div class="card-body">
          <div class="row mb-3">
            <div class="col">
              <h5 class="is-700">Fecha</h5>
              <h3 class="text-primary is-400">@justdate($event->date)</h3>
            </div>
            <div class="col">
              <h5 class="is-700">Hora</h5>
              <h3 class="text-primary is-400">@justtime($event->date)</h3>
            </div>
          </div>
          <div class="">
          <h5 class="is-700">Dirección</h5>
              <h5 class="text-primary is-400">{{$event->address}}</h5>
          </div>
        </div>
      </div>
          <a class="btn btn-block btn-primary btn-lg my-3" target="_blank" href="https://calendar.google.com/calendar/r/eventedit?action=TEMPLATE&text={{$titleEncode}}&details={{$descriptionEncode}}&location={{$locationEncode}}&dates={{$start}}/{{$end}}&ctz={{$ctz}}">
          <i class="fab fa-google fa-fw"></i><i class="fas fa-calendar-plus fa-fw"></i> Agregar a Google Calendar</a>
      @include('portal.events.album')
      @include('portal.events.objectives')
    </div>
  </div>
  </div>
@endsection
