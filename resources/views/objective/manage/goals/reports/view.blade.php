@extends('objective.manage.goals.reports.master')

@section('panelContent')
  <h3 class="is-700">Resumen del reporte</h3>
  <p class="lead"><i class="text-primary {{$report->type_icon}} fa-fw"></i> Reporte de {{$report->type_label}} &middot; <span class="text-muted">@justdate($report->date)</span></p>
  <div class="d-grid grid-cols-2 grid-cols-md-4 gap-2 mb-3">
    <x-stat label="Me gusta" :value="$report->positive_testimonies" icon="far fa-thumbs-up" tone="primary" :href="route('objectives.manage.goals.reports.testimonies', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])" />
    <x-stat label="Comentarios" :value="$report->comments->count()" icon="far fa-comments" tone="secondary" :href="route('objectives.manage.goals.reports.comments', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])" />
    <x-stat label="Fotos" :value="$report->photos->count()" icon="far fa-images" tone="info" :href="route('objectives.manage.goals.reports.album', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])" />
    <x-stat label="Archivos" :value="$report->files->count()" icon="far fa-copy" tone="info" :href="route('objectives.manage.goals.reports.files', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id])" />
  </div>
  @if($report->period)
  <p class="mb-2"><i class="far fa-calendar fa-fw text-muted"></i>&nbsp;Informa el período <b>{{ $report->period->label() }}</b> <small class="text-muted">({{ $report->period->rangeLabel() }})</small></p>
  @endif
  @if($report->milestone)
  <p class="mb-2"><i class="far fa-star fa-fw text-muted"></i>&nbsp;Completa el hito <b>#{{ $report->milestone->order }} {{ $report->milestone->title }}</b></p>
  @endif
@endsection
