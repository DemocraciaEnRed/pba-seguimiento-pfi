@props(['label', 'icon' => null])

<div {{ $attributes->class(['card shadow-sm h-100']) }}>
  <div class="card-body">
    <h3 class="text-smaller text-muted is-700 mb-2">@if($icon)<i class="{{ $icon }} fa-fw"></i> @endif{{ $label }}</h3>
    <div class="mb-0">{{ $slot }}</div>
  </div>
</div>
