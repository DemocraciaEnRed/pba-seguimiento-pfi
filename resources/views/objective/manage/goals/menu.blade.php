@php
$goalParams = ['objectiveId' => $objective->id, 'goalId' => $goal->id];
$canManage = Auth::user()->hasRole('admin') || Auth::user()->isManagerObjective($objective->id);
@endphp
<ul class="list-unstyled mb-0">
<li><a href="{{ route('objectives.manage.goals', ['objectiveId' => $objective->id]) }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver a las metas</a></li>
<li><a href="{{ route('objectives.manage.index', ['objectiveId' => $objective->id]) }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver al objetivo</a></li>
</ul>
<hr>
<x-side-menu.group>
  <x-side-menu.category label="Inicio" icon="fas fa-gauge-high" :href="route('objectives.manage.goals.index', $goalParams)" :active="request()->routeIs('objectives.manage.goals.index')" />
</x-side-menu.group>
<x-side-menu.group label="Seguimiento">
  <x-side-menu.category label="Reportes" icon="far fa-file-lines" :href="route('objectives.manage.goals.reports', $goalParams)" :active="request()->routeIs('objectives.manage.goals.reports')" :create-href="route('objectives.manage.goals.reports.add', $goalParams)" :create-active="request()->routeIs('objectives.manage.goals.reports.add')" create-label="Nuevo reporte" />
  <x-side-menu.category label="Hitos" icon="fas fa-star" :href="route('objectives.manage.goals.milestones', $goalParams)" :active="request()->routeIs('objectives.manage.goals.milestones', 'objectives.manage.goals.milestones.edit')" :create-href="$canManage ? route('objectives.manage.goals.milestones.add', $goalParams) : null" :create-active="request()->routeIs('objectives.manage.goals.milestones.add')" create-label="Nuevo hito" />
</x-side-menu.group>
<x-side-menu.group label="Participación">
  <x-side-menu.category label="Comentarios" icon="far fa-comments" :href="route('objectives.manage.goals.comments', $goalParams)" :active="request()->routeIs('objectives.manage.goals.comments')" />
</x-side-menu.group>
@if($canManage)
<x-side-menu.group label="Configuración">
  <x-side-menu.category label="Editar meta" icon="fas fa-pen" :href="route('objectives.manage.goals.edit', $goalParams)" :active="request()->routeIs('objectives.manage.goals.edit')" />
  <x-side-menu.category label="Configuración" icon="fas fa-gear" :href="route('objectives.manage.goals.configuration', $goalParams)" :active="request()->routeIs('objectives.manage.goals.configuration')" />
</x-side-menu.group>
@endif
