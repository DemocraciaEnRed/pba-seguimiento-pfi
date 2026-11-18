<?php

namespace App\Http\Controllers;
use App\Objective;
use App\Goal;
use App\Report;
use App\Category;
use App\Initiative;
use App\Organization;
use App\Services\Indicators\HomeStats;
use App\Services\Indicators\ObjectiveStats;
use App\Services\Goals\GoalAudience;
use App\Services\SeoMetadata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use App\Http\Resources\Objective as ObjectiveResource;
use App\Http\Resources\Report as ReportResource;
use App\Http\Resources\SimpleReport as SimpleReportResource;

class ObjectiveController extends Controller
{
    /** @var array<string, array{0: string, 1: string}> */
    private const LIST_SORTS = [
        'title' => ['title', 'asc'],
        'last_report' => ['last_report_date', 'desc'],
        'most_reports' => ['reports_count', 'desc'],
        'most_reached' => ['goals_reached_count', 'desc'],
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Forces to be authenticated.
        // $this->middleware('auth');
        // $this->middleware('check_role:admin');
        // $this->middleware('fetch_objective');
    }

    public function index(Request $request, string $objectiveId, SeoMetadata $seoMetadata): View|Response
    {
        $objective = Objective::findOrFail($objectiveId);
        $isPublic = $objective->isVisibleTo(null);
        $user = $request->user();
        if ($isPublic) {
            $goalAudience = app(GoalAudience::class)->publicQuery();
        } else {
            abort_if($user === null || ! $objective->isVisibleTo($user), 404);
            $goalAudience = app(GoalAudience::class)->managementQuery($user);
        }
        $objective->load(['strategicObjective.category', 'goals' => fn ($goals) => $goals
            ->visibleIn($goalAudience)
            ->orderBy('title'), 'communities', 'cover', 'files']);
        $organizations = Organization::query()
            ->associatedWithGoals((clone $goalAudience)->where('goals.objective_id', $objective->id))
            ->with('logo')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);
        $request->attributes->set('search_indexing_public', $isPublic);
        $viewData = [
            'objective' => $objective,
            'organizations' => $organizations,
            'metadata' => $seoMetadata->objective($objective),
        ];

        return $isPublic
            ? view('objective.view', $viewData)
            : response()->view('objective.view', $viewData)->header('Cache-Control', 'private, no-store');
    }

    public function viewList(Request $request){
        $categories = Category::query()
            ->with(['strategicObjectives' => fn ($query) => $query->select(['id', 'category_id', 'codigo', 'title'])->orderBy('codigo')])
            ->orderBy('order')
            ->get();
        return view('portal.catalogs.objectives',[
            'categories' => $categories
        ]);
    }

    public function viewCatalog(HomeStats $homeStats): View
    {
        $publicGoals = app(GoalAudience::class)->publicQuery();
        $categories = Category::with([
            'strategicObjectives' => function ($query) {
                $query->orderBy('title');
            },
            'strategicObjectives.objectives' => function ($query) {
                $query->where('hidden', false)->orderBy('title');
            },
            'strategicObjectives.objectives.goals' => function ($query) use ($publicGoals) {
                $query->visibleIn($publicGoals)->orderBy('title');
            },
        ])->orderBy('order')->get();

        $statsByCategory = collect($homeStats->cached()['categories'])
            ->keyBy('id');

        $initiativesByCategory = Initiative::query()
            ->with('categories:id')
            ->ordered()
            ->get()
            ->flatMap(fn (Initiative $initiative) => $initiative->categories->map(fn (Category $category): array => [$category->id, $initiative]))
            ->groupBy(0)
            ->map(fn ($pairs) => $pairs->pluck(1)->values());

        return view('portal.catalogs.catalog', [
            'categories' => $categories,
            'statsByCategory' => $statsByCategory,
            'initiativesByCategory' => $initiativesByCategory,
            'documents' => config('plan.documents', []),
        ]);
    }

    public function fetch(Request $request)
    {
        $validated = $request->validate([
            'size' => 'nullable|integer|min:1|max:50',
            'category' => 'nullable|integer',
            'strategic_objective' => 'nullable|integer',
            'sort' => ['nullable', 'string', Rule::in(array_keys(self::LIST_SORTS))],
            'order_by' => ['nullable', 'string', 'regex:/^(title|created_at|updated_at),(asc|desc)$/i'],
        ]);
        $title = $request->query('s');
        $publicGoals = app(GoalAudience::class)->publicQuery();

        $objectives = Objective::query()
            ->with('strategicObjective.category')
            ->where('hidden', false)
            ->withCount([
                'goals' => fn ($goals) => $goals->visibleIn($publicGoals),
                'reports' => fn ($reports) => $reports->approved()->visibleIn($publicGoals),
            ])
            ->withCount(collect(array_keys(Goal::STATUSES))->mapWithKeys(fn (string $status): array => [
                "goals as goals_{$status}_count" => fn ($goals) => $goals->visibleIn($publicGoals)->where('status', $status),
            ])->all())
            ->withMax(['reports as last_report_date' => fn ($reports) => $reports->approved()->visibleIn($publicGoals)], 'reports.date')
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->whereHas('strategicObjective', fn ($strategicObjectives) => $strategicObjectives->where('category_id', $category)))
            ->when($validated['strategic_objective'] ?? null, fn ($query, $strategicObjective) => $query->where('strategic_objective_id', $strategicObjective));

        if (! is_null($title)) {
            $keywords = explode(' ', $title);
            $objectives->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('trace', 'like', "%{$keyword}%");
                }
            });
        }

        if (isset($validated['sort'])) {
            [$column, $direction] = self::LIST_SORTS[$validated['sort']];
            $objectives->orderBy($column, $direction)->orderBy('title');
        } elseif (isset($validated['order_by'])) {
            [$column, $direction] = explode(',', $validated['order_by']);
            $objectives->orderBy($column, strtolower($direction));
        }

        $objectives = $objectives->paginate($validated['size'] ?? 10)->withQueryString();

        return ObjectiveResource::collection($objectives);
    }

    public function fetchOne(Request $request, string $objectiveId): ObjectiveResource
    {
        $objective = Objective::query()
            ->where('hidden', false)
            ->with('strategicObjective.category')
            ->findOrFail($objectiveId);

        return new ObjectiveResource($objective);
    }

    public function fetchReports(Request $request, string $objectiveId): AnonymousResourceCollection|JsonResponse
    {
        $objective = Objective::findOrFail($objectiveId);
        $user = null;
        if (! $objective->isVisibleTo(null)) {
            $user = $request->user();
            abort_if($user === null || ! $objective->isVisibleTo($user), 404);
        }

        $pageSize = $request->query('size',10);
        $orderBy = $request->query('order_by');
        $detailed = $request->query('detailed');
        $fetchAll = $request->query('all');
        $onlyMappable = $request->query('mappable');
        $goalAudience = $user === null
            ? app(GoalAudience::class)->publicQuery()
            : app(GoalAudience::class)->managementQuery($user);
        $reports = $user === null
            ? Report::query()->approved()
            : Report::query()->manageableBy($user);
        $reports->visibleIn($goalAudience)->forListing(explode(',', (string) $request->query('with')));
        if(!is_null($orderBy)){
            $orderByParams = explode(',',$orderBy);
            $reports->orderBy($orderByParams[0],$orderByParams[1]);
        }
        $reports->whereHas('goal', fn ($query) => $query->where('objective_id', $objectiveId));

        if($onlyMappable){
            $reports->whereNotNull('map_long')->whereNotNull('map_lat')->whereNotNull('map_center');
        }
        // If "all=1" is not present
        if($fetchAll){
            // Paginate
            $reports = $reports->get();
        } else {
            // Otherwise
            // Get all
            $reports = $reports->paginate($pageSize)->withQueryString();
        }
        $resource = $detailed
            ? ReportResource::collection($reports)
            : SimpleReportResource::collection($reports);

        return $user === null
            ? $resource
            : $resource->response()->header('Cache-Control', 'private, no-store');
    }

    public function fetchStats(Request $request, ObjectiveStats $objectiveStats, string $objectiveId): JsonResponse
    {
        $objective = Objective::findOrFail($objectiveId);
        $user = null;
        if (! $objective->isVisibleTo(null)) {
            $user = $request->user();
            abort_if($user === null || ! $objective->isVisibleTo($user), 404);
        }

        $response = response()->json([
            'message' => 'Ok',
            'data' => $objectiveStats->compute($objective, $user),
        ]);

        return $user === null ? $response : $response->header('Cache-Control', 'private, no-store');
    }

     public function formToggleSubscription(Request $request, $objectiveId){
        if(!$request->user()) {
            abort(403, 'No autorizado');
        }
        $objective = Objective::query()->where('hidden', false)->findOrFail($objectiveId);
        $isSubscriber = $objective->isSubscriber($request->user()->id);
        if($isSubscriber){
            $objective->subscribers()->detach($request->user()->id);
            $msg = 'Te has desubscripto del objetivo';
        } else {
            $objective->subscribers()->attach($request->user()->id);
            $msg = '¡Te has subscripto al objetivo!';
        }
        return redirect()->back()->with('success',$msg);

    }
}
