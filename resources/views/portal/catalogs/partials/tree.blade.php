{{-- Árbol completo del plan (ejes → objetivos estratégicos → objetivos generales → metas). Usar junto con partials.tree-toggle-script. --}}
<div class="catalog-tree" role="tree" aria-label="Estructura del plan">
  @forelse($categories as $category)
    <details id="eje-{{ $category->id }}" class="catalog-tree__branch catalog-tree__branch--category" style="--catalog-color: {{ $category->color }}" open>
      <summary>
        <span class="catalog-tree__label"><x-category-icon :icon="$category->icon" class="mr-2" />{{ $category->title }}</span>
        <div class="catalog-tree__stats_rows">
          <p>Objetivos Estratégicos <span class="badge badge-light">{{ $category->strategicObjectives->count() }}</span></p>
          <p>Objetivos Generales <span class="badge badge-light">{{ $category->strategicObjectives->sum(fn($so) => $so->objectives->count()) }}</span></p>
          <p>Metas <span class="badge badge-light">{{ $category->strategicObjectives->sum(fn($so) => $so->objectives->sum(fn($o) => $o->goals->count())) }}</span></p>
        </div>
      </summary>

      <div class="catalog-tree__children">
        @include('portal.catalogs.partials.strategic-objectives-tree', ['strategicObjectives' => $category->strategicObjectives])
      </div>
    </details>
  @empty
    <div class="alert alert-info">No hay ejes cargados.</div>
  @endforelse
</div>
