@if (count($changes) > 0)
<ul class="text-smaller text-muted mb-1">
  @foreach ($changes as $column => [$before, $after])
    <li><b>{{ $column }}:</b> <del>{{ Str::limit($before === '' ? '(vacío)' : $before, 120) }}</del> → {{ Str::limit($after === '' ? '(vacío)' : $after, 120) }}</li>
  @endforeach
</ul>
@endif
