@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Iniciativas</h3>
  <p class="lead">Sistemas, herramientas y programas vinculados a los ejes del Plan. Se muestran en «El Plan» dentro de cada eje y, las destacadas, en la página de inicio.</p>
  <form method="GET" action="{{ route('admin.initiatives') }}" class="form-inline mb-3">
    <label class="sr-only" for="filter-category">Eje</label>
    <select id="filter-category" name="category" class="custom-select custom-select-sm mr-2 mb-2">
      <option value="">Todos los ejes</option>
      @foreach($categories as $category)
      <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->title }}</option>
      @endforeach
    </select>
    <div class="custom-control custom-checkbox mr-2 mb-2">
      <input type="checkbox" class="custom-control-input" id="filter-featured" name="featured" value="1" @checked(request()->boolean('featured'))>
      <label class="custom-control-label" for="filter-featured">Solo destacadas en inicio</label>
    </div>
    <button type="submit" class="btn btn-outline-primary btn-sm mb-2">Filtrar</button>
  </form>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-smaller text-muted">{{ $initiatives->total() }} resultado(s)</span>
  </div>
  <div class="list-group shadow-sm mb-3">
    @forelse($initiatives as $initiative)
    <div class="list-group-item d-flex align-items-center gap-3">
      <span class="text-smaller text-muted text-nowrap" title="Orden">#{{ $initiative->order }}</span>
      @if($initiative->logo_path)
      <img src="{{ $initiative->logoUrl() }}" class="rounded border bg-white flex-shrink-0 object-contain" width="48" height="48" alt="Logo {{ $initiative->title }}">
      @else
      <span class="rounded bg-light flex-shrink-0 d-inline-flex align-items-center justify-content-center text-muted" style="width:48px;height:48px"><i class="fas fa-rocket"></i></span>
      @endif
      <div class="flex-grow-1 min-w-0">
        <p class="mb-0 is-700">
          {{ $initiative->title }}
          @if($initiative->featured_on_home)
          <span class="badge badge-primary ml-1" title="Destacada en la página de inicio"><i class="fas fa-star"></i> Inicio</span>
          @endif
        </p>
        <p class="mb-1 text-smaller text-muted text-truncate" title="{{ $initiative->subtitle }}">{{ $initiative->subtitle }}</p>
        @foreach($initiative->links ?? [] as $link)
        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="text-smaller d-inline-block mr-2">
          {{ $link['label'] }} <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="sr-only"> (se abre en una pestaña nueva)</span>
        </a>
        @endforeach
        <div>
          @foreach($initiative->categories as $category)
          <span class="badge text-white mr-1" style="background-color: {{ $category->color }}">{{ $category->title }}</span>
          @endforeach
        </div>
      </div>
      <div class="d-flex flex-column flex-md-row text-nowrap">
        <a href="{{ route('admin.initiatives.edit', ['initiative' => $initiative]) }}" class="btn btn-link btn-sm"><i class="fas fa-pencil fa-fw"></i>Editar</a>
        <a href="{{ route('admin.initiatives.delete', ['initiative' => $initiative]) }}" class="btn btn-link btn-sm"><i class="fas fa-trash-can fa-fw"></i>Eliminar</a>
      </div>
    </div>
    @empty
    <div class="list-group-item text-center">
      <h6 class="mb-0">No hay iniciativas cargadas</h6>
    </div>
    @endforelse
  </div>
  {{ $initiatives->links() }}
</section>

@endsection
