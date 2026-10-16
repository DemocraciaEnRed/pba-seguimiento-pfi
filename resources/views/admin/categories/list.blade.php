@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Ejes</h3>
  <p class="lead">A continuación encontrarán los ejes dentro de los cuales se agruparán los objetivos:</p>
  <div class="list-group shadow-sm mb-3">
    @forelse($categories as $category)
    <div class="list-group-item d-flex align-items-center gap-3">
      <div class="category-icon-container" style="background-color: {{$category->background_color}}">
        <x-category-icon :icon="$category->icon" style="color: {{$category->color}}" />
      </div>
      <div class="flex-grow-1 min-w-0">
        <span class="text-smaller text-muted">N° {{$category->order}}</span>
        <p class="mb-0 is-700" style="color: {{$category->color}}">{{$category->title}}</p>
      </div>
      <div class="text-right text-smaller text-nowrap">
        <a href="{{ route('admin.strategic-objectives', ['eje' => $category->id]) }}" class="d-block" title="Ver objetivos estratégicos">{{ $category->strategic_objectives_count }} {{ $category->strategic_objectives_count === 1 ? 'objetivo estratégico' : 'objetivos estratégicos' }}<i class="fas fa-compass fa-fw ml-1"></i></a>
        <a href="{{ route('admin.objectives', ['eje' => $category->id]) }}" class="d-block" title="Ver objetivos generales">{{ $category->objectives_count }} {{ $category->objectives_count === 1 ? 'objetivo general' : 'objetivos generales' }}<i class="fas fa-flag-checkered fa-fw ml-1"></i></a>
      </div>
      <div class="d-flex flex-column flex-md-row text-nowrap">
        <a href="{{ route('admin.categories.edit', ['categoryId' => $category->id]) }}" class="btn btn-link btn-sm"><i class="fas fa-pencil fa-fw"></i>Editar</a>
        <a href="{{ route('admin.categories.delete', ['categoryId' => $category->id]) }}" class="btn btn-link btn-sm"><i class="fas fa-trash-can fa-fw"></i>Eliminar</a>
      </div>
    </div>
    @empty
    <div class="list-group-item">
      <h6 class="mb-1">No hay ejes cargados</h6>
      <a href="{{ route('admin.categories.create') }}" class="is-700">Haga clic para crear un nuevo eje <i class="fas fa-arrow-right"></i></a>
    </div>
    @endforelse
  </div>
</section>

@endsection
