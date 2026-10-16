{{--
  Filterable list of report comments.
  Expects: $comments (paginator), $search, $type, $filterUrl, optional $downloadUrl, optional $deleteRoute + $deleteRouteParams, optional $showGoal.
--}}
@use('App\Services\Comments\CommentModeration')
<form method="GET" action="{{ $filterUrl }}" class="form-row align-items-end mb-3">
  <div class="col-md-6 mb-2">
    <label for="q" class="text-smaller mb-1">Buscar</label>
    <input type="search" id="q" name="q" value="{{ $search }}" class="form-control" placeholder="Texto, autor, email o reporte">
  </div>
  <div class="col-md-3 mb-2">
    <label for="tipo" class="text-smaller mb-1">Tipo</label>
    <select id="tipo" name="tipo" class="form-control">
      <option value="">Todos</option>
      <option value="{{ CommentModeration::TYPE_COMMENTS }}" @selected($type === CommentModeration::TYPE_COMMENTS)>Solo comentarios</option>
      <option value="{{ CommentModeration::TYPE_REPLIES }}" @selected($type === CommentModeration::TYPE_REPLIES)>Solo respuestas</option>
    </select>
  </div>
  <div class="col-md-3 mb-2">
    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-magnifying-glass fa-fw"></i>Filtrar</button>
  </div>
</form>
<div class="d-flex justify-content-between align-items-center mb-2">
  <span class="text-smaller text-muted">{{ $comments->total() }} resultado(s)</span>
  @if($downloadUrl ?? null)
  <a href="{{ $downloadUrl }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-download fa-fw"></i>Descargar CSV</a>
  @endif
</div>
@forelse($comments as $comment)
  @include('partials.comments.item', [
    'comment' => $comment,
    'showGoal' => $showGoal ?? true,
    'deleteUrl' => isset($deleteRoute) ? route($deleteRoute, ($deleteRouteParams ?? []) + ['commentId' => $comment->id]) : null,
  ])
@empty
<div class="card my-3 shadow-sm">
  <div class="card-body text-center">
    <h6 class="mb-0">No se encontraron comentarios</h6>
  </div>
</div>
@endforelse
{{ $comments->links() }}
