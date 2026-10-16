@extends('objective.manage.goals.master')

@section('panelContent')

<section>
  <h3 class="is-700">Hitos de la meta</h3>
  <p class="lead">Un hito es un acontecimiento importante y necesario para el cumplimiento de la meta, sin embargo, no responde a un indicador concreto ni representa un avance en términos cuantitativos. Podrás hacer reportes para informar el cumplimiento de estos hitos.</p>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="text-smaller text-muted">{{ $goal->milestones->count() }} hito(s) &middot; {{ $goal->milestones->whereNotNull('completed')->count() }} completado(s)</span>
    <a href="{{ route('objectives.manage.goals.milestones.add', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus fa-fw"></i>Nuevo hito</a>
  </div>

  <div class="list-group shadow-sm mb-3">
    @forelse($goal->milestones as $milestone)
    <div class="list-group-item d-flex align-items-start gap-3">
      <span class="badge badge-light mt-1">#{{ $milestone->order }}</span>
      <div class="flex-grow-1 min-w-0">
        <span class="is-700">{{ $milestone->title }}</span>
        <div class="text-smaller text-muted">
          @if(is_null($milestone->completed))
          <i class="text-danger fas fa-xmark fa-fw"></i>No completado
          @else
          <i class="text-success fas fa-check fa-fw"></i>Completado el @justdate($milestone->completed)
          @if($milestone->report)
          &middot; <a href="{{ route('objectives.manage.goals.reports.index', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $milestone->report->id]) }}">Ver el reporte</a>
          @endif
          @endif
        </div>
      </div>
      <div class="d-flex gap-2 text-smaller text-nowrap">
        <a href="{{ route('objectives.manage.goals.milestones.edit', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'milestoneId' => $milestone->id]) }}"><i class="fas fa-pencil fa-fw"></i>Editar</a>
        <a href="{{ route('objectives.manage.goals.milestones.delete', ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'milestoneId' => $milestone->id]) }}" class="text-danger"><i class="fas fa-trash-can fa-fw"></i>Eliminar</a>
      </div>
    </div>
    @empty
    <div class="list-group-item text-center text-muted py-4">
      La meta no tiene hitos. <a href="{{ route('objectives.manage.goals.milestones.add', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}">Crear el primer hito</a>
    </div>
    @endforelse
  </div>
</section>

@endsection
