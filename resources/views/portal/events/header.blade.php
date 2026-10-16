@php
$currentRoute = Route::currentRouteName();
@endphp

<x-hero title="Actividades" subtitle="Aquí podras ver todas las actividades en las cuales podes participar">
  <x-slot:actions>
    <a href="{{route('events.upcoming')}}" @class(['btn', 'btn-light' => $currentRoute == 'events.upcoming', 'btn-outline-light' => $currentRoute != 'events.upcoming'])><i class="fas fa-forward-fast fa-fw"></i> Próximas actividades</a>
    <a href="{{route('events.past')}}" @class(['btn', 'btn-light' => $currentRoute == 'events.past', 'btn-outline-light' => $currentRoute != 'events.past'])><i class="fas fa-clock-rotate-left fa-fw"></i> Actividades celebradas</a>
    @hasRole('admin')
    <a href="{{route('admin.events.create')}}" class="btn btn-secondary"><i class="fas fa-plus fa-fw"></i> Nueva</a>
    @endhasRole
  </x-slot:actions>
</x-hero>
