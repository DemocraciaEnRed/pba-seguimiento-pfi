@props(['label' => null])

<div {{ $attributes->class(['side-menu__group']) }} @if($label) role="group" aria-label="{{ $label }}" @endif>
  @if($label)
    <p class="side-menu__group-label">{{ $label }}</p>
  @endif
  {{ $slot }}
</div>
