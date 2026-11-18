@extends('objective.manage.master')

@section('panelContent')

<section>
  <h3 class="is-700">Comentarios</h3>
  <p class="lead">Comentarios y respuestas en los reportes de todas las metas del objetivo.</p>
  @include('partials.comments.list', [
    'filterUrl' => route('objectives.manage.comments', ['objectiveId' => $objective->id]),
    'downloadUrl' => Auth::user()->can('accessManagement', $objective)
      ? route('objectives.manage.comments.download', ['objectiveId' => $objective->id] + request()->only(['q', 'tipo']))
      : null,
    'deleteRoute' => 'objectives.manage.comments.delete.form',
    'deleteRouteParams' => ['objectiveId' => $objective->id],
  ])
</section>

@endsection
