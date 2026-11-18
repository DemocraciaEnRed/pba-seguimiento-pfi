@php
$countUnreadNotifications = count(Auth::user()->unreadNotifications);
@endphp

@extends('panel.master')

@section('panelContent')

<section>
  <h3 class="is-700">Resumen de tu cuenta</h3>
  <div class="d-flex align-items-center my-3">
    <div class="mr-3">
      @include('utils.avatar',['avatar' => Auth::user()->avatar, 'size' => 64])
    </div>
    <div>
      <p class="h5 is-700 mb-0">{{ Auth::user()->fullname }}</p>
      <p class="text-muted mb-0">{{ Auth::user()->email }}</p>
    </div>
  </div>
  <hr class="my-4">

  @if(!Auth::user()->hasVerifiedEmail())
  <div class="alert alert-warning my-3">
    <h5 class="is-700"><i class="fas fa-triangle-exclamation"></i>&nbsp;Debe verificar su cuenta</h5>
    Es importante que verifique su cuenta para comenzar a participar en la plataforma. Para hacerlo, haga <a href="{{ route('panel.account.verify') }}">click aquí<i class="fas fa-arrow-right fa-fw"></i></a>
  </div>
  @endif

  @if($deskSummary)
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="is-700 mb-0"><i class="fas fa-briefcase fa-fw"></i>&nbsp;Mesa de trabajo</h5>
    <a href="{{ route('panel.desk') }}" class="text-smaller">Abrir la Mesa<i class="fas fa-arrow-right fa-fw"></i></a>
  </div>
  <div class="d-grid grid-cols-2 grid-cols-lg-3 gap-3 mb-4">
    @include('admin.partials.stat', ['label' => 'Requiere mi acción', 'icon' => 'fas fa-hand-point-right', 'value' => $deskSummary['action'], 'route' => route('panel.desk').'#accion'])
    @include('admin.partials.stat', ['label' => 'Esperando a otra persona', 'icon' => 'far fa-hourglass-half', 'value' => $deskSummary['waiting'], 'route' => route('panel.desk').'#esperando'])
    @include('admin.partials.stat', ['label' => 'Mis metas', 'icon' => 'fas fa-medal', 'value' => $deskSummary['goals'], 'route' => route('panel.desk').'#metas'])
    @if($deskSummary['administration'] !== null)
    @include('admin.partials.stat', ['label' => 'Pendientes de Administración', 'icon' => 'fas fa-user-shield', 'value' => $deskSummary['administration'], 'route' => route('panel.desk').'#administracion'])
    @include('admin.partials.stat', ['label' => 'Alertas de continuidad', 'icon' => 'fas fa-triangle-exclamation', 'value' => $deskSummary['alerts'], 'route' => route('panel.desk').'#alertas'])
    @endif
  </div>
  @endif

  <h5 class="is-700"><i class="far fa-bell"></i>&nbsp;Notificaciones</h5>
  @if($countUnreadNotifications > 0)
  <p>Tenés {{ $countUnreadNotifications }} {{ $countUnreadNotifications > 1 ? 'notificaciones' : 'notificación' }} sin leer. <a href="{{ route('panel.notifications.unread') }}">Ver pendientes<i class="fas fa-arrow-right fa-fw"></i></a></p>
  @else
  <p>No tenés notificaciones pendientes. <a href="{{ route('panel.notifications') }}">Ver todas<i class="fas fa-arrow-right fa-fw"></i></a></p>
  @endif
</section>

@endsection
