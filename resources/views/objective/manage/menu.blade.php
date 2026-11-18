@php
$objectiveParams = ['objectiveId' => $objective->id];
$canManage = Auth::user()->can('update', $objective);
@endphp
@hasRole('admin')
<ul class="list-unstyled mb-0">
<li><a href="{{ route('admin.objectives') }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver a los objetivos</a></li>
</ul>
<hr>
@endhasRole
<x-side-menu.group>
  <x-side-menu.category label="Inicio" icon="fas fa-gauge-high" :href="route('objectives.manage.index', $objectiveParams)" :active="request()->routeIs('objectives.manage.index')" />
</x-side-menu.group>
<x-side-menu.group label="Seguimiento">
  <x-side-menu.category label="Metas" icon="fas fa-medal" :href="route('objectives.manage.goals', $objectiveParams)" :active="request()->routeIs('objectives.manage.goals')" :create-href="$canManage ? route('objectives.manage.goals.add', $objectiveParams) : null" :create-active="request()->routeIs('objectives.manage.goals.add')" create-label="Nueva meta" />
</x-side-menu.group>
<x-side-menu.group label="Participación">
  <x-side-menu.category label="Comentarios" icon="far fa-comments" :href="route('objectives.manage.comments', $objectiveParams)" :active="request()->routeIs('objectives.manage.comments')" />
  <x-side-menu.category label="Comunidades" icon="fas fa-icons" :href="route('objectives.manage.communities', $objectiveParams)" :active="request()->routeIs('objectives.manage.communities', 'objectives.manage.communities.edit')" :create-href="$canManage ? route('objectives.manage.communities.add', $objectiveParams) : null" :create-active="request()->routeIs('objectives.manage.communities.add')" create-label="Nueva comunidad" />
  @if($canManage)
  <x-side-menu.category label="Suscriptores" icon="fas fa-user-tag" :href="route('objectives.manage.subscribers', $objectiveParams)" :active="request()->routeIs('objectives.manage.subscribers')" />
  @endif
</x-side-menu.group>
@if($canManage)
<x-side-menu.group label="Equipo">
  <x-side-menu.category label="Equipos por meta" icon="fas fa-users" :href="route('admin.goal-assignments.objective', $objectiveParams)" />
</x-side-menu.group>
<x-side-menu.group label="Configuración">
  <x-side-menu.category label="Editar objetivo" icon="fas fa-pen" :href="route('objectives.manage.edit', $objectiveParams)" :active="request()->routeIs('objectives.manage.edit')" />
  <x-side-menu.category label="Imagen de portada" icon="far fa-image" :href="route('objectives.manage.cover', $objectiveParams)" :active="request()->routeIs('objectives.manage.cover')" />
  <x-side-menu.category label="Repositorio de archivos" icon="far fa-folder-open" :href="route('objectives.manage.files', $objectiveParams)" :active="request()->routeIs('objectives.manage.files')" />
  @if(app_setting('app_map_enabled'))
  <x-side-menu.category label="Mapa de reportes" icon="fas fa-map-location-dot" :href="route('objectives.manage.map', $objectiveParams)" :active="request()->routeIs('objectives.manage.map')" />
  @endif
  <x-side-menu.category label="Configuración" icon="fas fa-gear" :href="route('objectives.manage.configuration', $objectiveParams)" :active="request()->routeIs('objectives.manage.configuration')" />
  <x-side-menu.category label="Bitácora de acciones" icon="fas fa-list-check" :href="route('objectives.manage.logs', $objectiveParams)" :active="request()->routeIs('objectives.manage.logs')" />
</x-side-menu.group>
@endif
