<?php

namespace App\Http\Resources;

use App\Http\Resources\Category as CategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eje, objetivo estratégico and objetivo específico a goal belongs to.
 *
 * @mixin \App\Goal
 */
class GoalHierarchy extends JsonResource
{
    /**
     * @return array{category: CategoryResource, strategic_objective: array{id: int, codigo: string, title: string}, objective: array{id: int, title: string, url: string}}
     */
    public function toArray(Request $request): array
    {
        $objective = $this->objective;
        $strategicObjective = $objective->strategicObjective;

        return [
            'category' => CategoryResource::make($strategicObjective->category),
            'strategic_objective' => [
                'id' => $strategicObjective->id,
                'codigo' => $strategicObjective->codigo,
                'title' => $strategicObjective->title,
            ],
            'objective' => [
                'id' => $objective->id,
                'title' => $objective->title,
                'url' => route('objectives.index', ['objectiveId' => $objective->id]),
            ],
        ];
    }
}
