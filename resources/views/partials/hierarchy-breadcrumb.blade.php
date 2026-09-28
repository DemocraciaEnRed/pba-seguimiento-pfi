@php
    $category = $objective->category;
@endphp
<p class="my-1 text-smaller">
  <a href="{{ route('catalog') }}#eje-{{ $category->id }}" style="color: {{ $category->color }}"><x-category-icon :icon="$category->icon" /> {{ $category->title }}</a>
  <span class="text-muted">&nbsp;•&nbsp;<i class="fas fa-compass fa-fw"></i> {{ $objective->strategicObjective->title }}</span>
  <span class="text-muted">&nbsp;›&nbsp;<a href="{{ route('objectives.index', [$objective->id]) }}" class="text-muted"><i class="fas fa-crosshairs fa-fw"></i> {{ $objective->title }}</a></span>
</p>
