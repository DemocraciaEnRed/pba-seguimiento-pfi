@extends('layouts.admin')

@section('hero')
<x-hero title="Administración" :subtitle="'Panel de administración de la plataforma '.config('app.short_name').'.'" class="admin-hero" />
@endsection

@section('content')

<div class="container py-4 py-lg-5">
  <div class="row">
    <div class="col-md-4 col-lg-3">
      <div id="menu" class="card shadow-sm rounded">
        <div class="card-body">
          @include('admin.menu')
        </div>
      </div>
    </div>
    <div class="col-md-8 col-lg-9">
      <div class="card shadow-sm rounded">
        <div class="card-body p-3 p-lg-4">
        @yield('adminContent')
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
