@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Próximas actividades</h1>
  <p class="lead">Acá encontrarás las próximas actividades que podés administrar</p>
  @include('admin.events.list')
</section>

@endsection
