@extends('objective.manage.goals.reports.master')

@section('panelContent')

<section>
  <h3 class="is-700">Repositorio de archivos</h3>
  <p class="lead">Aquí podrás encontrar y cargar todos los archivos vinculados al reporte</p>
  @if($report->canManage(auth()->user()))
  <div class="card border-light mb-3">
    <div class="card-body p-3">
      <h6 class="is-700 mb-1"><i class="fas fa-upload fa-fw"></i>Nuevo archivo</h6>
      <p class="text-smaller text-muted mb-2">Intente no subir archivos de mucho peso.</p>
      <form action="{{ route('objectives.manage.goals.reports.files.form',['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id]) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="review_version" value="{{ $report->review_version }}">
        <input-file name="file"></input-file>
        <button class="btn btn-primary btn-sm" type="submit">Subir archivo</button>
      </form>
    </div>
    @endif
  </div>
  @include('objective.manage.partials.file-list', ['files' => $files])
</section>

@endsection
