@extends('objective.manage.master')

@section('panelContent')

<section>
  <h3 class="is-700">Nueva meta del objetivo</h3>
  <p class="lead">Para sumar una nueva meta a tu objetivo, completá los campos a continuación:</p>
  <hr>
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
  @endif
  <form method="POST" action="{{ route('objectives.manage.goals.add.form',['objectiveId' => $objective->id]) }}">
    @csrf
    <div class="form-group">
      <label><b>Título de la meta</b><span class="text-danger">*</span></label>
      <input type="text" class="form-control" name="title" placeholder="Escriba aquí" value="{{ old('title') }}">
    </div>
    <input-goal-indicator :initial='@json($indicatorForm)' :old='@json(old())' :options='@json($indicatorOptions)'></input-goal-indicator>
    <div class="form-group">
      <label><b>Estado inicial de la meta</b><span class="text-danger">*</span></label>
      <select class="custom-select" name="status">
        <option value="ongoing" selected>En progreso</option>
        <option value="delayed" >No cumplida</option>
        <option value="inactive" >Inactiva</option>
        <option value="reached" disabled>Alcanzada</option>
      </select>
      <small class="form-text text-muted">Nota: No puede crear una meta con estado "Alcanzado"</small>
    </div>
    <div class="form-group">
      <label><b>Fuente de los datos</b> <small class="text-info">Opcional</small></label>
      <input type="text" class="form-control" name="source" placeholder="Escriba aquí">
      <small class="form-text text-muted">Es importante que la fuente de datos sean accesibles y oficiales para hacer transparente la medición</small>
    </div>
    <div class="form-group">
      <label><b>Hitos</b> <small class="text-info">Opcional</small></label>
      <input-add-milestones-create-goal name="milestones">
    </div>
    <div class="border border-light rounded p-3">
      <label class="is-700 "><i class="fas fa-paper-plane"></i>&nbsp;Enviar notificacion a suscriptores</label>
      @if(!$objective->hidden)
      <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" name="notify" id="notify" value="true">
        <label class="custom-control-label is-clickable" for="notify">Notificar a los suscriptores</label>
      </div>
      @else
      <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>&nbsp;El objetivo se encuentra <i class="fas fa-eye-slash"></i> oculto, no se enviarán notificaciones a los usuarios.
      </div>
      @endif
      <small class="form-text text-muted">Se le enviará una notificación por email (si lo tienen habilitado) y por sistema, de que hay una nueva meta invitandolos a verla.</small>
    </div>
    <br>
    <button type="submit" class="btn btn-primary">Crear</button>
  </form>
</section>

@endsection
