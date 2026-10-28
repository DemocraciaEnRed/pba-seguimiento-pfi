@props(['initiative', 'color' => '#009aae'])

@php
  $color = is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#009aae';
  $backgroundUrl = $initiative->backgroundUrl();
  $logoUrl = $initiative->logoUrl();
  $tag = $initiative->url ? 'a' : 'div';
@endphp

<{{ $tag }}
  @if($initiative->url)
  href="{{ $initiative->url }}" target="_blank" rel="noopener noreferrer"
  aria-label="{{ $initiative->title }}: {{ $initiative->subtitle }} (se abre en una pestaña nueva)"
  @endif
  {{ $attributes->class(['initiative-card', 'initiative-card--link' => $initiative->url, 'initiative-card--has-image' => $backgroundUrl])->style(["--initiative-color: {$color}"]) }}
>
  @if($backgroundUrl)
  <div class="initiative-card__image" style="background-image: url('{{ $backgroundUrl }}')" aria-hidden="true"></div>
  @endif
  <div class="initiative-card__overlay" aria-hidden="true"></div>
  @if($initiative->url)
  <i class="fas fa-arrow-up-right-from-square initiative-card__corner" aria-hidden="true"></i>
  @endif
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
    @if($initiative->url)
    <span class="initiative-card__cta">Conocé más <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
    @endif
  </div>
</{{ $tag }}>
