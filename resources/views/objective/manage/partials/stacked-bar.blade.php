{{-- @param string $title, int $total, array<int, array{label: string, count: int, color: string}> $segments --}}
<p class="text-smaller mb-1">
  <b>{{ $title }}</b>&nbsp;
  @foreach($segments as $segment)
  <span class="text-nowrap"><i class="fas fa-circle fa-fw text-{{ $segment['color'] }}"></i>{{ $segment['count'] }} {{ $segment['label'] }}</span>&nbsp;
  @endforeach
</p>
<div class="progress mb-3">
  @foreach($segments as $segment)
    @php($percentage = $total > 0 ? (int) round($segment['count'] / $total * 100) : 0)
    @if($percentage > 0)
    <div class="progress-bar bg-{{ $segment['color'] }}" role="progressbar" style="width: {{ $percentage }}%">{{ $percentage }}%</div>
    @endif
  @endforeach
</div>
