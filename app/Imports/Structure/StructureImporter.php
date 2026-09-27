<?php

namespace App\Imports\Structure;

use App\Goal;
use App\Objective;
use App\Services\Indicators\GoalIndicatorConfigurator;
use App\StrategicObjective;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a validated plan in a single transaction. Subscribers are not notified.
 */
class StructureImporter
{
    public function __construct(private GoalIndicatorConfigurator $indicatorConfigurator) {}

    public function import(StructureImportPlan $plan, User $user): void
    {
        DB::transaction(function () use ($plan, $user): void {
            foreach ($plan->strategicObjectives as $strategicObjectiveItem) {
                /** @var StrategicObjective $strategicObjective */
                $strategicObjective = $strategicObjectiveItem->model;
                $this->save($strategicObjectiveItem, $user, 'el objetivo estratégico');

                foreach ($strategicObjectiveItem->children as $objectiveItem) {
                    /** @var Objective $objective */
                    $objective = $objectiveItem->model;

                    if (! $objective->exists) {
                        $objective->strategicObjective()->associate($strategicObjective);
                        $objective->author()->associate($user);
                    }

                    $this->save($objectiveItem, $user, 'el objetivo');

                    foreach ($objectiveItem->children as $goalItem) {
                        $this->saveGoal($goalItem, $objective, $user);
                    }
                }
            }
        });
    }

    private function save(PlannedItem $item, User $user, string $entity): void
    {
        if ($item->action === ImportAction::Unchanged) {
            return;
        }

        $item->model->save();
        $verb = $item->action === ImportAction::Create ? 'creado' : 'actualizado';

        Log::channel('mysql')->info("[{$user->fullname}] ha {$verb} {$entity} [{$item->model->title}] mediante importación", [
            'model_id' => $item->model->getKey(),
            'model_title' => $item->model->title,
            'user_id' => $user->id,
            'user_fullname' => $user->fullname,
            'user_email' => $user->email,
        ]);
    }

    private function saveGoal(PlannedItem $item, Objective $objective, User $user): void
    {
        if ($item->action === ImportAction::Unchanged) {
            return;
        }

        /** @var Goal $goal */
        $goal = $item->model;
        $goal->title = $item->input['title'];
        $goal->status = $item->input['status'];
        $goal->source = $item->input['source'];
        $goal->objective()->associate($objective);
        $this->indicatorConfigurator->apply($goal, $item->input);

        $verb = $item->action === ImportAction::Create ? 'creado' : 'actualizado';

        Log::channel('mysql')->info("[{$user->fullname}] ha {$verb} la meta [{$goal->title}] del objetivo [{$objective->title}] mediante importación", [
            'objective_id' => $objective->id,
            'objective_title' => $objective->title,
            'goal_id' => $goal->id,
            'goal_title' => $goal->title,
            'user_id' => $user->id,
            'user_fullname' => $user->fullname,
            'user_email' => $user->email,
        ]);
    }
}
