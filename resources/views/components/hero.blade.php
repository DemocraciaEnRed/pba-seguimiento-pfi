@props(['title' => null, 'subtitle' => null, 'image' => null, 'align' => 'left', 'size' => 'md', 'accent' => null])

@php
  $accent = is_string($accent) && preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : null;
@endphp

<section {{ $attributes->class(['hero', "hero--{$size}", 'hero--has-image' => $image, 'hero--accent' => $accent, 'text-center' => $align === 'center'])->style(["background-image: url('{$image}')" => $image, "--hero-accent: {$accent}" => $accent]) }}>
  <div class="hero__overlay">
    <div class="container position-relative">
      @if ($title)
        <h1 class="hero__title">{{ $title }}</h1>
      @endif
      @if ($subtitle)
        <p class="hero__subtitle lead">{{ $subtitle }}</p>
      @endif
      {{ $slot }}
      @isset($actions)
        <div @class(['hero__actions', 'justify-content-center' => $align === 'center'])>
          {{ $actions }}
        </div>
      @endisset
    </div>
  </div>
</section>
