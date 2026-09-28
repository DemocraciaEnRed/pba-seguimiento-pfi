@extends('layouts.app')

@section('hero')
<x-hero title="El Plan" subtitle="Recorré el plan completo: ejes, objetivos estratégicos, objetivos y sus metas.">
  <x-slot:actions>
    <button type="button" class="btn btn-outline-light" data-catalog-toggle aria-expanded="false">
      <i class="fas fa-fw fa-expand-alt mr-1"></i><span>Expandir todo</span>
    </button>
  </x-slot:actions>
</x-hero>
@endsection

@section('content')
<div class="container">
  <div class="py-5">

    <div class="catalog-tree" role="tree" aria-label="Estructura del plan">
      @forelse($categories as $category)
        <details id="eje-{{ $category->id }}" class="catalog-tree__branch catalog-tree__branch--category" style="--catalog-color: {{ $category->color }}" open>
          <summary>
            <span class="catalog-tree__label"><x-category-icon :icon="$category->icon" class="mr-2" />{{ $category->title }}</span>
            <div class="catalog-tree__stats_rows">
              <p>Estrategias <span class="badge badge-light">{{ $category->strategicObjectives->count() }}</span></p>
              <p>Objetivos <span class="badge badge-light">{{ $category->strategicObjectives->sum(fn($so) => $so->objectives->count()) }}</span></p>
              <p>Metas <span class="badge badge-light">{{ $category->strategicObjectives->sum(fn($so) => $so->objectives->sum(fn($o) => $o->goals->count())) }}</span></p>
            </div>
          </summary>

          <div class="catalog-tree__children">
            @forelse($category->strategicObjectives as $strategicObjective)
              <details class="catalog-tree__branch catalog-tree__branch--strategic">
                <summary>
                  <span class="catalog-tree__label"><i class="fas fa-compass fa-fw mr-2"></i>{{ $strategicObjective->title }}</span>
                  <div class="catalog-tree__stats_rows">
                    <p>Objetivos <span class="badge badge-light">{{ $strategicObjective->objectives->count() }}</span></p>
                    <p>Metas <span class="badge badge-light">{{ $strategicObjective->objectives->sum(fn($objective) => $objective->goals->count()) }}</span></p>
                  </div>
                </summary>

                <div class="catalog-tree__children">
                  @forelse($strategicObjective->objectives as $objective)
                    <details class="catalog-tree__branch catalog-tree__branch--objective">
                      <summary>
                        <a href="{{ route('objectives.index', $objective->id) }}" class="catalog-tree__link">
                          <i class="fas fa-crosshairs fa-fw mr-2"></i>{{ $objective->title }}
                        </a>
                        <div class="catalog-tree__stats_rows">
                          <p>Alcanzadas <span class="badge badge-light">{{ $objective->goals->where('status', 'reached')->count() }}</span></p>
                          <p>En progreso <span class="badge badge-light">{{ $objective->goals->where('status', 'ongoing')->count() }}</span></p>
                          <p>No cumplidas <span class="badge badge-light">{{ $objective->goals->where('status', 'delayed')->count() }}</span></p>
                          <p>Inactivas <span class="badge badge-light">{{ $objective->goals->where('status', 'inactive')->count() }}</span></p>
                        </div>
                      </summary>

                      <div class="catalog-tree__children">
                        @forelse($objective->goals as $goal)
                          <div class="catalog-tree__leaf">
                            <a href="{{ route('goals.index', $goal->id) }}" class="catalog-tree__link">
                              <i class="fas fa-flag-checkered fa-fw mr-2"></i>{{ $goal->title }}
                            </a>
                          </div>
                        @empty
                          <p class="catalog-tree__empty">No hay metas publicadas.</p>
                        @endforelse
                      </div>
                    </details>
                  @empty
                    <p class="catalog-tree__empty">No hay objetivos publicados.</p>
                  @endforelse
                </div>
              </details>
            @empty
              <p class="catalog-tree__empty">No hay objetivos estratégicos cargados.</p>
            @endforelse
          </div>
        </details>
      @empty
        <div class="alert alert-info">No hay ejes cargados.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection

@section('headscripts')
<script>
  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-catalog-toggle]');
    if (!button) {
      return;
    }

    const shouldExpand = button.getAttribute('aria-expanded') !== 'true';
    document.querySelectorAll('.catalog-tree details').forEach(function (branch) {
      branch.open = shouldExpand;
    });

    button.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
    button.querySelector('i').className = 'fas fa-fw mr-1 ' + (shouldExpand ? 'fa-compress-alt' : 'fa-expand-alt');
    button.querySelector('span').textContent = shouldExpand ? 'Contraer todo' : 'Expandir todo';
  });
</script>
@endsection

