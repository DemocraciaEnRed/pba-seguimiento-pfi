<a href="{{ route('admin.users') }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver a los usuarios</a>
<div class="d-flex align-items-center gap-3 mt-3 mb-3">
  @include('utils.avatar', ['avatar' => $user->avatar, 'size' => 64])
  <div class="min-w-0">
    <h3 class="is-700 mb-0">{{ $user->fullname }}</h3>
    <p class="text-muted mb-0">
      {{ $user->email }}
      @if($user->roles->contains('name', 'admin'))
      <span class="badge badge-primary align-middle">Administrador</span>
      @endif
      @if($user->hasVerifiedEmail())
      <span class="badge badge-success align-middle">Verificado</span>
      @else
      <span class="badge badge-warning align-middle">No verificado</span>
      @endif
    </p>
  </div>
</div>
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a href="{{ route('admin.users.show', ['userId' => $user->id]) }}" @class(['nav-link', 'active' => request()->routeIs('admin.users.show')])>Resumen</a>
  </li>
  <li class="nav-item">
    <a href="{{ route('admin.users.objectives', ['userId' => $user->id]) }}" @class(['nav-link', 'active' => request()->routeIs('admin.users.objectives')])>Objetivos</a>
  </li>
</ul>
