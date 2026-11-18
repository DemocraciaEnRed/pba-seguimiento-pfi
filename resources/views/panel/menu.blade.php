@php
$countUnreadNotifications = count(Auth::user()->unreadNotifications);
@endphp

<x-side-menu.group>
  <x-side-menu.category label="Mi panel" icon="fas fa-gauge-high" :href="route('panel.index')" :active="request()->routeIs('panel.index')" />
  @can('accessWorkDesk')
  <x-side-menu.category label="Mesa de trabajo" icon="fas fa-briefcase" :href="route('panel.desk')" :active="request()->routeIs('panel.desk')" />
  @endcan
</x-side-menu.group>
<x-side-menu.group label="Actividad">
  <x-side-menu.category label="Notificaciones" icon="fas fa-bell">
    <a href="{{ route('panel.notifications.unread') }}" @class(['item-link', 'is-active' => request()->routeIs('panel.notifications.unread')])>Pendientes @if($countUnreadNotifications > 0)<span class="badge badge-primary badge-pill align-middle">{{ $countUnreadNotifications }}</span>@endif</a>
    <a href="{{ route('panel.notifications') }}" @class(['item-link', 'is-active' => request()->routeIs('panel.notifications')])>Todas</a>
  </x-side-menu.category>
  <x-side-menu.category label="Mis objetivos generales" icon="fas fa-marker" :href="route('panel.objectives')" :active="request()->routeIs('panel.objectives')" />
  @if(Auth::user()->responsibleGoals()->exists())
  <x-side-menu.category label="Reporteros de mis metas" icon="fas fa-users-gear" :href="route('panel.goal-assignments.index')" :active="request()->routeIs('panel.goal-assignments*')" />
  @endif
  <x-side-menu.category label="Mis suscripciones" icon="fas fa-envelope" :href="route('panel.subscriptions')" :active="request()->routeIs('panel.subscriptions')" />
</x-side-menu.group>
<x-side-menu.group label="Mi cuenta">
  <x-side-menu.category label="Mis datos" icon="fas fa-user" :href="route('panel.account.data')" :active="request()->routeIs('panel.account.data')" />
  <x-side-menu.category label="Email" icon="fas fa-at" :href="route('panel.account.email')" :active="request()->routeIs('panel.account.email')" />
  <x-side-menu.category label="Avatar" icon="fas fa-circle-user" :href="route('panel.account.avatar')" :active="request()->routeIs('panel.account.avatar')" />
  <x-side-menu.category label="Contraseña" icon="fas fa-key" :href="route('panel.account.access')" :active="request()->routeIs('panel.account.access')" />
  <x-side-menu.category label="Preferencias de envío" icon="fas fa-sliders" :href="route('panel.account.notifications')" :active="request()->routeIs('panel.account.notifications')" />
</x-side-menu.group>
