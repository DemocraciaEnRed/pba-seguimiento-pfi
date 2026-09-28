@php
$currentRoute = Route::currentRouteName();
@endphp

<x-hero align="center">
  <h1 class="hero__title"><i class="fas fa-calendar-days"></i>&nbsp;Eventos</h1>
  <x-slot:actions>
    <a href="{{route('events.upcoming')}}" @class(['btn', 'btn-light' => $currentRoute == 'events.upcoming', 'btn-outline-light' => $currentRoute != 'events.upcoming'])><i class="fas fa-forward-fast fa-fw"></i> Próximos eventos</a>
    <a href="{{route('events.past')}}" @class(['btn', 'btn-light' => $currentRoute == 'events.past', 'btn-outline-light' => $currentRoute != 'events.past'])><i class="fas fa-clock-rotate-left fa-fw"></i> Eventos celebrados</a>
    @hasRole('admin')
    <a href="{{route('admin.events.create')}}" class="btn btn-secondary"><i class="fas fa-plus fa-fw"></i> Nuevo</a>
    @endhasRole
  </x-slot:actions>
</x-hero>
