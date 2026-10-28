@extends('layouts.app')

@section('hero')
<x-hero title="Plan de Fortalecimiento Institucional 2024-2027" subtitle="La hoja de ruta con la que el Ministerio desarrolla las capacidades necesarias para planificar, decidir, ejecutar y evaluar mejor las obras y los servicios públicos. Acá podés recorrer sus ejes, objetivos y metas, y seguir cómo avanzan.">
  <x-slot:actions>
    <a href="#ejes" class="btn btn-light"><i class="fas fa-layer-group mr-1" aria-hidden="true"></i>Recorré los ejes</a>
    @if($pfi = collect($documents)->firstWhere('acronym', 'PFI'))
    <a href="{{ $pfi['url'] }}" class="btn btn-outline-light" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>Descargá el PFI</a>
    @endif
  </x-slot:actions>
</x-hero>
@endsection

@section('content')
<div class="container py-5">
  <div class="row">
    <div class="col-lg-7 mb-4 mb-lg-0">
      <h2 class="h4 is-700 mb-3">¿Qué es el PFI?</h2>
      <p>El Plan de Fortalecimiento Institucional (PFI) 2024-2027 es la hoja de ruta con la que el Ministerio transforma su gestión para que cada obra y cada servicio público genere más y mejores resultados para la ciudadanía bonaerense.</p>
      <p>El PFI acompaña al Plan Estratégico de Infraestructura: el PEI define las prioridades de infraestructura y el PFI desarrolla las capacidades para llevarlas adelante.</p>

      <ol class="plan-path list-unstyled" aria-label="Recorrido del Plan">
        <li>Brechas</li>
        <li>Capacidades</li>
        <li>Resultados</li>
        <li>Impactos</li>
        <li>Valor público</li>
      </ol>
      <p class="text-muted">El Mapa sigue ese recorrido: muestra qué capacidades se están construyendo, qué resultados producen y a qué impactos contribuyen.</p>

      <div class="plan-faq">
        <details class="plan-faq__item">
          <summary>Más sobre el Plan</summary>
          <div class="plan-faq__body">
            <p>El Plan se organiza en siete ejes que despliegan objetivos, metas e iniciativas para mejorar cada etapa del ciclo de la obra pública, desde la planificación y la contratación hasta la ejecución, el control y la rendición de cuentas.</p>
            <p class="mb-0">Involucra a todas las áreas del Ministerio y a sus empresas, entes y organismos, y se construye en diálogo con el ecosistema de actores de las obras y los servicios públicos.</p>
          </div>
        </details>
        <details class="plan-faq__item">
          <summary>¿Cómo se estructura?</summary>
          <div class="plan-faq__body">
            <p>El PFI se organiza en siete ejes:</p>
            <ul class="mb-0">
              <li><b>Ejes transversales</b>, que atraviesan todos los equipos, espacios y decisiones del Ministerio: Integridad, Transparencia y Gobierno Abierto · Desarrollo Sostenible · Género y Diversidad.</li>
              <li><b>Fundamentos institucionales</b>, que constituyen las bases que dan soporte a la gestión: Innovación y Transformación Digital · Planeamiento, Monitoreo y Evaluación · Simplificación, Normalización y Mejora de los Procesos · Aprendizaje y Crecimiento.</li>
            </ul>
          </div>
        </details>
        <details class="plan-faq__item">
          <summary>De la estrategia a la meta</summary>
          <div class="plan-faq__body">
            <p>Cada eje se despliega en cascada:</p>
            <ol class="mb-0">
              <li><b>Objetivos estratégicos:</b> las grandes orientaciones del eje para 2024-2027.</li>
              <li><b>Objetivos generales:</b> lo que el Ministerio se propone lograr dentro de cada estrategia.</li>
              <li><b>Objetivos específicos:</b> compromisos concretos y verificables, con plazos y responsables.</li>
              <li><b>Iniciativas:</b> las acciones, herramientas y entregables concretos que permiten alcanzar cada objetivo.</li>
              <li><b>Indicadores:</b> permiten medir el avance: alcanzada, en progreso, no cumplida o inactiva.</li>
            </ol>
          </div>
        </details>
        <details class="plan-faq__item">
          <summary>¿Cómo se hace el seguimiento?</summary>
          <div class="plan-faq__body">
            <p>Cada área, empresa, ente y organismo del Ministerio tiene su propio Plan alineado al PFI. Así funciona el ciclo:</p>
            <ol class="mb-0">
              <li>Cada área define sus metas en su Plan Operativo Anual, alineadas a los objetivos del PFI.</li>
              <li>Reporta su avance de forma periódica.</li>
              <li>El avance se consolida y revisa en las instancias de seguimiento del Plan.</li>
              <li>Las acciones se ajustan en función de lo aprendido.</li>
              <li>Los resultados se publican en el Mapa.</li>
            </ol>
          </div>
        </details>
      </div>
    </div>

    @if(count($documents))
    <div class="col-lg-5">
      <h2 class="h5 is-700 mb-3">Documentos del Plan</h2>
      <div class="row">
        @foreach($documents as $document)
        <div class="col-6">
          <a href="{{ $document['url'] }}" class="plan-document" target="_blank" rel="noopener noreferrer" aria-label="Descargar {{ $document['title'] }} (PDF, se abre en una pestaña nueva)">
            <img src="{{ $document['cover'] }}" alt="" class="plan-document__cover" loading="lazy">
            <span class="plan-document__title">{{ $document['title'] }}</span>
            <span class="plan-document__description">{{ $document['description'] }}</span>
            <span class="btn btn-primary btn-sm mt-2"><i class="fas fa-download mr-1" aria-hidden="true"></i>Descargar {{ $document['acronym'] }}</span>
          </a>
        </div>
        @endforeach
      </div>
    </div>
    @endif
  </div>
</div>

<nav id="ejes" class="plan-axes-nav" aria-label="Ejes del Plan">
  <div class="container">
    <p class="plan-axes-nav__title">Recorré los ejes</p>
    <ul class="plan-axes-nav__list">
      @foreach($categories as $category)
      <li>
        <a href="#eje-{{ $category->id }}" class="plan-axes-nav__chip" style="--axis-color: {{ $category->color }}">
          <x-category-icon :icon="$category->icon" class="mr-1" />{{ $category->title }}
        </a>
      </li>
      @endforeach
    </ul>
  </div>
</nav>

@forelse($categories as $category)
  @php
    $stats = $statsByCategory->get($category->id, []);
    $goalsTotal = $stats['goals_total'] ?? 0;
    $goalsReached = $stats['goals_reached'] ?? 0;
    $initiatives = $initiativesByCategory->get($category->id, collect());
  @endphp
  <section id="eje-{{ $category->id }}" class="plan-axis" style="--axis-color: {{ $category->color }}" aria-labelledby="eje-{{ $category->id }}-title">
    <div class="container">
      <header class="plan-axis__header">
        <span class="plan-axis__icon"><x-category-icon :icon="$category->icon" /></span>
        <div>
          <p class="plan-axis__eyebrow">Eje {{ $loop->iteration }}</p>
          <h2 id="eje-{{ $category->id }}-title" class="plan-axis__title">{{ $category->title }}</h2>
        </div>
      </header>
      @if($category->description)
      <p class="plan-axis__description">{{ $category->description }}</p>
      @endif

      <dl class="plan-axis__stats">
        <div class="plan-axis__stat">
          <dt>Objetivos estratégicos</dt>
          <dd>{{ $stats['strategic_objectives_count'] ?? $category->strategicObjectives->count() }}</dd>
        </div>
        <div class="plan-axis__stat">
          <dt>Objetivos generales</dt>
          <dd>{{ $stats['objectives_count'] ?? 0 }}</dd>
        </div>
        <div class="plan-axis__stat">
          <dt>Metas</dt>
          <dd>{{ $goalsTotal }}</dd>
        </div>
        <div class="plan-axis__stat">
          <dt>Metas alcanzadas</dt>
          <dd>{{ $goalsTotal > 0 ? round($goalsReached * 100 / $goalsTotal) : 0 }}%</dd>
          <div class="plan-axis__progress" role="progressbar" aria-label="Metas alcanzadas" aria-valuenow="{{ $goalsReached }}" aria-valuemin="0" aria-valuemax="{{ $goalsTotal }}">
            <span style="width: {{ $goalsTotal > 0 ? round($goalsReached * 100 / $goalsTotal) : 0 }}%"></span>
          </div>
        </div>
      </dl>

      <div class="plan-axis__panel" id="eje-{{ $category->id }}-estructura">
        <div class="plan-axis__panel-header">
          <h3 class="plan-axis__subtitle"><i class="fas fa-sitemap mr-2" aria-hidden="true"></i>Objetivos y metas</h3>
          @if($category->strategicObjectives->isNotEmpty())
          <button type="button" class="btn btn-outline-secondary btn-sm" data-catalog-toggle data-catalog-target="#eje-{{ $category->id }}-estructura" aria-expanded="false">
            <i class="fas fa-fw mr-1 fa-expand-alt"></i><span>Expandir todo</span>
          </button>
          @endif
        </div>
        <div class="catalog-tree" style="--catalog-color: {{ $category->color }}">
          @include('portal.catalogs.partials.strategic-objectives-tree', ['strategicObjectives' => $category->strategicObjectives])
        </div>
      </div>

      <div class="plan-axis__block">
        <div class="plan-axis__block-header">
          <h3 class="plan-axis__subtitle"><i class="far fa-file-lines mr-2" aria-hidden="true"></i>Últimos reportes</h3>
          <a href="{{ route('reports', ['category' => $category->id]) }}" class="plan-axis__more">Ver más reportes del eje <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        <portal-home-reports-grid fetch-url="{{ route('apiService.reports', ['category' => $category->id, 'order_by' => 'updated_at,DESC', 'with' => 'report_goal,report_hierarchy,report_cover,report_excerpt,report_highlights', 'size' => 2]) }}" empty-text="Todavía no hay reportes publicados para este eje"></portal-home-reports-grid>
      </div>

      @if($initiatives->isNotEmpty())
      <div class="plan-axis__block">
        <x-initiatives-carousel :initiatives="$initiatives" :color="$category->color" class="plan-axis__carousel" label="Iniciativas del eje {{ $category->title }}">
          <x-slot:header>
            <h3 class="plan-axis__subtitle"><i class="fas fa-rocket mr-2" aria-hidden="true"></i>Iniciativas</h3>
          </x-slot:header>
        </x-initiatives-carousel>
      </div>
      @endif
    </div>
  </section>
@empty
  <div class="container pb-5">
    <div class="alert alert-info">No hay ejes cargados.</div>
  </div>
@endforelse
@endsection

@section('headscripts')
@include('portal.catalogs.partials.tree-toggle-script')
@endsection
