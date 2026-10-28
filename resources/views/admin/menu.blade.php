<x-side-menu.group>
  <x-side-menu.category label="Inicio" icon="fas fa-gauge-high" :href="route('admin.index')" :active="request()->routeIs('admin.index')" />
</x-side-menu.group>
<x-side-menu.group label="Plan">
  <x-side-menu.category label="Ejes" icon="fas fa-tags" :href="route('admin.categories')" :active="request()->routeIs('admin.categories', 'admin.categories.edit')" :create-href="route('admin.categories.create')" :create-active="request()->routeIs('admin.categories.create')" create-label="Crear eje" />
  <x-side-menu.category label="Objetivos estratégicos" icon="fas fa-diagram-project" :href="route('admin.strategic-objectives')" :active="request()->routeIs('admin.strategic-objectives')" />
  <x-side-menu.category label="Objetivos generales" icon="fas fa-flag-checkered" :href="route('admin.objectives')" :active="request()->routeIs('admin.objectives')" :create-href="route('admin.objectives.create')" :create-active="request()->routeIs('admin.objectives.create')" create-label="Crear objetivo general" />
  <x-side-menu.category label="Organizaciones" icon="far fa-building" :href="route('admin.organizations')" :active="request()->routeIs('admin.organizations', 'admin.organizations.edit')" :create-href="route('admin.organizations.create')" :create-active="request()->routeIs('admin.organizations.create')" create-label="Crear organización" />
  <x-side-menu.category label="Carga masiva" icon="fas fa-file-import" :href="route('admin.import')" :active="request()->routeIs('admin.import*')" />
</x-side-menu.group>
<x-side-menu.group label="Participación">
  <x-side-menu.category label="Reportes" icon="far fa-file-lines" :href="route('admin.reports')" :active="request()->routeIs('admin.reports')" />
  <x-side-menu.category label="Comentarios" icon="far fa-comments" :href="route('admin.comments')" :active="request()->routeIs('admin.comments')" />
  <x-side-menu.category label="Actividades" icon="far fa-calendar-days" :create-href="route('admin.events.create')" :create-active="request()->routeIs('admin.events.create')" create-label="Crear actividad">
    <a href="{{ route('admin.events') }}" @class(['item-link', 'is-active' => request()->routeIs('admin.events', 'admin.events.edit')])>Próximas</a>
    <a href="{{ route('admin.events.past') }}" @class(['item-link', 'is-active' => request()->routeIs('admin.events.past')])>Celebradas</a>
  </x-side-menu.category>
</x-side-menu.group>
<x-side-menu.group label="Portal">
  <x-side-menu.category label="Iniciativas" icon="fas fa-rocket" :href="route('admin.initiatives')" :active="request()->routeIs('admin.initiatives', 'admin.initiatives.edit', 'admin.initiatives.delete')" :create-href="route('admin.initiatives.create')" :create-active="request()->routeIs('admin.initiatives.create')" create-label="Crear iniciativa" />
  <x-side-menu.category label="Preguntas frecuentes" icon="fas fa-circle-question" :href="route('admin.faqs')" :active="request()->routeIs('admin.faqs', 'admin.faqs.edit')" :create-href="route('admin.faqs.create')" :create-active="request()->routeIs('admin.faqs.create')" create-label="Agregar pregunta frecuente" />
  <x-side-menu.category label="Personalizar Home" icon="fas fa-house" :href="route('admin.settings.homepage')" :active="request()->routeIs('admin.settings.homepage')" />
  <x-side-menu.category label="SEO y Analytics" icon="fas fa-magnifying-glass-chart" :href="route('admin.settings.seo')" :active="request()->routeIs('admin.settings.seo')" />
  @if(app_setting('app_map_enabled'))
  <x-side-menu.category label="Mapas y georreferencia" icon="fas fa-map-location-dot" :href="route('admin.settings.map')" :active="request()->routeIs('admin.settings.map')" />
  @endif
</x-side-menu.group>
<x-side-menu.group label="Sistema">
  <x-side-menu.category label="Usuarios" icon="fas fa-users" :href="route('admin.users')" :active="request()->routeIs('admin.users*')" />
  <x-side-menu.category label="Bitácora de acciones" icon="fas fa-list-check" :href="route('admin.logs')" :active="request()->routeIs('admin.logs')" />
</x-side-menu.group>
