@php
    $heightHeader = 100
@endphp

@extends('layouts.app')

@section('content')
<div class="container">
  <div class="py-5">
    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-end" style="gap: 1rem;">
      <div>
        <h3 class="is-700 mb-2">Catálogo</h3>
        <p class="lead mb-0">Explorá los ejes, objetivos estratégicos, objetivos y metas de la plataforma.</p>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-catalog-toggle aria-expanded="false">
        <i class="fas fa-fw fa-expand-alt mr-1"></i><span>Expandir todo</span>
      </button>
    </div>

    <div class="catalog-tree" role="tree" aria-label="Catálogo de objetivos">
      @forelse($categories as $category)
        <details class="catalog-tree__branch catalog-tree__branch--category" style="--catalog-color: {{ $category->color }}" open>
          <summary>
            <span class="catalog-tree__label"><x-category-icon :icon="$category->icon" class="mr-2" />{{ $category->title }}</span>
            <div class="catalog-tree__stats_rows">
              <p>Estrategias <span class="badge badge-light badge-pill">{{ $category->strategicObjectives->count() }}</span></p>
              <p>Objetivos <span class="badge badge-light badge-pill">{{ $category->strategicObjectives->sum(fn($so) => $so->objectives->count()) }}</span></p>
              <p>Metas <span class="badge badge-light badge-pill">{{ $category->strategicObjectives->sum(fn($so) => $so->objectives->sum(fn($o) => $o->goals->count())) }}</span></p>
            </div>
          </summary>

          <div class="catalog-tree__children">
            @forelse($category->strategicObjectives as $strategicObjective)
              <details class="catalog-tree__branch catalog-tree__branch--strategic">
                <summary>
                  <span class="catalog-tree__label"><i class="fas fa-compass fa-fw mr-2"></i>{{ $strategicObjective->title }}</span>
                  <div class="catalog-tree__stats_rows">
                    <p>Objetivos <span class="badge badge-light badge-pill">{{ $strategicObjective->objectives->count() }}</span></p>
                    <p>Metas <span class="badge badge-light badge-pill">{{ $strategicObjective->objectives->sum(fn($objective) => $objective->goals->count()) }}</span></p>
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
                          <p>Alcanzadas <span class="badge badge-light badge-pill">{{ $objective->goals->where('status', 'reached')->count() }}</span></p>
                          <p>En progreso <span class="badge badge-light badge-pill">{{ $objective->goals->where('status', 'ongoing')->count() }}</span></p>
                          <p>No cumplidas <span class="badge badge-light badge-pill">{{ $objective->goals->where('status', 'delayed')->count() }}</span></p>
                          <p>Inactivas <span class="badge badge-light badge-pill">{{ $objective->goals->where('status', 'inactive')->count() }}</span></p>
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

@section('stylesheets')
<style>
  .catalog-tree__branch {
    border-left: 2px solid #dee2e6;
    margin-left: .75rem;
  }

  .catalog-tree__branch summary {
    align-items: center;
    border-left: 4px solid var(--catalog-color, #6c757d);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    list-style: none;
    min-height: 2.5rem;
    padding: .25rem 1rem;
  }

  .catalog-tree__branch .catalog-tree__label {
    flex: 1;
  }

  .catalog-tree__branch summary::-webkit-details-marker {
    display: none;
  }

  .catalog-tree__branch summary::before {
    color: var(--catalog-color, #6c757d);
    content: '\f054';
    font-family: 'Font Awesome 7 Free';
    font-weight: 900;
    margin-right: .75rem;
    transition: transform .15s ease-in-out;
  }

  .catalog-tree__branch[open] > summary::before {
    transform: rotate(90deg);
  }

  .catalog-tree__branch--category {
    border-left: 0;
    margin-left: 0;
    margin: 1rem 0;
  }

  .catalog-tree__branch--category > summary {
    border-radius: 1rem;
    width: 100%;
    text-align: left;
    background: #f1f7f8;
    background-color: var(--catalog-color, #155d68);
    color: #fff;
    font-size: 1.35rem;
    font-weight: 700;
    padding: 0.8rem 1.1rem;
  }
    .catalog-tree__branch--category > summary::before {
    color: #fff
  }

  .catalog-tree__branch--strategic > summary {
    --catalog-color: #6f42c1;
    color: #343a40;
    font-weight: 600;
  }

  .catalog-tree__branch--objective > summary {
    --catalog-color: #007bff;
  }

  .catalog-tree__branch--objective > summary .catalog-tree__link {
    color: #0056b3;
  }

  .catalog-tree__leaf {
    --catalog-color: #28a745;
  }

  .catalog-tree__children {
    margin-left: 1.25rem;
  }

  .catalog-tree__branch--objective > summary,
  .catalog-tree__leaf {
    border-top: 1px solid #edf0f2;
  }

  .catalog-tree__link {
    color: inherit;
    flex: 1;
    text-decoration: none;
  }

  .catalog-tree__link:hover {
    color: #17a2b8;
  }

  .catalog-tree__leaf {
    padding: .7rem 1rem .7rem 2.35rem;
  }

  .catalog-tree__leaf .catalog-tree__link {
    color: #218838;
  }

  .catalog-tree__empty {
    color: #6c757d;
    font-size: .9rem;
    margin: 0;
    padding: .65rem 1rem;
  }

  .catalog-tree__stats_rows {
    display: flex;
    flex-direction: row;
    margin: 0;
    gap: 0.75rem;
    font-size: 0.85rem;
    font-weight: 300;
  }

  .catalog-tree__stats_rows p {
    align-items: center;
    display: flex;
    gap: .45rem;
    justify-content: flex-end;
    margin: 0;
    text-align: right;
  }
</style>
@endsection
