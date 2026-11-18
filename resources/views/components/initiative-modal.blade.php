@props(['initiative', 'color', 'modalId'])

@php
  $backgroundUrl = $initiative->backgroundUrl();
  $logoUrl = $initiative->logoUrl();
@endphp

<div class="modal fade initiative-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true" style="--initiative-color: {{ $color }}">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <button type="button" class="initiative-modal__close" data-dismiss="modal" aria-label="Cerrar detalle">
        <i class="fas fa-xmark" aria-hidden="true"></i>
      </button>
      <div class="initiative-modal__content">
        <header class="initiative-card initiative-modal__hero @if($backgroundUrl) initiative-card--has-image @endif" style="--initiative-color: {{ $color }}">
          @if($backgroundUrl)
          <div class="initiative-card__image" style="background-image: url('{{ $backgroundUrl }}')" aria-hidden="true"></div>
          @endif
          <div class="initiative-card__overlay" aria-hidden="true"></div>
          <div class="initiative-card__body">
            @if($logoUrl)
            <div class="initiative-card__logo">
              <img src="{{ $logoUrl }}" alt="" loading="lazy">
            </div>
            @endif
            <h2 id="{{ $modalId }}-title" class="initiative-modal__title">{{ $initiative->title }}</h2>
          </div>
        </header>
        <div class="modal-body">
          <p class="initiative-modal__text">{{ $initiative->subtitle }}</p>
          @if(! empty($initiative->links))
          <h3 class="h6 font-weight-bold">Enlaces externos</h3>
          <ul class="initiative-modal__links list-unstyled mb-0">
            @foreach($initiative->links as $link)
            <li>
              <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">
                <span class="initiative-modal__link-label">{{ $link['label'] }} <i class="fas fa-arrow-up-right-from-square ml-1" aria-hidden="true"></i></span>
                <span class="initiative-modal__link-url">{{ $link['url'] }}</span>
                <span class="sr-only"> (se abre en una pestaña nueva)</span>
              </a>
            </li>
            @endforeach
          </ul>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
