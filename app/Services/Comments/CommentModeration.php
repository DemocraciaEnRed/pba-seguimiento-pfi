<?php

namespace App\Services\Comments;

use App\Comment;
use App\Report;
use App\Services\Goals\GoalAudience;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommentModeration
{
    public const string TYPE_COMMENTS = 'comentarios';

    public const string TYPE_REPLIES = 'respuestas';

    /**
     * Comments and replies on reports, newest first, optionally limited to an objective or a goal
     * and filtered by text (content, author or report title) and type.
     */
    public function search(?string $search = null, ?string $type = null, ?int $objectiveId = null, ?int $goalId = null, ?User $user = null): Builder
    {
        $search = trim((string) $search);
        $isScoped = $objectiveId !== null || $goalId !== null;
        $goalAudience = $user === null
            ? app(GoalAudience::class)->publicQuery()
            : app(GoalAudience::class)->managementQuery($user);
        $reportScope = fn (Builder $query) => $query
            ->visibleIn($goalAudience)
            ->when($user === null, fn (Builder $query) => $query->approved(), fn (Builder $query) => $query->manageableBy($user))
            ->when($goalId !== null, fn (Builder $query) => $query->where('goal_id', $goalId))
            ->when($objectiveId !== null, fn (Builder $query) => $query->whereHas('goal', fn (Builder $query) => $query->where('objective_id', $objectiveId)));

        return Comment::query()
            ->with(['user', 'commentable.goal.objective', 'parent.commentable.goal.objective'])
            ->when($isScoped, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereHasMorph('commentable', [Report::class], $reportScope)
                ->orWhereHas('parent', fn (Builder $query) => $query->whereHasMorph('commentable', [Report::class], $reportScope))))
            ->when($type === self::TYPE_COMMENTS, fn (Builder $query) => $query->whereNull('parent_id'))
            ->when($type === self::TYPE_REPLIES, fn (Builder $query) => $query->whereNotNull('parent_id'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $reportTitle = fn (Builder $query) => $query->where('title', 'like', $like);
                $query->where(fn (Builder $query) => $query
                    ->where('content', 'like', $like)
                    ->orWhereHas('user', fn (Builder $query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('surname', 'like', $like)
                        ->orWhere('email', 'like', $like))
                    ->orWhereHasMorph('commentable', [Report::class], $reportTitle)
                    ->orWhereHas('parent', fn (Builder $query) => $query->whereHasMorph('commentable', [Report::class], $reportTitle)));
            })
            ->latest()
            ->orderByDesc('id');
    }

    /**
     * Soft deletes the comment together with its replies and records it in the action log.
     */
    public function delete(Comment $comment, User $moderator, string $moderatorRole): void
    {
        $report = $comment->relatedReport();
        abort_unless($moderator->hasRole('admin')
            || $comment->user_id === $moderator->id
            || ($report !== null && $moderator->isAssignedToGoal($report->goal_id)), 403);

        DB::transaction(function () use ($comment): void {
            $comment->replies()->get()->each->delete();
            $comment->delete();
        });

        Log::channel('mysql')->info("[{$moderator->fullname}] ({$moderatorRole}) ha eliminado ".($comment->isReply() ? 'la respuesta' : 'el comentario').' de ['.($comment->user?->fullname ?? '-').']'.($report ? " hecho en el reporte [{$report->title}]" : ''), [
            'comment_id' => $comment->id,
            'comment_parent_id' => $comment->parent_id,
            'comment_content' => $comment->content,
            'report_id' => $report?->id,
            'report_title' => $report?->title,
            'goal_id' => $report?->goal?->id,
            'objective_id' => $report?->goal?->objective_id,
            'user_id' => $moderator->id,
        ]);
    }

    public static function deletedMessage(Comment $comment): string
    {
        return $comment->isReply() ? 'La respuesta ha sido eliminada' : 'El comentario y sus respuestas han sido eliminados';
    }
}
