<?php

namespace App\Http\Controllers;
use App\Objective;
use App\Goal;
use App\Report;
use App\Category;
use App\Services\Indicators\ObjectiveStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\Objective as ObjectiveResource;
use App\Http\Resources\Report as ReportResource;
use App\Http\Resources\SimpleReport as SimpleReportResource;

class ObjectiveController extends Controller
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
        // $this->middleware('check_role:admin');
        // $this->middleware('fetch_objective');
    }

    public function index(Request $request, $objectiveId){
        $objective = Objective::with(['strategicObjective.category', 'organizations.logo', 'goals', 'communities', 'cover', 'members', 'files'])->findOrFail($objectiveId);
        abort_unless($objective->isVisibleTo($request->user()), 404);
        return view('objective.view',['objective' => $objective]);
    }

    public function viewList(Request $request){
        $categories = Category::orderBy('order')->get();
        return view('portal.catalogs.objectives',[
            'categories' => $categories
        ]);
    }

    public function viewCatalog()
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

        return view('portal.catalogs.catalog', [
            'categories' => $categories,
        ]);
    }

    public function fetch(Request $request)
    {
        $pageSize = $request->query('size',10);
        $orderBy = $request->query('order_by');
        $hidden = $request->query('hidden',false);
        $category = $request->query('category',null);
        $title = $request->query('s',null);

        $objectives = Objective::with('strategicObjective.category');
        if(!is_null($orderBy)){
            $orderByParams = explode(',',$orderBy);
            $objectives->orderBy($orderByParams[0],$orderByParams[1]);
        }
        if(!is_null($category)){
            $objectives->whereHas('strategicObjective', function ($query) use ($category) {
                $query->where('category_id', $category);
            });
        }
        if(!is_null($title)){
            $titleExploded = explode(' ', $title);
            $objectives->where(function ($query) use ($titleExploded) {
                foreach ($titleExploded as $keyword) {
                $query->orWhere('trace', 'like', "%{$keyword}%");
                }
            });
        }
        $objectives->where('hidden',false);
        $objectives = $objectives->paginate($pageSize)->withQueryString();
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
        $reports = Report::query()->forListing(explode(',', (string) $request->query('with')));
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
