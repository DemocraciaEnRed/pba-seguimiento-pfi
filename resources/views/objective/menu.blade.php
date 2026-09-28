@php
  $currentRoute = Route::currentRouteName();
	$currentRouteGoalId = Route::current()->parameters()['goalId'] ?? false ;
@endphp

<div class="objective-menu mb-3">
  <div class="card shadow-sm rounded mb-3">
    <ul class="list-unstyled objective-goals-list mb-0">
      <li class="list-item py-2 pl-4 pr-3 {{ $currentRoute == 'objectives.index' ? 'active' : null}}"><a href="{{route('objectives.index',['objectiveId' => $objective->id])}}"><i class="fas fa-house fa-fw"></i>&nbsp;Vista general del objetivo</a></li>
      @forelse ($objective->goals as $goalAux)
      <li class="list-item py-2 pl-4 pr-3 d-flex align-items-baseline {{ $currentRoute == 'goals.index' && $currentRouteGoalId == $goalAux->id ? 'active' : null }}">
        <i class="far fa-circle-dot fa-fw text-{{$goalAux->status}}" title="Meta {{$goalAux->status_label}}"></i>&nbsp;
        <a href="{{route('goals.index',['goalId' => $goalAux->id])}}" class="flex-fill">{{$goalAux->title}}</a>
        @if(!is_null($goalAux->progress_percentage))
        <span class="text-smallest text-muted is-700 ml-2 text-nowrap">{{$goalAux->progress_label}}</span>
        @endif
      </li>
      @empty
      <li class="list-item py-2 pl-4 pr-3 text-muted">No hay metas</li>
      @endforelse
    </ul>
  </div>
  @if(!$objective->communities->isEmpty())
  <div class="card shadow-sm rounded">
    <div class="card-body">
      <p class="text-smaller text-muted mb-2">¡Unite a nuestra comunidad!</p>
      @foreach($objective->communities as $community)
      <a href="{{$community->url}}" target="_blank" rel="noopener" class="py-1 px-2 text-smallest rounded d-inline-block my-1 mb-1" style="border: 1px solid {{$community->color}}; color: {{$community->color}}"><i class="{{$community->icon}}"></i>&nbsp;{{$community->label}}</a>
      @endforeach
    </div>
  </div>
  @endif
</div>
