@extends('objective.manage.goals.reports.master')

@section('stylesheets')
@if(app_setting('app_map_enabled'))
<link href="https://api.mapbox.com/mapbox-gl-js/v2.9.1/mapbox-gl.css" rel="stylesheet">
@endif
@endsection

@section('panelContent')
  <h3 class="is-700">Resumen del reporte</h3>
  <p class="lead"><i class="text-primary {{$report->type_icon}} fa-fw"></i> Reporte de {{$report->type_label}} &middot; <span class="text-muted">@justdate($report->date)</span></p>
  <div class="d-grid grid-cols-2 grid-cols-md-4 gap-2 mb-3">
    @if($report->isApproved())
    <x-stat label="Me gusta" :value="$report->positive_testimonies" icon="far fa-thumbs-up" tone="primary" :href="route('objectives.manage.goals.reports.testimonies', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])" />
    <x-stat label="Comentarios" :value="$report->comments->count()" icon="far fa-comments" tone="secondary" :href="route('objectives.manage.goals.reports.comments', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])" />
    @endif
    <x-stat label="Fotos" :value="$report->photos->count()" icon="far fa-images" tone="info" :href="route('objectives.manage.goals.reports.album', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])" />
    <x-stat label="Archivos" :value="$report->files->count()" icon="far fa-copy" tone="info" :href="route('objectives.manage.goals.reports.files', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])" />
  </div>
  @if($report->period)
  <p class="mb-2"><i class="far fa-calendar fa-fw text-muted"></i>&nbsp;Informa el período <b>{{ $report->period->label() }}</b> <small class="text-muted">({{ $report->period->rangeLabel() }})</small></p>
  @endif
  @if($report->milestone)
  <p class="mb-2"><i class="far fa-star fa-fw text-muted"></i>&nbsp;{{ $report->isApproved() ? 'Hito informado' : 'Propone completar el hito' }} <b>#{{ $report->milestone->order }} {{ $report->milestone->title }}</b></p>
  @endif
  <p>Autor: {{ $report->author->fullname }}</p>
  @if(!$report->isApproved())
  <p>Atiende el pendiente: {{ $report->attendant?->fullname ?? $report->author->fullname }}</p>
  @can('manageReporters', $goal)
  <form method="POST" action="{{ route('objectives.manage.goals.reports.attendant', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id]) }}" class="mb-3">
    @csrf
    @method('PUT')
    <input type="hidden" name="review_version" value="{{ $report->review_version }}">
    <label for="attendant_id">Reasignar atención a un Reportero de esta meta</label>
    <select name="attendant_id" id="attendant_id" class="form-control mb-2" required>
      <option value="">Elegir Reportero</option>
      @foreach($attendantCandidates as $candidate)
      <option value="{{ $candidate->id }}">{{ $candidate->fullname }}</option>
      @endforeach
    </select>
    <button class="btn btn-outline-primary" type="submit" @disabled($attendantCandidates->isEmpty())>Reasignar atención</button>
  </form>
  @endcan
  @endif
  <div class="mb-3">{!! nl2br(e($report->content)) !!}</div>
  @foreach($report->photos as $photo)
  <a href="{{ $photo->path }}" target="_blank" rel="noopener"><img src="{{ $photo->thumbnail_path }}" height="80" class="rounded mb-3 mr-2" alt="Foto del reporte"></a>
  @endforeach
  @if($files->total() > 0)
  @include('objective.manage.partials.file-list')
  @endif
  @if(app_setting('app_map_enabled') && $report->map_geometries)
  @include('report.map')
  @endif
  @if(!$report->isApproved())
  <h4>Cambios propuestos</h4>
  @if($report->status)
  <p>Estado actual: {{ $goal->status_label }} → Estado propuesto: {{ $report->status_label }}</p>
  @endif
  @if($report->type === 'progress' && $goal->isSimple())
  <p>Avance actual: {{ indicator_number($goal->indicator_progress) }} + {{ indicator_number($report->progress) }} = {{ indicator_number($goal->indicator_progress + $report->progress) }} {{ $goal->indicator_unit }}</p>
  @elseif($report->type === 'progress' && $report->period)
  <p>Meta del período: {{ indicator_number($report->period->target_value) }} — Resultado propuesto: {{ indicator_number($report->measured_value) }} {{ $goal->indicator_unit }}</p>
  @elseif($report->type === 'milestone')
  <p>Fecha propuesta de cumplimiento: {{ $report->proposed_milestone_date?->format('d/m/Y') }}</p>
  @endif
  @if($report->review_status === \App\Services\Reports\ReviewStatus::Pending && auth()->user()->hasRole('admin'))
  <form method="POST" action="{{ route('reports.review.approve', ['reportId' => $report->id]) }}" class="mb-3">
    @csrf
    <input type="hidden" name="review_version" value="{{ $report->review_version }}">
    <input type="hidden" name="context" value="{{ $reviewContext }}">
    @if(!$objective->hidden && $goal->visibility === 'public')
    <label class="d-block"><input type="checkbox" name="notify" value="true" @checked($report->notify_subscribers)> Notificar a suscriptores al publicar</label>
    @endif
    <button type="submit" class="btn btn-primary">Aprobar y aplicar</button>
  </form>
  <form method="POST" action="{{ route('reports.review.return', ['reportId' => $report->id]) }}">
    @csrf
    <input type="hidden" name="review_version" value="{{ $report->review_version }}">
    <label for="return_reason">Motivo de devolución</label>
    <textarea id="return_reason" name="return_reason" class="form-control mb-2" maxlength="2000" required>{{ old('return_reason') }}</textarea>
    <button type="submit" class="btn btn-outline-danger">Devolver</button>
  </form>
  @elseif($report->review_status === \App\Services\Reports\ReviewStatus::Returned && auth()->user()->can('update', $report))
  <form method="POST" action="{{ route('reports.review.resubmit', ['reportId' => $report->id]) }}">
    @csrf
    <input type="hidden" name="review_version" value="{{ $report->review_version }}">
    <button type="submit" class="btn btn-primary">Reenviar a revisión</button>
  </form>
  @endif
  @endif
@endsection
