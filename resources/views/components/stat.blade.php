@props(['label', 'value', 'icon', 'href' => null, 'hint' => null, 'tone' => null])

<div {{ $attributes->class(['card border-light h-100 admin-stat']) }}>
  <div class="card-body p-3">
    <p class="text-smaller text-muted mb-1"><i class="{{ $icon }} fa-fw {{ $tone ? 'text-'.$tone : '' }}"></i>&nbsp;{{ $label }}</p>
    <span class="h4 is-700 mb-0 d-block">{{ is_numeric($value) ? number_format($value, 0, ',', '.') : $value }}</span>
    @if($hint)
    <span class="text-smaller text-muted d-block">{{ $hint }}</span>
    @endif
    @if($href)
    <a href="{{ $href }}" class="stretched-link" aria-label="Ver {{ $label }}"></a>
    @endif
  </div>
</div>
