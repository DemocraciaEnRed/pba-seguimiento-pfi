@props(['initiative', 'color' => '#009aae'])

@php
  $color = is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#009aae';
  $backgroundUrl = $initiative->backgroundUrl();
  $logoUrl = $initiative->logoUrl();
  $modalId = 'initiative-detail-'.\Illuminate\Support\Str::random(12);
@endphp

<article {{ $attributes->class(['initiative-card', 'initiative-card--interactive', 'initiative-card--has-image' => $backgroundUrl])->style(["--initiative-color: {$color}"]) }}>
  @if($backgroundUrl)
  <div class="initiative-card__image" style="background-image: url('{{ $backgroundUrl }}')" aria-hidden="true"></div>
  @endif
  <div class="initiative-card__overlay" aria-hidden="true"></div>
  <i class="fas fa-expand initiative-card__corner" aria-hidden="true"></i>
  <div class="initiative-card__body">
    @if($logoUrl)
    <div class="initiative-card__logo">
      <img src="{{ $logoUrl }}" alt="" loading="lazy">
    </div>
    @endif
    <div class="initiative-card__text">
      <h3 class="initiative-card__title" title="{{ $initiative->title }}">{{ $initiative->title }}</h3>
      <p class="initiative-card__subtitle">{{ $initiative->subtitle }}</p>
    </div>
    <span class="initiative-card__cta" aria-hidden="true">Ver detalle <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
  </div>
  <button type="button" class="initiative-card__trigger" data-toggle="modal" data-target="#{{ $modalId }}" aria-controls="{{ $modalId }}" aria-haspopup="dialog" aria-label="Ver detalle de {{ $initiative->title }}">
    <span class="sr-only">Ver detalle de {{ $initiative->title }}</span>
  </button>
</article>

@push('initiative-modals')
  <x-initiative-modal :initiative="$initiative" :color="$color" :modal-id="$modalId" />
@endpush
