@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Ejes</h3>
  <p class="lead">A continuación encontrarán los ejes dentro de los cuales se agruparán los objetivos:</p>
  @forelse($categories as $category)
  <div class="card mb-3 shadow-sm">
    <div class="card-body d-flex align-items-center">
      <div class="mr-3 category-icon-container" style="background-color: {{$category->background_color}}">
        <x-category-icon :icon="$category->icon" size="2x" style="color: {{$category->color}}" />
      </div>
        <div class="w-100">
          <span class="text-smaller text-muted">N° {{$category->order}}</span>
          <h5 class="m-0" style="color: {{$category->color}}">{{$category->title}}</h5>
        </div>
        <div class="text-right">
          <a href="{{ route('admin.categories.edit', ['categoryId' => $category->id]) }}" class="btn btn-link btn-sm"><i class="fas fa-pencil fa-fw"></i>Editar</a>
          <a href="{{ route('admin.categories.delete', ['categoryId' => $category->id]) }}" class="btn btn-link btn-sm"><i class="fas fa-trash-can fa-fw"></i>Eliminar</a>
        </div>
    </div>
  </div>
  @empty
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <div>
        <h6 class="card-title">No hay ejes cargados</h4>
        <a href="{{ route('admin.categories.create') }}" class="card-link"><b>Haga clic para crear un nuevo eje <i class="fas fa-arrow-right"></i></b></a>
      </div>
    </div>
  </div>
  @endforelse
</section>

@endsection
