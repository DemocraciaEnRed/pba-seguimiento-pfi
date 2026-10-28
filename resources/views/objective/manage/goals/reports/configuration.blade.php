@extends('objective.manage.goals.reports.master')

@section('panelContent')

<section>
  <h3 class="is-700">Configuración del reporte</h3>
  <p class="lead">A continuación, encontrarás otras opciones de configuracion del reporte</p>
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
  <h5 class="is-700 has-text-danger"><i class="fas fa-trash"></i> Eliminar reporte</h5>
  <p>Al eliminar el reporte, tenga en cuenta lo siguiente</p>
  <ul>
    <li>El reporte deja de estar disponible. Si reservaba un período, lo libera.</li>
    <li>Eliminar un aprobado no revierte automáticamente avances ni hitos ya aplicados.</li>
  </ul>
  <form action="{{ route('objectives.manage.goals.reports.delete.form',['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id]) }}" method="POST">
    @method('DELETE')
    @csrf
    <div class="form-group">
      <label>Ingrese su contraseña</label>
      <input type="password" class="form-control" name="password">
      <small class="form-text text-muted">Para poder eliminar el reporte, ingrese su contraseña para confirmar.</small>
    </div>
    @if($report->isApproved())
    <div class="form-group">
     <label class="is-700 "><i class="fas fa-paper-plane"></i>&nbsp;Enviar notificación a suscriptores</label>
      @if(!$objective->hidden)
      <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" name="notify" id="notify" value="true">
        <label class="custom-control-label is-clickable" for="notify">Notificar a los suscriptores</label>
      </div>
      @endif
      @else
      <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>&nbsp;El objetivo se encuentra <i class="fas fa-eye-slash"></i> oculto, no se enviarán notificaciones a los usuarios.
      </div>
      @endif
      <small class="form-text text-muted">Se le enviará una notificación por sistema, de que el reporte ha sido eliminado.</small>
    </div>
    <button type="submit" class="btn btn-danger">Eliminar</button>
  </form>
</section>

@endsection
