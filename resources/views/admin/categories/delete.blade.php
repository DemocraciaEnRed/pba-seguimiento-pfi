@extends('admin.master')

@section('adminContent')

<section>
<h3 class="is-700">Eliminar eje</small></h3>
<p class="lead">Complete los siguientes campos para eliminar un eje:</p>
  @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
  @endif
  <form action="{{ route('admin.categories.delete.form',['categoryId' => $category->id]) }}" method="POST">
    @method('DELETE')
    @csrf
    <p>Al eliminar un eje, tenga en cuenta lo siguiente</p>
    <ul>
      <li>Los objetivos estratégicos vinculados con el eje "{{$category->title}}" no pueden quedar sin eje</li>
      <li>Para eliminar el eje, se deben migrar sus objetivos estratégicos a un eje existente</li>
      <li>El siguiente formulario migra todos los objetivos estratégicos al eje seleccionado.</li>
    </ul>
     <div class="form-group">
      <label><b>Eje al que migran los objetivos estratégicos</b><span class="text-danger">*</span></label>
      <select class="custom-select" name="category">
        @foreach ($categories as $categoryAux)
        @if($categoryAux->id != $category->id)
        <option value="{{$categoryAux->id}}">{{$categoryAux->title}}</option>
        @endif
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label><b>Ingrese su contraseña</b><span class="text-danger">*</span></label>
      <input type="password" class="form-control" name="password">
      <small class="form-text text-muted">Para poder eliminar el eje, ingrese su contraseña para confirmar.</small>
    </div>
    <button type="submit" class="btn btn-danger">Eliminar</button>
  </form>

</section>

@endsection
