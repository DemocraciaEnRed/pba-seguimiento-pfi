@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Objetivos Estratégicos</h3>
  <p class="lead">A continuación encontrarán los objetivos estratégicos, agrupados por Eje:</p>
  @forelse($strategicObjectives as $strategicObjective)
  <div class="card mb-3 shadow-sm">
    <div class="card-body d-flex align-items-center">
      <div class="mr-3 category-icon-container" style="background-color: {{$strategicObjective->category->background_color}}">
        <x-category-icon :icon="$strategicObjective->category->icon" size="2x" style="color: {{$strategicObjective->category->color}}" />
      </div>
        <div class="w-100">
          <span class="text-smaller text-muted">{{$strategicObjective->codigo}} &middot; {{$strategicObjective->category->title}}</span>
          <h5 class="m-0">{{$strategicObjective->title}}</h5>
        </div>
        <div class="text-right">
          <a href="{{ route('admin.categories.edit', ['categoryId' => $strategicObjective->category_id]) }}" class="btn btn-link btn-sm"><i class="fas fa-pencil fa-fw"></i>Editar Eje</a>
          <a href="{{ route('admin.strategic-objectives.delete', ['strategicObjectiveId' => $strategicObjective->id]) }}" class="btn btn-link btn-sm"><i class="fas fa-trash-can fa-fw"></i>Eliminar</a>
        </div>
    </div>
  </div>
  @empty
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <div>
        <h6 class="card-title">No hay objetivos estratégicos cargados</h6>
        <a href="{{ route('admin.categories.create') }}" class="card-link"><b>Haga clic para crear un eje con objetivos estratégicos <i class="fas fa-arrow-right"></i></b></a>
      </div>
    </div>
  </div>
  @endforelse
</section>

@endsection
