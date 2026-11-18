@extends('admin.master')

@section('adminContent')

<section>
  <a href="{{ route('admin.pfi-categories') }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver a las categorías PFI</a>
  <h3 class="is-700 mt-3">Editar categoría PFI</h3>
  <p class="lead">
    Los cambios se reflejan en {{ $category->users_count }} {{ $category->users_count === 1 ? 'integrante' : 'integrantes' }} con esta categoría.
    Si la desactivás, la conservan pero no podrá elegirse en nuevas asignaciones.
  </p>
  <form method="POST" action="{{ route('admin.pfi-categories.edit.form', ['pfiCategory' => $category]) }}">
    @method('PUT')
    @csrf
    @include('admin.pfi-categories._form')
    <button type="submit" class="btn btn-primary">Guardar cambios</button>
  </form>
</section>

@endsection
