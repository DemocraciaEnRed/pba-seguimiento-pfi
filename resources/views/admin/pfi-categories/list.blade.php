@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Categorías PFI</h3>
  <p class="lead">Clasifican a las personas que integran los equipos PFI. Cada integrante tiene una o más categorías. Las categorías no otorgan permisos y no se eliminan: se desactivan para que no puedan elegirse en nuevas asignaciones.</p>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-smaller text-muted">{{ $categories->count() }} categoría(s)</span>
    <a href="{{ route('admin.pfi-categories.create') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus fa-fw"></i>Crear categoría PFI</a>
  </div>
  <div class="list-group shadow-sm mb-3">
    @forelse($categories as $category)
    <div class="list-group-item d-flex align-items-center gap-3">
      <div class="flex-grow-1 min-w-0">
        <p class="mb-0 is-700">
          {{ $category->name }}
          @unless($category->is_active)
          <span class="badge badge-secondary align-middle">Inactiva</span>
          @endunless
        </p>
        <a href="{{ route('admin.users', ['categoria_pfi' => $category->id]) }}" class="text-smaller">{{ $category->users_count }} {{ $category->users_count === 1 ? 'integrante' : 'integrantes' }}</a>
      </div>
      <a href="{{ route('admin.pfi-categories.edit', ['pfiCategory' => $category]) }}" class="btn btn-link btn-sm text-nowrap"><i class="fas fa-pencil fa-fw"></i>Editar</a>
    </div>
    @empty
    <div class="list-group-item text-center">
      <h6 class="mb-0">No hay categorías PFI cargadas</h6>
    </div>
    @endforelse
  </div>
</section>

@endsection
