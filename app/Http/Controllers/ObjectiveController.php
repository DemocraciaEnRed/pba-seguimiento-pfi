<?php

namespace App\Http\Controllers;
use App\Objective;
use App\Goal;
use App\Report;
use App\Category;
use App\Initiative;
use App\Services\Indicators\HomeStats;
use App\Services\Indicators\ObjectiveStats;
use App\Services\SeoMetadata;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request, string $objectiveId, SeoMetadata $seoMetadata): View {
        $objective = Objective::with(['strategicObjective.category', 'organizations.logo', 'goals', 'communities', 'cover', 'members', 'files'])->findOrFail($objectiveId);
        abort_unless($objective->isVisibleTo($request->user()), 404);
        $request->attributes->set('search_indexing_public', $objective->isVisibleTo(null));
        return view('objective.view',[
            'objective' => $objective,
            'metadata' => $seoMetadata->objective($objective),
        ]);
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
        $categories = Category::with([
            'strategicObjectives' => function ($query) {
                $query->orderBy('title');
            },
            'strategicObjectives.objectives' => function ($query) {
                $query->where('hidden', false)->orderBy('title');
            },
            'strategicObjectives.objectives.goals' => function ($query) {
                $query->orderBy('title');
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

        $objectives = Objective::query()
            ->with('strategicObjective.category')
            ->where('hidden', false)
            ->withCount(['goals', 'reports' => fn ($reports) => $reports->approved()])
            ->withCount(collect(array_keys(Goal::STATUSES))->mapWithKeys(fn (string $status): array => [
                "goals as goals_{$status}_count" => fn ($goals) => $goals->where('status', $status),
            ])->all())
            ->withMax(['reports as last_report_date' => fn ($reports) => $reports->approved()], 'reports.date')
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

    public function fetchOne(Request $request, $objectiveId)
    {
        $objective = Objective::findorfail($objectiveId);
        dd($objective);
    }

    public function fetchReports(Request $request, $objectiveId){
        abort_unless(Objective::findOrFail($objectiveId)->isVisibleTo($request->user()), 404);
        $pageSize = $request->query('size',10);
        $orderBy = $request->query('order_by');
        $detailed = $request->query('detailed');
        $fetchAll = $request->query('all');
        $onlyMappable = $request->query('mappable');
        $reports = Report::query()->approved()->forListing(explode(',', (string) $request->query('with')));
        if(!is_null($orderBy)){
            $orderByParams = explode(',',$orderBy);
            $reports->orderBy($orderByParams[0],$orderByParams[1]);
        }
        $reports->whereHas('goal',function ($q) use($request, $objectiveId) {
            $q->where('objective_id',$objectiveId);
          });

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
        if($detailed){
            return ReportResource::collection($reports);
        } else {
            return SimpleReportResource::collection($reports);
        }
    }

    public function fetchStats(Request $request, ObjectiveStats $objectiveStats, $objectiveId): JsonResponse
    {
        $objective = Objective::findOrFail($objectiveId);
        abort_unless($objective->isVisibleTo($request->user()), 404);

        return response()->json([
            'message' => 'Ok',
            'data' => $objectiveStats->compute($objective),
        ]);
    }

     public function formToggleSubscription(Request $request, $objectiveId){
        if(!$request->user()) {
            abort(403, 'No autorizado');
        }
        $objective = Objective::findorfail($objectiveId);
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
