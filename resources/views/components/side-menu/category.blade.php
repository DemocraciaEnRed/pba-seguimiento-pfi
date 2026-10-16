@props(['label', 'icon' => null, 'href' => null, 'active' => false, 'createHref' => null, 'createActive' => false, 'createLabel' => null])

<div {{ $attributes->class(['side-menu__category']) }}>
  <div class="side-menu__heading">
    @if($href)
      <a href="{{ $href }}" @class(['category', 'is-active' => $active]) @if($active) aria-current="page" @endif>@if($icon)<i class="{{ $icon }} fa-fw"></i>&nbsp;@endif{{ $label }}</a>
    @else
      <h6 class="category mb-0">@if($icon)<i class="{{ $icon }} fa-fw"></i>&nbsp;@endif{{ $label }}</h6>
    @endif
    @if($createHref)
      <a href="{{ $createHref }}" @class(['side-menu__add', 'is-active' => $createActive]) title="{{ $createLabel ?? 'Crear' }}" aria-label="{{ $createLabel ?? 'Crear' }}"><i class="fas fa-plus"></i></a>
    @endif
  </div>
  @if($slot->isNotEmpty())
    <div class="menu-link">{{ $slot }}</div>
  @endif
</div>
