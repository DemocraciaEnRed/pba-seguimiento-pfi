@php
$reportParams = ['objectiveId' => $objective->id, 'goalId' => $goal->id, 'reportId' => $report->id];
@endphp
<ul class="list-unstyled mb-0">
<li><a href="{{ route('objectives.manage.goals.reports', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver a los reportes</a></li>
<li><a href="{{ route('objectives.manage.goals.index', ['objectiveId' => $objective->id, 'goalId' => $goal->id]) }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver a la meta</a></li>
<li><a href="{{ route('objectives.manage.index', ['objectiveId' => $objective->id]) }}" class="text-smaller text-dark"><i class="fas fa-arrow-left"></i>&nbsp;Volver al objetivo</a></li>
</ul>
<hr>
<x-side-menu.group>
  <x-side-menu.category label="Inicio" icon="fas fa-gauge-high" :href="route('objectives.manage.goals.reports.index', $reportParams)" :active="request()->routeIs('objectives.manage.goals.reports.index')" />
</x-side-menu.group>
@if($report->isApproved())
<x-side-menu.group label="Participación">
  <x-side-menu.category label="Comentarios" icon="far fa-comments" :href="route('objectives.manage.goals.reports.comments', $reportParams)" :active="request()->routeIs('objectives.manage.goals.reports.comments')" />
  <x-side-menu.category label="Me gusta" icon="far fa-thumbs-up" :href="route('objectives.manage.goals.reports.testimonies', $reportParams)" :active="request()->routeIs('objectives.manage.goals.reports.testimonies')" />
</x-side-menu.group>
@endif
<x-side-menu.group label="Contenido">
  @if($report->canManage(auth()->user()))
  <x-side-menu.category label="Editar reporte" icon="fas fa-pen" :href="route('objectives.manage.goals.reports.edit', $reportParams)" :active="request()->routeIs('objectives.manage.goals.reports.edit')" />
  @endif
  <x-side-menu.category label="Álbum de fotos" icon="far fa-images" :href="route('objectives.manage.goals.reports.album', $reportParams)" :active="request()->routeIs('objectives.manage.goals.reports.album')" />
  <x-side-menu.category label="Repositorio de archivos" icon="far fa-folder-open" :href="route('objectives.manage.goals.reports.files', $reportParams)" :active="request()->routeIs('objectives.manage.goals.reports.files')" />
  @if(app_setting('app_map_enabled') && $report->canManage(auth()->user()))
  <x-side-menu.category label="Mapa" icon="fas fa-map-location-dot" :href="route('objectives.manage.goals.reports.map', $reportParams)" :active="request()->routeIs('objectives.manage.goals.reports.map')" />
  @endif
  @if($report->canDelete(auth()->user()))
  <x-side-menu.category label="Configuración" icon="fas fa-gear" :href="route('objectives.manage.goals.reports.configuration', $reportParams)" :active="request()->routeIs('objectives.manage.goals.reports.configuration')" />
  @endif
</x-side-menu.group>
