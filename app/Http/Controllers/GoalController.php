<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Goal;
use App\Objective;
use App\Report;
use App\Services\SeoMetadata;
use App\Services\Goals\GoalAudience;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\Report as ReportResource;
use App\Http\Resources\SimpleReport as SimpleReportResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\User;

class GoalController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Forces to be authenticated.
        // $this->middleware('auth');
    }

    public function index(Request $request, string $goalId, SeoMetadata $seoMetadata): View|Response
    {
        $audience = app(GoalAudience::class);
        $goalAudience = $audience->publicQuery();
        $goal = Goal::query()->visibleIn($goalAudience)->find($goalId);
        $isPublic = $goal !== null;
        $reportsFetchUrl = route('apiService.reports');

        if ($goal === null) {
            $user = $request->user();
            abort_unless($user, 404);
            $goalAudience = $audience->managementQuery($user);
            $goal = Goal::query()->visibleIn($goalAudience)->findOrFail($goalId);
            $reportsFetchUrl = route('apiService.goals.management-reports', ['goalId' => $goalId]);
        }

        $goal->load(['responsibleOrganization', 'participantOrganizations']);
        $objective = Objective::with([
            'strategicObjective.category',
            'goals' => fn ($goals) => $goals->visibleIn($goalAudience)->orderBy('title'),
            'communities',
            'cover',
        ])->findOrFail($goal->objective_id);
        $request->attributes->set('search_indexing_public', $goal->isVisibleTo(null));
        $viewData = [
            'goal' => $goal,
            'objective' => $objective,
            'reportsFetchUrl' => $reportsFetchUrl,
            'metadata' => $seoMetadata->goal($goal, $objective),
        ];

        return $isPublic
            ? view('objective.goal.view', $viewData)
            : response()->view('objective.goal.view', $viewData)->header('Cache-Control', 'private, no-store');
    }

    public function fetchReports(Request $request, string $goalId): AnonymousResourceCollection
    {
        $publicGoals = app(GoalAudience::class)->publicQuery();
        Goal::query()->visibleIn($publicGoals)->findOrFail($goalId);

        return $this->reportsForGoal($request, $goalId, $publicGoals);
    }

    public function fetchManagementReports(Request $request, string $goalId): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 404);

        $goalAudience = app(GoalAudience::class)->managementQuery($user);
        Goal::query()->visibleIn($goalAudience)->findOrFail($goalId);

        return $this->reportsForGoal($request, $goalId, $goalAudience, $user)
            ->response()
            ->header('Cache-Control', 'private, no-store');
    }

    private function reportsForGoal(Request $request, string $goalId, Builder $goalAudience, ?User $user = null): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'size' => ['nullable', 'integer', 'min:1', 'max:50'],
            's' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(Report::TYPES))],
            'date_range' => ['nullable', Rule::in(['last_30_days', 'last_3_months', 'last_year'])],
            'sort' => ['nullable', Rule::in(['recent', 'oldest', 'most_commented', 'most_liked'])],
            'order_by' => ['nullable', 'string', 'regex:/^(date|created_at|updated_at|title),(asc|desc)$/i'],
            'detailed' => ['nullable', 'boolean'],
            'all' => ['nullable', 'boolean'],
            'mappable' => ['nullable', 'boolean'],
        ]);
        $reports = $user === null
            ? Report::query()->approved()
            : Report::query()->manageableBy($user);
        $reports->visibleIn($goalAudience)->forListing(explode(',', (string) $request->query('with')));
        if (isset($validated['sort'])) {
            match ($validated['sort']) {
                'recent' => $reports->orderBy('date', 'desc'),
                'oldest' => $reports->orderBy('date', 'asc'),
                'most_commented' => $reports->orderBy('comments_count', 'desc'),
                'most_liked' => $reports->orderBy('positive_testimonies_count', 'desc'),
            };
            $reports->orderBy('id', $validated['sort'] === 'oldest' ? 'asc' : 'desc');
        } elseif (isset($validated['order_by'])) {
            [$column, $direction] = explode(',', $validated['order_by']);
            $reports->orderBy($column, strtolower($direction));
        }
        $reports->where('goal_id', $goalId)
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['date_range'] ?? null, fn ($query, $range) => $query->where('date', '>=', match ($range) {
                'last_30_days' => now()->subDays(30)->startOfDay(),
                'last_3_months' => now()->subMonths(3)->startOfDay(),
                'last_year' => now()->subYear()->startOfDay(),
            }))
            ->when($validated['s'] ?? null, fn ($query, $title) => $query->where('trace', 'like', '%'.$title.'%'));
        if ($request->boolean('mappable')) {
            $reports->whereNotNull('map_long')->whereNotNull('map_lat')->whereNotNull('map_center');
        }
        if ($request->boolean('all')) {
            $reports = $reports->get();
        } else {
            $reports = $reports->paginate($validated['size'] ?? 10)->withQueryString();
        }

        return $request->boolean('detailed')
            ? ReportResource::collection($reports)
            : SimpleReportResource::collection($reports);
    }
}
