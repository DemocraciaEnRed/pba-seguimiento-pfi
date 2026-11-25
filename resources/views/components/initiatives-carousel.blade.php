@props(['initiatives', 'color' => '#009aae', 'label' => 'Iniciativas'])

@php
  $carouselId = 'initiatives-'.\Illuminate\Support\Str::random(6);
@endphp

@if($initiatives->isNotEmpty())
<x-card-carousel :id="$carouselId" :color="$color" :label="$label" {{ $attributes }}>
  <x-slot:header>{{ $header ?? '' }}</x-slot:header>
  @foreach($initiatives as $initiative)
  <li class="initiatives-carousel__item">
    <x-initiative-card :initiative="$initiative" :color="$color" />
  </li>
  @endforeach
</x-card-carousel>
@endif
