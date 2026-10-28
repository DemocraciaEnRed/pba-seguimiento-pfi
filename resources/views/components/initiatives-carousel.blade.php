@props(['initiatives', 'color' => '#009aae', 'label' => 'Iniciativas'])

@php
  $carouselId = 'initiatives-'.\Illuminate\Support\Str::random(6);
@endphp

@if($initiatives->isNotEmpty())
<section {{ $attributes->class(['initiatives-carousel'])->merge(['style' => '--carousel-color: '.$color]) }} aria-roledescription="carrusel" aria-label="{{ $label }}" data-initiatives-carousel>
  <div class="initiatives-carousel__header">
    @isset($header)
    <div class="initiatives-carousel__heading">{{ $header }}</div>
    @endisset
    <div class="initiatives-carousel__controls" data-initiatives-carousel-controls hidden>
      <button type="button" class="initiatives-carousel__button" data-initiatives-carousel-prev aria-controls="{{ $carouselId }}" aria-label="Ver iniciativas anteriores" disabled>
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
      </button>
      <button type="button" class="initiatives-carousel__button" data-initiatives-carousel-next aria-controls="{{ $carouselId }}" aria-label="Ver más iniciativas">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  </div>
  <ul class="initiatives-carousel__track" id="{{ $carouselId }}" data-initiatives-carousel-track tabindex="0">
    @foreach($initiatives as $initiative)
    <li class="initiatives-carousel__item">
      <x-initiative-card :initiative="$initiative" :color="$color" />
    </li>
    @endforeach
  </ul>
</section>
@endif
