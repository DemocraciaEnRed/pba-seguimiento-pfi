@extends('admin.master')
@use('App\Services\Admin\UserDirectory')

@section('adminContent')

<section>
  <h3 class="is-700">Usuarios</h3>
  <p class="lead">Todas las personas registradas en la plataforma. Buscá, filtrá y entrá a la ficha de cada usuario para editar sus datos, roles y objetivos.</p>

  <form method="GET" action="{{ route('admin.users') }}" class="mb-3">
    <div class="d-grid grid-cols-1 grid-cols-md-2 grid-cols-lg-6 gap-2 align-items-end">
      <div class="col-span-md-2">
        <label for="q" class="text-smaller mb-1">Buscar</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Nombre, apellido o email">
      </div>
      <div>
        <label for="verificado" class="text-smaller mb-1">Verificación</label>
        <select id="verificado" name="verificado" class="form-control">
          <option value="">Todos</option>
          <option value="{{ UserDirectory::VERIFIED }}" @selected($filters['verificado'] === UserDirectory::VERIFIED)>Verificados</option>
          <option value="{{ UserDirectory::UNVERIFIED }}" @selected($filters['verificado'] === UserDirectory::UNVERIFIED)>No verificados</option>
        </select>
      </div>
      <div>
        <label for="rol" class="text-smaller mb-1">Rol</label>
        <select id="rol" name="rol" class="form-control">
          <option value="">Todos</option>
          <option value="{{ UserDirectory::ROLE_ADMIN }}" @selected($filters['rol'] === UserDirectory::ROLE_ADMIN)>Administradores</option>
          <option value="{{ UserDirectory::ROLE_USER }}" @selected($filters['rol'] === UserDirectory::ROLE_USER)>Usuarios</option>
        </select>
      </div>
      <div>
        <label for="equipo" class="text-smaller mb-1">Equipos</label>
        <select id="equipo" name="equipo" class="form-control">
          <option value="">Todos</option>
          <option value="{{ UserDirectory::WITH_TEAM }}" @selected($filters['equipo'] === UserDirectory::WITH_TEAM)>Con objetivos</option>
          <option value="{{ UserDirectory::WITHOUT_TEAM }}" @selected($filters['equipo'] === UserDirectory::WITHOUT_TEAM)>Sin objetivos</option>
        </select>
      </div>
      <div>
        <label for="orden" class="text-smaller mb-1">Ordenar por</label>
        <select id="orden" name="orden" class="form-control">
          @foreach(UserDirectory::sortOptions() as $value => $label)
          <option value="{{ $value }}" @selected(($filters['orden'] ?? UserDirectory::SORT_RECENT) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass fa-fw"></i>Filtrar</button>
      @if(array_filter($filters))
      <a href="{{ route('admin.users') }}" class="btn btn-link">Limpiar filtros</a>
      @endif
    </div>
  </form>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="text-smaller text-muted">{{ $users->total() }} resultado(s)</span>
    <a href="{{ route('admin.users.download', array_filter($filters)) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i>Descargar CSV</a>
  </div>

  <div class="list-group shadow-sm mb-3">
    @forelse($users as $user)
    <a href="{{ route('admin.users.show', ['userId' => $user->id]) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
      @include('utils.avatar', ['avatar' => $user->avatar, 'size' => 40, 'thumbnail' => true])
      <div class="flex-grow-1 min-w-0">
        <p class="mb-0 is-600 text-dark">
          {{ $user->fullname }}
          @if($user->roles->contains('name', 'admin'))
          <span class="badge badge-primary align-middle">Admin</span>
          @endif
          @unless($user->hasVerifiedEmail())
          <span class="badge badge-warning align-middle">No verificado</span>
          @endunless
        </p>
        <p class="mb-0 text-smaller text-muted text-truncate">{{ $user->email }}@if($user->organization) · {{ $user->organization }}@endif</p>
      </div>
      <div class="text-right text-smaller text-nowrap">
        <span class="d-block text-dark" title="Objetivos en los que integra el equipo"><i class="fas fa-flag-checkered fa-fw"></i>{{ $user->objectives_count }} {{ $user->objectives_count === 1 ? 'objetivo' : 'objetivos' }}</span>
        <span class="text-muted">Alta: {{ $user->created_at?->format('d/m/Y') ?? '-' }}</span>
      </div>
    </a>
    @empty
    <div class="list-group-item text-center">
      <h6 class="mb-0">No se encontraron usuarios</h6>
    </div>
    @endforelse
  </div>
  {{ $users->links() }}
</section>

@endsection
