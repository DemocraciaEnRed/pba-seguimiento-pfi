@extends('admin.master')
@use('App\Services\Admin\UserDirectory')
@use('App\Services\Admin\ObjectiveDirectory')

@section('adminContent')

<section class="admin-dashboard">
  <h5 class="is-700 mb-3"><i class="fas fa-sitemap fa-fw"></i>&nbsp;Estructura del plan</h5>
  <div class="d-grid grid-cols-2 grid-cols-md-3 grid-cols-lg-5 gap-3 mb-4">
    @foreach([
      ['label' => 'Ejes', 'icon' => 'fas fa-tags', 'value' => $stats['structure']['categories'], 'route' => route('admin.categories')],
      ['label' => 'Obj. estratégicos', 'icon' => 'fas fa-diagram-project', 'value' => $stats['structure']['strategic_objectives'], 'route' => route('admin.strategic-objectives')],
      ['label' => 'Obj. publicados', 'icon' => 'fas fa-flag-checkered', 'value' => $stats['structure']['objectives_published'], 'route' => route('admin.objectives', ['visibilidad' => ObjectiveDirectory::VISIBLE])],
      ['label' => 'Obj. ocultos', 'icon' => 'far fa-eye-slash', 'value' => $stats['structure']['objectives_hidden'], 'route' => route('admin.objectives', ['visibilidad' => ObjectiveDirectory::HIDDEN])],
      ['label' => 'Metas', 'icon' => 'fas fa-medal', 'value' => $stats['structure']['goals'], 'route' => null],
    ] as $item)
      @include('admin.partials.stat', $item)
    @endforeach
  </div>

  <h5 class="is-700 mb-1"><i class="fas fa-traffic-light fa-fw"></i>&nbsp;Semáforo de cumplimiento</h5>
  <p class="text-smaller text-muted">Metas activas de objetivos publicados, según el cumplimiento a la fecha.</p>
  <div class="d-grid grid-cols-2 grid-cols-lg-4 gap-3 mb-4">
    @foreach([
      ['label' => 'Cumplido', 'icon' => 'fas fa-circle text-success', 'value' => $stats['traffic_lights']['green']],
      ['label' => 'Levemente por debajo', 'icon' => 'fas fa-circle text-warning', 'value' => $stats['traffic_lights']['yellow']],
      ['label' => 'Incumplido', 'icon' => 'fas fa-circle text-danger', 'value' => $stats['traffic_lights']['red']],
      ['label' => 'Sin medición', 'icon' => 'far fa-circle text-muted', 'value' => $stats['traffic_lights']['unmeasured']],
    ] as $item)
      @include('admin.partials.stat', $item)
    @endforeach
  </div>

  <div class="d-flex flex-wrap justify-content-between align-items-center column-gap-3 mb-2">
    <h5 class="is-700 mb-0"><i class="fas fa-chart-line fa-fw"></i>&nbsp;Actividad</h5>
    <ul class="nav nav-pills admin-dashboard__ranges">
      @foreach($ranges as $option)
      <li class="nav-item">
        <a href="{{ route('admin.index', ['rango' => $option->value]) }}" @class(['nav-link py-1 px-2 text-smaller', 'active' => $option === $range])>{{ $option->label() }}</a>
      </li>
      @endforeach
    </ul>
  </div>
  <div class="d-grid grid-cols-2 grid-cols-lg-4 gap-3 mb-4">
    @foreach([
      ['label' => 'Reportes', 'icon' => 'far fa-file-lines', 'value' => $stats['activity']['reports'], 'route' => route('admin.reports', array_filter(['desde' => $range->bounds()[0]?->format('Y-m-d'), 'hasta' => $range->bounds()[1]?->format('Y-m-d')]))],
      ['label' => 'Comentarios', 'icon' => 'far fa-comments', 'value' => $stats['activity']['comments'], 'route' => route('admin.comments')],
      ['label' => 'Me gusta', 'icon' => 'far fa-thumbs-up', 'value' => $stats['activity']['likes']],
      ['label' => 'Usuarios nuevos', 'icon' => 'fas fa-user-plus', 'value' => $stats['activity']['users']],
    ] as $item)
      @include('admin.partials.stat', $item)
    @endforeach
  </div>

  <h5 class="is-700 mb-3"><i class="fas fa-users fa-fw"></i>&nbsp;Usuarios</h5>
  <div class="d-grid grid-cols-2 grid-cols-lg-4 gap-3 mb-4">
    @foreach([
      ['label' => 'Registrados', 'icon' => 'fas fa-users', 'value' => $stats['users']['total'], 'route' => route('admin.users')],
      ['label' => 'Verificados', 'icon' => 'fas fa-user-check', 'value' => $stats['users']['verified'], 'route' => route('admin.users', ['verificado' => UserDirectory::VERIFIED])],
      ['label' => 'No verificados', 'icon' => 'fas fa-user-clock', 'value' => $stats['users']['unverified'], 'route' => route('admin.users', ['verificado' => UserDirectory::UNVERIFIED])],
      ['label' => 'Administradores', 'icon' => 'fas fa-user-shield', 'value' => $stats['users']['administrators'], 'route' => route('admin.users', ['rol' => UserDirectory::ROLE_ADMIN])],
    ] as $item)
      @include('admin.partials.stat', $item)
    @endforeach
  </div>

  <hr class="my-4">

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="is-700 mb-0"><i class="far fa-comments fa-fw"></i>&nbsp;Últimos comentarios</h5>
    <a href="{{ route('admin.comments') }}" class="text-smaller">Ver todos<i class="fas fa-arrow-right fa-fw"></i></a>
  </div>
  @forelse($latestComments as $comment)
    @include('partials.comments.item', ['comment' => $comment, 'excerpt' => true])
  @empty
  <p class="text-muted text-smaller">Todavía no hay comentarios.</p>
  @endforelse

  <hr class="my-4">

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="is-700 mb-0"><i class="fas fa-list-check fa-fw"></i>&nbsp;Últimas acciones</h5>
    <a href="{{ route('admin.logs') }}" class="text-smaller">Ver bitácora<i class="fas fa-arrow-right fa-fw"></i></a>
  </div>
  @forelse($logs as $log)
  <div class="text-smaller">
  <p class="mb-1"><b>@datetime($log->record_datetime)</b> - {{$log->message}} <a data-toggle="collapse" href="#collapse{{$log->id}}" role="button" aria-expanded="false"><i class="fas fa-code fa-fw"></i></a></p>
  <p id="collapse{{$log->id}}" class="collapse"><code>{{$log->context}}</code></p>
  </div>
  @empty
  <p class="text-muted text-smaller">No hay acciones registradas.</p>
  @endforelse
</section>

@endsection
