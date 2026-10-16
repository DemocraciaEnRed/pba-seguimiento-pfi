@extends('objective.manage.goals.master')

@section('panelContent')

<section>
  <h3 class="is-700">Comentarios</h3>
  <p class="lead">Comentarios y respuestas en los reportes de esta meta.</p>
  @include('partials.comments.list', [
    'filterUrl' => route('objectives.manage.goals.comments', ['objectiveId' => $objective->id, 'goalId' => $goal->id]),
    'downloadUrl' => Auth::user()->hasRole('admin') || Auth::user()->isManagerObjective($objective->id)
      ? route('objectives.manage.goals.comments.download', ['objectiveId' => $objective->id, 'goalId' => $goal->id] + request()->only(['q', 'tipo']))
      : null,
    'deleteRoute' => 'objectives.manage.goals.comments.delete.form',
    'deleteRouteParams' => ['objectiveId' => $objective->id, 'goalId' => $goal->id],
    'showGoal' => false,
  ])
</section>

@endsection
