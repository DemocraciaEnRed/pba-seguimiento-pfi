@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Actividades celebradas</h1>
  <p class="lead">Acá encontrarás las actividades celebradas que podés administrar</p>
  @include('admin.events.list')
</section>

@endsection
