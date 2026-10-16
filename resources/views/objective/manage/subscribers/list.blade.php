@extends('objective.manage.master')

@section('panelContent')

<section>
  <h3 class="is-700">Suscriptores</h3>
  <p class="lead">Aquí encontrará a los usuarios suscriptos a las notificaciones del objetivo</p>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="text-smaller text-muted">{{ $subscribers->total() }} resultado(s)</span>
    @isManager($objective->id)
    @if($subscribers->total() > 0)
    <a href="{{ route('objectives.manage.subscribers.download', ['objectiveId' => $objective->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i>Descargar CSV</a>
    @endif
    @endisManager
  </div>

  <div class="list-group shadow-sm mb-3">
    @forelse($subscribers as $subscriber)
    <div class="list-group-item d-flex align-items-center gap-3">
      @include('utils.avatar', ['avatar' => $subscriber->avatar, 'size' => 36, 'thumbnail' => true])
      <div class="flex-grow-1 min-w-0">
        <span class="is-700">{{ $subscriber->surname }}, {{ $subscriber->name }}</span>
        @isManager($objective->id)
        <span class="d-block text-smaller text-muted text-truncate">{{ $subscriber->email }}</span>
        @endisManager
      </div>
      <span class="text-right text-smaller text-nowrap text-muted" title="Fecha de suscripción"><i class="far fa-calendar fa-fw"></i>@justdate($subscriber->pivot->created_at)</span>
    </div>
    @empty
    <div class="list-group-item text-center text-muted py-4">
      <i class="far fa-face-surprise"></i>&nbsp;¡No hay suscriptores del objetivo!
    </div>
    @endforelse
  </div>
  {{ $subscribers->links() }}
</section>

@endsection
