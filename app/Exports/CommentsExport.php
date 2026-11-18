<?php

namespace App\Exports;

use App\Services\Comments\CommentModeration;
use App\User;
use Illuminate\Support\LazyCollection;

class CommentsExport implements CsvExport
{
    public function __construct(
        private ?string $search = null,
        private ?string $type = null,
        private ?int $objectiveId = null,
        private ?int $goalId = null,
        private ?User $user = null,
    ) {}

    public function collection(): LazyCollection
    {
        return app(CommentModeration::class)->search($this->search, $this->type, $this->objectiveId, $this->goalId, $this->user)->lazy();
    }

    public function headings(): array
    {
        return [
            'Tipo',
            'ID',
            'Respuesta a (ID)',
            'Autor',
            'Autor Email',
            'Comentario',
            'Reporte (ID)',
            'Reporte',
            'Meta',
            'Objetivo',
            'Fecha creado',
            'Fecha actualizado',
        ];
    }

    public function map(mixed $comment): array
    {
        $report = $comment->relatedReport();

        return [
            $comment->isReply() ? 'Respuesta' : 'Comentario',
            $comment->id,
            $comment->parent_id ?? '-',
            $comment->user?->fullname ?? '-',
            $comment->user?->email ?? '-',
            $comment->content,
            $report?->id ?? '-',
            $report?->title ?? '-',
            $report?->goal?->title ?? '-',
            $report?->goal?->objective?->title ?? '-',
            $comment->created_at->format('d/m/Y H:i:s'),
            $comment->updated_at->format('d/m/Y H:i:s'),
        ];
    }
}
