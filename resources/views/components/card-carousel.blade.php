@props(['color' => '#009aae', 'label', 'id'])

<section {{ $attributes->class(['initiatives-carousel'])->merge(['style' => '--carousel-color: '.$color]) }} aria-roledescription="carrusel" aria-label="{{ $label }}" data-initiatives-carousel>
  <div class="initiatives-carousel__header">
    <div class="initiatives-carousel__heading">{{ $header }}</div>
    <div class="initiatives-carousel__controls" data-initiatives-carousel-controls hidden>
      <button type="button" class="initiatives-carousel__button" data-initiatives-carousel-prev aria-controls="{{ $id }}" aria-label="{{ $label }}: ver tarjetas anteriores" disabled>
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
      </button>
      <button type="button" class="initiatives-carousel__button" data-initiatives-carousel-next aria-controls="{{ $id }}" aria-label="{{ $label }}: ver más tarjetas">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  </div>
  <ul class="initiatives-carousel__track" id="{{ $id }}" data-initiatives-carousel-track tabindex="0">
    {{ $slot }}
  </ul>
</section>
