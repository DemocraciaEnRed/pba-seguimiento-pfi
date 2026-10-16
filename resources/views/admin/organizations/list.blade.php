@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Organizaciones</h3>
  <p class="lead">En esta sección se podrán cargar organizaciones de la sociedad civil, agrupaciones conformadas por ciudadanos independientes, o bien, cualquier sector que pueda estar asociado a un objetivo.</p>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-smaller text-muted">{{ $organizations->total() }} resultado(s)</span>
  </div>
  <div class="list-group shadow-sm mb-3">
    @forelse($organizations as $organization)
    <div class="list-group-item d-flex align-items-center gap-3">
      <img src="{{ asset($organization->logo?->path ?? 'img/default-background.png') }}" class="rounded flex-shrink-0 object-contain bg-light" width="48" height="48" alt="Logo {{$organization->name}}" title="{{$organization->name}}" />
      <div class="flex-grow-1 min-w-0">
        <p class="mb-0 is-700">{{ $organization->name }}</p>
        <p class="mb-0 text-smaller text-muted text-truncate" title="{{ $organization->description }}">{{ $organization->description }}</p>
      </div>
      <span class="text-smaller text-nowrap" title="Objetivos asociados"><i class="fas fa-flag-checkered fa-fw"></i>{{ $organization->objectives_count }} {{ $organization->objectives_count === 1 ? 'objetivo' : 'objetivos' }}</span>
      <div class="d-flex flex-column flex-md-row text-nowrap">
        <a href="{{ route('admin.organizations.edit', ['organizationId' => $organization->id]) }}" class="btn btn-link btn-sm"><i class="fas fa-pencil fa-fw"></i>Editar</a>
        <a href="{{ route('admin.organizations.delete', ['organizationId' => $organization->id]) }}" class="btn btn-link btn-sm"><i class="fas fa-trash-can fa-fw"></i>Eliminar</a>
      </div>
    </div>
    @empty
    <div class="list-group-item text-center">
      <h6 class="mb-0">No hay organizaciones creadas</h6>
    </div>
    @endforelse
  </div>
  {{ $organizations->links() }}

</section>

@endsection
