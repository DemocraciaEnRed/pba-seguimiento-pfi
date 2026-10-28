@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Crear iniciativa</h3>
  <p class="lead">Las iniciativas son sistemas, herramientas o programas que materializan el Plan. Se muestran en la sección de cada eje vinculado y, si se destacan, en la página de inicio.</p>
  <form method="POST" action="{{ route('admin.initiatives.create.form') }}" enctype="multipart/form-data">
    @csrf
    @include('admin.initiatives._form')
    <button type="submit" class="btn btn-primary">Crear</button>
  </form>
</section>

@endsection
