@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Crear categoría PFI</h3>
  <p class="lead">La categoría quedará disponible para clasificar a las personas que integran los equipos PFI. No otorga permisos.</p>
  <form method="POST" action="{{ route('admin.pfi-categories.create.form') }}">
    @csrf
    @include('admin.pfi-categories._form')
    <button type="submit" class="btn btn-primary">Crear</button>
  </form>
</section>

@endsection
