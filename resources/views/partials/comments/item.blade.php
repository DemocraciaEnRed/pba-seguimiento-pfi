@php
  $report = $comment->relatedReport();
@endphp
<div class="card my-2 shadow-sm">
  <div class="card-body py-3 d-flex align-items-start">
    <div class="w-100">
      <p class="mb-1 text-smaller text-muted">
        @if($comment->isReply())
        <span class="badge badge-secondary">Respuesta</span>
        @else
        <span class="badge badge-primary">Comentario</span>
        @endif
        <b class="text-dark">{{ $comment->user?->fullname ?? 'Usuario eliminado' }}</b>
        &middot; @datetime($comment->created_at)
        @if($comment->edited)
        &middot; <i>editado</i>
        @endif
      </p>
      <p class="mb-1 nl2br">{{ $excerpt ?? false ? Str::limit($comment->content, 180) : $comment->content }}</p>
      @if($report)
      <p class="mb-0 text-smaller text-muted">
        <i class="far fa-file-lines fa-fw"></i>En <a href="{{ route('reports.index', ['reportId' => $report->id]) }}" target="_blank">{{ $report->title }}</a>
        @if($report->goal && ($showGoal ?? true))
        &middot; Meta: {{ $report->goal->title }}
        @endif
      </p>
      @endif
    </div>
    @if($deleteUrl ?? null)
    <div class="ml-2">
      <form action="{{ $deleteUrl }}" method="POST" onsubmit="return confirm('{{ $comment->isReply() ? '¿Eliminar esta respuesta?' : '¿Eliminar este comentario y todas sus respuestas?' }}')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-link btn-sm text-danger text-nowrap"><i class="fas fa-trash fa-fw"></i>Eliminar</button>
      </form>
    </div>
    @endif
  </div>
</div>
