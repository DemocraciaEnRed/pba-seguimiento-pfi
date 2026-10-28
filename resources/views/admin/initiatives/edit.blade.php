@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Editar iniciativa</h3>
  <p class="lead">Modificá los datos de la iniciativa. Los cambios se reflejan en la página de inicio y en «El Plan».</p>
  <form method="POST" action="{{ route('admin.initiatives.edit.form', ['initiative' => $initiative]) }}" enctype="multipart/form-data">
    @method('PUT')
    @csrf
    @include('admin.initiatives._form')
    <button type="submit" class="btn btn-primary">Guardar cambios</button>
  </form>
</section>

@endsection
