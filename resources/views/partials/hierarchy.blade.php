@php
    $category = $objective->category;
    $goal = $goal ?? null;
@endphp
<div class="hierarchy-tree">
  <div class="hierarchy-tree__row" style="--catalog-color: {{ $category->color }}">
    <span class="hierarchy-tree__icon"><x-category-icon :icon="$category->icon" /></span>
    <div>
      <span class="hierarchy-tree__level">Eje</span>
      <a class="hierarchy-tree__title" href="{{ route('catalog') }}#eje-{{ $category->id }}">{{ $category->title }}</a>
    </div>
  </div>
  <div class="hierarchy-tree__row hierarchy-tree__row--strategic">
    <span class="hierarchy-tree__icon"><i class="fas fa-compass fa-fw"></i></span>
    <div>
      <span class="hierarchy-tree__level">Objetivo estratégico</span>
      <span class="hierarchy-tree__title">{{ $objective->strategicObjective->title }}</span>
    </div>
  </div>
  <div class="hierarchy-tree__row hierarchy-tree__row--objective">
    <span class="hierarchy-tree__icon"><i class="fas fa-crosshairs fa-fw"></i></span>
    <div>
      <span class="hierarchy-tree__level">Objetivo específico</span>
      <a class="hierarchy-tree__title" href="{{ route('objectives.index', [$objective->id]) }}">{{ $objective->title }}</a>
    </div>
  </div>
  @if($goal)
  <div class="hierarchy-tree__row hierarchy-tree__row--goal">
    <span class="hierarchy-tree__icon"><i class="fas fa-flag-checkered fa-fw"></i></span>
    <div class="w-100">
      <span class="hierarchy-tree__level">Meta</span>
      <a class="hierarchy-tree__title" href="{{ route('goals.index', [$goal->id]) }}">{{ $goal->title }}</a>
      <span class="d-block text-smaller is-700 text-{{ $goal->status }}"><i class="far fa-circle-dot fa-fw"></i> {{ $goal->status_label }} · {{ $goal->progress_label }}</span>
    </div>
  </div>
  @endif
</div>
