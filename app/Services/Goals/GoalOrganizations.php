<?php

namespace App\Services\Goals;

use App\Goal;
use App\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Responsible and participant organizations of a goal, confirmed by Administration.
 */
class GoalOrganizations
{
    /**
     * Active organizations plus the inactive ones the goal already has and may keep.
     *
     * @return Collection<int, Organization>
     */
    public function options(?Goal $goal = null): Collection
    {
        $currentIds = $goal ? $this->currentIds($goal) : [];

        return Organization::query()
            ->where(fn (Builder $organizations) => $organizations->active()->orWhereIn('id', $currentIds))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    /**
     * @param  Collection<int, Organization>  $options
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function rules(Collection $options, array $input): array
    {
        $selectable = Rule::in($options->modelKeys());
        $responsible = $input['responsible_organization'] ?? null;

        return [
            'responsible_organization' => ['nullable', 'integer', $selectable],
            'participant_organizations' => ['nullable', 'array'],
            'participant_organizations.*' => ['integer', 'distinct', $selectable, Rule::notIn(is_scalar($responsible) ? [$responsible] : [])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'responsible_organization.in' => 'Elegí una organización activa del catálogo.',
            'participant_organizations.*.in' => 'Elegí organizaciones activas del catálogo.',
            'participant_organizations.*.not_in' => 'La organización responsable no puede figurar también como participante.',
            'participant_organizations.*.distinct' => 'Cada organización participante puede elegirse una sola vez.',
        ];
    }

    /**
     * @param  array{responsible_organization?: int|string|null, participant_organizations?: list<int|string>|null}  $validated
     */
    public function sync(Goal $goal, array $validated): void
    {
        DB::transaction(function () use ($goal, $validated): void {
            $goal->responsible_organization_id = $validated['responsible_organization'] ?? null;
            $goal->save();
            $goal->participantOrganizations()->sync($validated['participant_organizations'] ?? []);
        });
    }

    /**
     * @return list<int>
     */
    private function currentIds(Goal $goal): array
    {
        return array_values(array_filter([
            $goal->responsible_organization_id,
            ...$goal->participantOrganizations()->pluck('organizations.id')->all(),
        ]));
    }
}
