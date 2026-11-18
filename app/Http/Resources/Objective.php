<?php

namespace App\Http\Resources;

use Auth;
use DB;
use App\Goal;
use App\Report;
use Carbon\Carbon;
use App\Http\Resources\Goal as GoalResource;
use App\Http\Resources\Category as CategoryResource;
use App\Http\Resources\Report as ReportResource;
use App\Services\Goals\GoalAudience;
use Illuminate\Http\Resources\Json\JsonResource;

class Objective extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $publicGoals = app(GoalAudience::class)->publicQuery();
        $goals = fn () => Goal::query()
            ->visibleIn($publicGoals)
            ->where('objective_id', $this->getKey());
        $reports = fn () => Report::query()
            ->approved()
            ->visibleIn($publicGoals)
            ->whereIn('goal_id', $goals()->select('goals.id'));

        $res = [
            'id' => $this->id,
            'category' => CategoryResource::make($this->category),
            'category_id' => $this->strategicObjective->category_id,
            'strategic_objective_id' => $this->strategic_objective_id,
            'title' => $this->title,
            'content' => $this->content,
            'tags' => $this->tags,
            'archived' => $this->archived,
            'hidden' => $this->hidden,
            'created_at' => $this->created_at,
            'published_at' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at,
            'updated_when' => $this->updated_at?->diffForHumans(),
            'url' => route('objectives.index',['objectiveId' => $this->id])
        ];

        $with = $request->query('with');
        if(!is_null($with)){
          $withParams = explode(',',$with);
          foreach ($withParams as $withParam) {
            switch($withParam){
              case 'objective_goals':
                $res['goals'] = GoalResource::collection($goals()->get());
                break;
              case 'objective_reports':
                $res['reports'] = GoalResource::collection($goals()->get());
                break;
              case 'objective_latest_goals':
                $res['latest_goals'] = GoalResource::collection($goals()->orderBy('updated_at','DESC')->limit(4)->get());
                break;
              case 'objective_latest_reports':
                $res['latest_reports'] = ReportResource::collection($reports()->orderBy('updated_at','DESC')->limit(4)->get());
                break;
              case 'objective_latest_report':
                $latestReport = $reports()->orderBy('reports.updated_at', 'DESC')->first();
                $res['latest_report'] = $latestReport ? [
                    'id' => $latestReport->id,
                    'title' => $latestReport->title,
                    'type_label' => $latestReport->type_label,
                    'type_icon' => $latestReport->type_icon,
                    'when' => $latestReport->created_at->diffForHumans(),
                    'url' => route('reports.index', ['reportId' => $latestReport->id]),
                ] : null;
                break;
              case 'objective_stats':
                $res['reports_count'] = $this->reports_count ?? $reports()->count();
                $res['goals_count'] = $this->goals_count ?? $goals()->count();
                $res['goals_status'] = $this->resource->hasAttribute('goals_reached_count')
                    ? collect(array_keys(Goal::STATUSES))->mapWithKeys(fn (string $status): array => [$status => (int) $this->{"goals_{$status}_count"}])
                    : $goals()->select('status',DB::raw('COUNT(*) AS total'))->groupBy('status')->get()->pluck('total','status');
                $lastReportDate = $this->resource->hasAttribute('last_report_date')
                    ? $this->last_report_date
                    : $reports()->max('reports.date');
                $res['last_report_when'] = $lastReportDate ? Carbon::parse($lastReportDate)->diffForHumans() : null;
                break;
              case 'objective_strategic_objective':
                $res['strategic_objective'] = [
                    'id' => $this->strategicObjective->id,
                    'codigo' => $this->strategicObjective->codigo,
                    'title' => $this->strategicObjective->title,
                ];
                break;
              default:
                break;
            }
          }
        }
        return $res;
    }
}
