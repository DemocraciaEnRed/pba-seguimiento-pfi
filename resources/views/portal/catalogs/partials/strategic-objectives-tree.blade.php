@forelse($strategicObjectives as $strategicObjective)
  <details class="catalog-tree__branch catalog-tree__branch--strategic">
    <summary>
      <span class="catalog-tree__label"><i class="fas fa-compass fa-fw mr-2"></i>{{ $strategicObjective->title }}</span>
      <div class="catalog-tree__stats_rows">
        <p>Objetivos generales <span class="badge badge-light">{{ $strategicObjective->objectives->count() }}</span></p>
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
        <p class="catalog-tree__empty">No hay objetivos generales publicados.</p>
      @endforelse
    </div>
  </details>
@empty
  <p class="catalog-tree__empty">No hay objetivos estratégicos cargados.</p>
@endforelse
