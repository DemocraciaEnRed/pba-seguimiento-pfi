@extends('admin.master')

@use('App\Imports\Structure\ImportAction')
@use('App\Imports\Structure\StructureImportColumns')

@section('adminContent')

<section>
  <h3 class="is-700">Vista previa de la importación</h3>
  <p class="lead">Revisá lo que se va a hacer. Todavía no se guardó nada.</p>

  <table class="table table-sm table-bordered text-center">
    <thead class="thead-light">
      <tr>
        <th class="text-left"></th>
        @foreach (ImportAction::cases() as $action)
          <th><span class="badge {{ $action->badgeClass() }}">{{ $action->label() }}</span></th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach ($plan->summary() as $entity => $counts)
      <tr>
        <td class="text-left"><b>{{ $entity }}</b></td>
        @foreach (ImportAction::cases() as $action)
          <td>{{ $counts[$action->value] }}</td>
        @endforeach
      </tr>
      @endforeach
    </tbody>
  </table>

  @if ($plan->hasErrors())
  <div class="alert alert-danger">
    <p class="is-700 mb-2"><i class="fas fa-triangle-exclamation"></i>&nbsp;El archivo tiene {{ count($plan->errors) }} {{ count($plan->errors) === 1 ? 'error' : 'errores' }}. Corregilos en la planilla y volvé a subirla.</p>
    <ul class="mb-0 text-smaller">
      @foreach ($plan->errors as $error)
        <li><b>Fila {{ $error['line'] }}</b>@if ($error['column']) · {{ $error['column'] }}@endif: {{ $error['message'] }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="d-flex my-4">
    @if (! $plan->hasErrors() && $plan->hasChanges())
    <form method="POST" action="{{ route('admin.import.confirm') }}" class="mr-2">
      @csrf
      <button type="submit" class="btn btn-primary"><i class="fas fa-check fa-fw"></i>&nbsp;Confirmar importación</button>
    </form>
    @elseif (! $plan->hasErrors())
    <p class="text-muted mr-3 my-auto">El archivo no tiene cambios para aplicar.</p>
    @endif
    <a href="{{ route('admin.import') }}" class="btn btn-outline-secondary">Subir otro archivo</a>
  </div>

  @foreach ($plan->strategicObjectives as $strategicObjective)
  <div class="card my-3 shadow-sm">
    <div class="card-body">
      <h6 class="mb-1">
        <span class="badge {{ $strategicObjective->action->badgeClass() }}">{{ $strategicObjective->action->label() }}</span>
        <span class="text-muted">{{ $strategicObjective->model->category?->title }} ›</span>
        <b>{{ $strategicObjective->model->codigo }} - {{ $strategicObjective->model->title }}</b>
        <small class="text-muted">(fila {{ $strategicObjective->line }})</small>
      </h6>
      @include('admin.import.changes', ['changes' => $strategicObjective->changes])

      @foreach ($strategicObjective->children as $objective)
      <div class="border-left pl-3 ml-2 mt-3">
        <p class="mb-1">
          <span class="badge {{ $objective->action->badgeClass() }}">{{ $objective->action->label() }}</span>
          <b>{{ $objective->model->codigo }} - {{ $objective->model->title }}</b>
          <small class="text-muted">(fila {{ $objective->line }})</small>
        </p>
        @include('admin.import.changes', ['changes' => $objective->changes])

        @if (count($objective->children) > 0)
        <ul class="list-unstyled ml-3 mb-0">
          @foreach ($objective->children as $goal)
          <li class="my-1">
            <span class="badge {{ $goal->action->badgeClass() }}">{{ $goal->action->label() }}</span>
            {{ $goal->input['title'] }}
            <small class="text-muted">· {{ StructureImportColumns::label($goal->model->exists ? $goal->model->measurement_mode->value : $goal->input['measurement_mode'], StructureImportColumns::modes()) }} · fila {{ $goal->line }}</small>
            @include('admin.import.changes', ['changes' => $goal->changes])
          </li>
          @endforeach
        </ul>
        @endif
      </div>
      @endforeach
    </div>
  </div>
  @endforeach
</section>

@endsection
