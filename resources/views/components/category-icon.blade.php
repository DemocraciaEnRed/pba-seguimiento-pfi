@props(['icon', 'size' => null])

@if ($iconUrl = \App\Category::iconUrl($icon))
<span {{ $attributes->class(['category-icon', "category-icon--{$size}" => $size])->style(["--category-icon-url: url('{$iconUrl}')"]) }} aria-hidden="true"></span>
@endif
