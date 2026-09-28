@php
    $heightHeader = 300
@endphp

@if(app_setting('app_map_enabled'))
@section('stylesheets')
<link href='https://api.mapbox.com/mapbox-gl-js/v2.9.1/mapbox-gl.css' rel='stylesheet' />
@endsection

@section('headscripts')
<script src='https://api.mapbox.com/mapbox-gl-js/v2.9.1/mapbox-gl.js'></script>
@endsection
@endif

@extends('layouts.app')

@section('content')
<div class="container push-to-header" style="margin-top: -250px">
  <div class="row justify-content-between align-items-center mb-3 mb-md-5 flex-column-reverse flex-md-row">
    <div class="col-md-5 text-center text-md-left mb-3 mb-md-0">
      <h5 class="text-white">{{app_setting('app_homepage_subtitle')}}</h5>
      <a href="{{route('about.general')}}" class="btn btn-info">Más información <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="col-md-5">
      <img src="{{asset(app_setting('app_logo_white','img/default-logo-white.svg'))}}" class="img-fluid image logo-home ml-md-auto ml-auto mr-auto mr-md-0 mb-3 mb-md-0"
        alt="{{ config('app.name', 'Laravel') }}">
    </div>
  </div>
  <portal-home-stats fetch-url="{{route('apiService.home.stats')}}">
    @include('partials.loading')
  </portal-home-stats>
  @if(app_setting('app_homepage_show_categories_selector'))
  <h4 class="is-400 mb-3">Explorá los ejes de los objetivos</h4>
  @forelse($categories as $category)
    @include('portal.partials.category-bar', ['category' => $category])
  @empty
    <div class="alert alert-info">No hay ejes cargados en la plataforma.</div>
  @endforelse
  <p class="mb-4 text-right"><a href="{{route('catalog')}}" class="btn btn-outline-primary">Explorá el catálogo con todos los objetivos y metas de los ejes <i class="fas fa-arrow-right"></i></a></p>
  @endif


  @if(app_setting('app_homepage_show_latest_reports') && !app_setting('app_homepage_latest_reports_at_the_end'))
  <h4 class="is-400 mb-3">Ultimos reportes publicados</h4>
  <portal-home-reports-grid fetch-url="{{route('apiService.reports',['order_by'=>'updated_at,DESC','with'=>'report_goal,report_hierarchy,report_cover,report_excerpt,report_highlights','size' => 8])}}"></portal-home-reports-grid>
  <p class="mb-4 text-right"><a href="{{route('reports')}}" class="btn btn-outline-primary">Ver más reportes <i class="fas fa-arrow-right"></i></a></p>
  @endif
  <h4 class="is-400 mb-3">Ultimos objetivos actualizados</h4>
  <portal-last-objectives fetch-url="{{route('apiService.objectives',['order_by'=>'updated_at,DESC','with'=>'objective_latest_goals,objective_latest_report,objective_stats,objective_strategic_objective','size' => 5])}}"></portal-last-objectives>
  <p class="mb-4 text-right"><a href="{{route('objectives')}}" class="btn btn-outline-primary">Ver más objetivos <i class="fas fa-arrow-right"></i></a></p>
  @if(app_setting('app_homepage_show_latest_reports') && app_setting('app_homepage_latest_reports_at_the_end'))
  <h4 class="is-400 mb-3">Ultimos reportes publicados</h4>
  <portal-home-reports-grid fetch-url="{{route('apiService.reports',['order_by'=>'updated_at,DESC','with'=>'report_goal,report_hierarchy,report_cover,report_excerpt,report_highlights','size' => 8])}}"></portal-home-reports-grid>
  <p class="mb-4 text-right"><a href="{{route('reports')}}" class="btn btn-outline-primary">Ver más reportes <i class="fas fa-arrow-right"></i></a></p>
  @endif
  @if(app_setting('app_map_enabled') && app_setting('app_homepage_show_map'))
    <h4 class="is-400 mb-3">Ultimos 15 reportes geolocalizados</h4>
    <map-reports fetch-url="{{route('apiService.reports',['mappable' => true, 'order_by'=>'updated_at,DESC', 'size'=> 15])}}" :paginated="false" access-token="{{app_setting('app_mapbox_api_key')}}" map-style="{{app_setting('app_mapbox_style')}}" :lat="{{app_setting('app_map_lat_default') ?: 'undefined'}}" :long="{{app_setting('app_map_long_default') ?: 'undefined'}}" :zoom="{{app_setting('app_map_zoom_default') ?: 'undefined'}}">
  @endif
</div>
@endsection

