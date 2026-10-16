@extends('layouts.app')

@section('hero')
<x-hero title="Mi panel" subtitle="Notificaciones, objetivos que integrás y preferencias de tu cuenta." class="panel-hero" />
@endsection

@section('content')

<div class="container py-4 py-lg-5">
  <div class="row">
    <div class="col-md-4 col-lg-3">
      <div id="menu" class="card shadow-sm rounded">
        <div class="card-body">

      @include('panel.menu')
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
