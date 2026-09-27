<div class="d-flex flex-wrap">
  @foreach (\App\Category::AVAILABLE_ICONS as $icon => $iconLabel)
    <label class="mr-3 mb-2 text-center">
      <input type="radio" name="icon" value="{{ $icon }}" class="d-none" @checked($selectedIcon === $icon)>
      <span class="category-icon-option d-block border rounded p-2 text-primary">
        <x-category-icon :icon="$icon" size="2x" /><br>
        <small class="text-dark">{{ $iconLabel }}</small>
      </span>
    </label>
  @endforeach
</div>
