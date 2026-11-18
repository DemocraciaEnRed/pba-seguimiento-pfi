{{-- Expects $responsibleName (?string) and $participantNames (list<string>). --}}
@if($responsibleName || ! empty($participantNames))
<div class="hero-organizations mt-2">
  @if($responsibleName)
  <p class="mb-1"><i class="far fa-building fa-fw"></i> Organización responsable: <b>{{ $responsibleName }}</b></p>
  @endif
  @if(! empty($participantNames))
  <p class="text-smaller mb-0">Organizaciones participantes: {{ implode(', ', $participantNames) }}</p>
  @endif
</div>
@endif
