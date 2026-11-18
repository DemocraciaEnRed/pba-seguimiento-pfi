<?php

namespace App\Http\Controllers;

use App\Goal;
use App\Objective;
use App\Services\Goals\GoalAssignments;
use App\Services\Pfi\PfiMemberships;
use App\Services\Reports\ReportContinuity;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GoalAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('verified');
    }

    public function index(Request $request): View
    {
        Gate::authorize('isAdmin');
        $validated = $request->validate(['q' => ['nullable', 'string', 'min:3', 'max:255']]);
        $search = trim($validated['q'] ?? '');

        return view('admin.goal-assignments.index', [
            'objectives' => Objective::query()
                ->with('strategicObjective.category')
                ->withCount('goals')
                ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
                ->orderBy('title')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function showObjective(Request $request, string $objectiveId, PfiMemberships $pfiMemberships): View
    {
        Gate::authorize('isAdmin');
        $validated = $request->validate(['q' => ['nullable', 'string', 'min:3', 'max:255']]);
        $search = trim($validated['q'] ?? '');
        $objective = Objective::with([
            'goals.responsibles',
            'goals.reporters',
            'members',
        ])->findOrFail($objectiveId);

        return view('admin.goal-assignments.objective', [
            'objective' => $objective,
            'legacyMembers' => $objective->members,
            'candidates' => $search === '' ? null : $pfiMemberships->candidates($search)->paginate(10)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function showAdminGoal(Request $request, string $goalId, PfiMemberships $pfiMemberships): View
    {
        $goal = Goal::with('objective')->findOrFail($goalId);
        $this->authorize('manageAssignments', $goal);

        return $this->goalView($request, $goal, $pfiMemberships, true);
    }

    public function showResponsibleGoals(Request $request): View
    {
        return view('panel.goal-assignments.index', [
            'goals' => $request->user()->responsibleGoals()
                ->with('objective.strategicObjective.category')
                ->orderBy('goals.title')
                ->paginate(20),
        ]);
    }

    public function showResponsibleGoal(Request $request, string $goalId, PfiMemberships $pfiMemberships): View
    {
        $goal = Goal::with('objective')->findOrFail($goalId);
        $this->authorize('manageReporters', $goal);

        return $this->goalView($request, $goal, $pfiMemberships, false);
    }

    public function storeResponsible(Request $request, string $goalId, string $userId, GoalAssignments $goalAssignments): RedirectResponse
    {
        $goal = Goal::findOrFail($goalId);
        $this->authorize('manageAssignments', $goal);
        $member = $this->findPfiMember($request, $userId);
        $assigned = $goalAssignments->assignResponsible($goal, $member, $request->user());

        return $this->redirectToGoal($request, $goal, false)
            ->with($assigned ? 'success' : 'warning', $assigned ? 'Se asignó a la persona como Responsable de la meta' : 'La persona ya era Responsable de esta meta');
    }

    public function destroyResponsible(Request $request, string $goalId, string $userId, GoalAssignments $goalAssignments): RedirectResponse
    {
        $goal = Goal::findOrFail($goalId);
        $this->authorize('manageAssignments', $goal);
        $member = User::findOrFail($userId);
        $removed = $goalAssignments->removeResponsible($goal, $member, $request->user(), $this->destinations($request));

        $message = match (true) {
            ! $removed => 'La persona no tenía ese rol en la meta',
            $goal->responsibles()->exists() => 'Se quitó a la persona como Responsable de la meta',
            default => 'Se quitó a la persona como Responsable. La meta quedó sin Responsables; considerá asignar a alguien para mantener la cobertura.',
        };

        return $this->redirectToGoal($request, $goal, false)
            ->with($removed ? 'success' : 'warning', $message);
    }

    public function storeReporter(Request $request, string $goalId, string $userId, GoalAssignments $goalAssignments): RedirectResponse
    {
        $goal = Goal::findOrFail($goalId);
        $this->authorize('manageReporters', $goal);
        $member = $this->findPfiMember($request, $userId);
        $assigned = $goalAssignments->assignReporter($goal, $member, $request->user());

        return $this->redirectToGoal($request, $goal, $request->routeIs('panel.goal-assignments.*'))
            ->with($assigned ? 'success' : 'warning', $assigned ? 'Se asignó a la persona como Reportera de la meta' : 'La persona ya era Reportera de esta meta');
    }

    public function destroyReporter(Request $request, string $goalId, string $userId, GoalAssignments $goalAssignments): RedirectResponse
    {
        $goal = Goal::findOrFail($goalId);
        $this->authorize('manageReporters', $goal);
        $member = User::findOrFail($userId);
        $removed = $goalAssignments->removeReporter($goal, $member, $request->user(), $this->destinations($request));

        return $this->redirectToGoal($request, $goal, $request->routeIs('panel.goal-assignments.*'))
            ->with($removed ? 'success' : 'warning', $removed ? 'Se quitó a la persona como Reportera de la meta' : 'La persona no tenía ese rol en la meta');
    }

    public function confirmResponsibleRemoval(Request $request, string $goalId, string $userId): View
    {
        return $this->removalView($request, $goalId, $userId, 'responsible');
    }

    public function confirmReporterRemoval(Request $request, string $goalId, string $userId): View
    {
        return $this->removalView($request, $goalId, $userId, 'reporter');
    }

    private function removalView(Request $request, string $goalId, string $userId, string $role): View
    {
        $goal = Goal::with(['objective', 'responsibles'])->findOrFail($goalId);
        $this->authorize($role === 'responsible' ? 'manageAssignments' : 'manageReporters', $goal);
        $member = User::findOrFail($userId);
        $isAdmin = $request->routeIs('admin.*');
        $prefix = $isAdmin ? 'admin' : 'panel';
        $roleRoute = $role === 'responsible' ? 'responsibles' : 'reporters';

        return view('admin.goal-assignments.continuity', [
            'member' => $member, 'isAdmin' => $isAdmin,
            'roleLabel' => $role === 'responsible' ? 'Responsable' : 'Reportero',
            'reports' => app(ReportContinuity::class)->pendingForRemoval($member, $goal, $role)
                ->with(['goal.reporters.pfiMembership', 'goal.objective'])->orderBy('id')->get(),
            'coverage' => $role === 'responsible' ? collect([$goal]) : collect(),
            'action' => route("{$prefix}.goal-assignments.{$roleRoute}.delete", ['goalId' => $goalId, 'userId' => $userId]),
            'backUrl' => route("{$prefix}.goal-assignments.goal", ['goalId' => $goalId]),
        ]);
    }

    /** @return array<int, int> */
    private function destinations(Request $request): array
    {
        $data = $request->validate(['destinations' => ['sometimes', 'array'], 'destinations.*' => ['required', 'integer', 'min:1']]);

        return array_map(intval(...), $data['destinations'] ?? []);
    }

    public function assignReporterToObjective(Request $request, string $objectiveId, string $userId, GoalAssignments $goalAssignments): RedirectResponse
    {
        Gate::authorize('isAdmin');
        $objective = Objective::findOrFail($objectiveId);
        $member = $this->findPfiMember($request, $userId);

        if (! $objective->goals()->exists()) {
            return redirect()->route('admin.goal-assignments.objective', ['objectiveId' => $objective->getKey()])
                ->with('warning', 'Este objetivo todavía no tiene metas para asignar.');
        }

        $count = $goalAssignments->assignReporterToObjective($objective, $member, $request->user());
        $message = $count > 0
            ? "Se asignó como Reportero en {$count} metas existentes. Las metas futuras no se modifican."
            : 'La persona ya estaba asignada como Reportera en las metas existentes.';

        return redirect()->route('admin.goal-assignments.objective', [
            'objectiveId' => $objective->getKey(),
            'q' => $request->input('q'),
        ])->with($count > 0 ? 'success' : 'warning', $message);
    }

    private function goalView(Request $request, Goal $goal, PfiMemberships $pfiMemberships, bool $isAdmin): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'min:3', 'max:255']]);
        $search = trim($validated['q'] ?? '');
        $goal->load(['responsibles', 'reporters']);

        return view('admin.goal-assignments.goal', [
            'goal' => $goal,
            'isAdmin' => $isAdmin,
            'candidates' => $search === '' ? null : $pfiMemberships->candidates($search)->paginate(10)->withQueryString(),
            'search' => $search,
            'backUrl' => $isAdmin
                ? route('admin.goal-assignments.objective', ['objectiveId' => $goal->objective_id])
                : route('panel.goal-assignments.index'),
        ]);
    }

    private function findPfiMember(Request $request, string $userId): User
    {
        $request->merge(['user_id' => $userId]);
        $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('pfi_memberships', 'user_id')],
        ]);

        return User::findOrFail($userId);
    }

    private function redirectToGoal(Request $request, Goal $goal, bool $isPanel): RedirectResponse
    {
        $route = $isPanel ? 'panel.goal-assignments.goal' : 'admin.goal-assignments.goal';
        $parameters = ['goalId' => $goal->getKey()];
        $search = $request->input('q');

        if (is_string($search) && $search !== '') {
            $parameters['q'] = $search;
        }

        return redirect()->route($route, $parameters);
    }
}
