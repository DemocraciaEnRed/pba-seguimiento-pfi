@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Eliminar iniciativa</h3>
  <p class="lead">Estás por eliminar la iniciativa <b>{{ $initiative->title }}</b>.</p>
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif
  <form action="{{ route('admin.initiatives.delete.form', ['initiative' => $initiative]) }}" method="POST">
    @method('DELETE')
    @csrf
    <p>La iniciativa dejará de mostrarse en la página de inicio y en «El Plan». También se eliminarán su imagen de fondo y su logo. Esta acción no se puede deshacer.</p>
    <div class="form-group">
      <label for="password"><b>Ingrese su contraseña</b><span class="text-danger">*</span></label>
      <input type="password" id="password" class="form-control" name="password">
      <small class="form-text text-muted">Para poder eliminar la iniciativa, ingrese su contraseña para confirmar.</small>
    </div>
    <button type="submit" class="btn btn-danger">Eliminar</button>
  </form>
</section>

@endsection
