<hr class="my-4">
<div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="is-700 mb-0"><i class="far fa-comments fa-fw"></i>&nbsp;Últimos comentarios</h5>
  <a href="{{ $listUrl }}" class="text-smaller">Ver todos<i class="fas fa-arrow-right fa-fw"></i></a>
</div>
@forelse($latestComments as $comment)
  @include('partials.comments.item', ['comment' => $comment, 'excerpt' => true, 'showGoal' => $showGoal ?? true])
@empty
<p class="text-muted text-smaller">Todavía no hay comentarios en los reportes.</p>
@endforelse
