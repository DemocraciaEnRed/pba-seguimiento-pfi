@extends('admin.master')

@section('adminContent')

<section>
<h3 class="is-700">Eliminar objetivo estratégico</h3>
<p class="lead">Complete los siguientes campos para eliminar un objetivo estratégico:</p>
  @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
  @endif
  <form action="{{ route('admin.strategic-objectives.delete.form',['strategicObjectiveId' => $strategicObjective->id]) }}" method="POST">
    @method('DELETE')
    @csrf
    <p>Al eliminar un objetivo estratégico, tenga en cuenta lo siguiente</p>
    <ul>
      <li>Los objetivos específicos vinculados con el objetivo estratégico "{{$strategicObjective->title}}" no pueden quedar sin objetivo estratégico</li>
      <li>Para eliminarlo, se deben migrar los objetivos específicos vinculados a un objetivo estratégico existente</li>
      <li>El siguiente formulario migra todos los objetivos específicos al objetivo estratégico seleccionado.</li>
    </ul>
     <div class="form-group">
      <label><b>Objetivo estratégico al que migran los objetivos específicos</b><span class="text-danger">*</span></label>
      <select class="custom-select" name="strategic_objective">
        @foreach ($strategicObjectives as $strategicObjectiveAux)
        <option value="{{$strategicObjectiveAux->id}}">{{$strategicObjectiveAux->codigo}} - {{$strategicObjectiveAux->title}}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label><b>Ingrese su contraseña</b><span class="text-danger">*</span></label>
      <input type="password" class="form-control" name="password">
      <small class="form-text text-muted">Para poder eliminar el objetivo estratégico, ingrese su contraseña para confirmar.</small>
    </div>
    <button type="submit" class="btn btn-danger">Eliminar</button>
  </form>

</section>

@endsection
