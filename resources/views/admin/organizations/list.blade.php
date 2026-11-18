@extends('admin.master')

@section('adminContent')

<section>
  <h3 class="is-700">Organizaciones</h3>
  <p class="lead">Áreas, organismos, instituciones y otras entidades que se asocian a las metas como organización responsable o participante. Las organizaciones de un objetivo se reúnen desde sus metas.</p>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-smaller text-muted">{{ $organizations->total() }} resultado(s)</span>
  </div>
  <div class="list-group shadow-sm mb-3">
    @forelse($organizations as $organization)
    <div class="list-group-item d-flex align-items-center gap-3">
      <img src="{{ asset($organization->logo?->path ?? 'img/default-background.png') }}" class="rounded flex-shrink-0 object-contain bg-light" width="48" height="48" alt="Logo {{$organization->name}}" title="{{$organization->name}}" />
      <div class="flex-grow-1 min-w-0">
        <p class="mb-0 is-700">
          {{ $organization->name }}
          @unless($organization->is_active)
          <span class="badge badge-secondary align-middle">Inactiva</span>
          @endunless
        </p>
        <p class="mb-0 text-smaller text-muted text-truncate" title="{{ $organization->description }}">{{ $organization->description }}</p>
      </div>
      <span class="text-smaller text-nowrap" title="Metas asociadas como responsable o participante">
        <i class="far fa-circle-dot fa-fw"></i>{{ $organization->responsible_goals_count }} como responsable · {{ $organization->participant_goals_count }} como participante
      </span>
      <a href="{{ route('admin.organizations.edit', ['organizationId' => $organization->id]) }}" class="btn btn-link btn-sm text-nowrap"><i class="fas fa-pencil fa-fw"></i>Editar</a>
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
