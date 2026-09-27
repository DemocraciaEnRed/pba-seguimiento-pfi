<a href="{{ route('catalog') }}#eje-{{ $category->id }}" class="catalog-tree__bar" style="--catalog-color: {{ $category->color }}">
  <span class="catalog-tree__label"><x-category-icon :icon="$category->icon" class="mr-2" />{{ $category->title }}</span>
  <div class="catalog-tree__stats_rows">
    <p>Estrategias <span class="badge badge-light badge-pill">{{ $category->strategicObjectives->count() }}</span></p>
    <p>Objetivos <span class="badge badge-light badge-pill">{{ $category->strategicObjectives->sum(fn($strategicObjective) => $strategicObjective->objectives->count()) }}</span></p>
    <p>Metas <span class="badge badge-light badge-pill">{{ $category->strategicObjectives->sum(fn($strategicObjective) => $strategicObjective->objectives->sum('goals_count')) }}</span></p>
  </div>
</a>
