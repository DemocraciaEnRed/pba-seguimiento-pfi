<?php

namespace App\Http\Resources;

use Auth;
// use App\Http\Resources\User as UserResource;
// use App\Testimony;
use App\Http\Resources\Goal as GoalResource;
use App\Http\Resources\GoalHierarchy as GoalHierarchyResource;
use App\Http\Resources\Testimony as TestimonyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SimpleReport extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $res = [
            'id' => $this->id,
            'goal_id' => $this->goal_id,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'type_icon' => $this->type_icon,
            'title' => $this->title,
            'date' => $this->date,
            'when' => $this->date->diffForHumans(),
            'tags' => $this->tags,
            'status' => $this->status,
            'status' => $this->status_label,
            'map_lat' => $this->map_lat,
            'map_long' => $this->map_long,
            'map_zoom' => $this->map_zoom,
            'comments_count' => $this->comments_count ?? $this->comments()->count(),
            'positive_testimonies_count' => $this->positive_testimonies_count ?? $this->positiveTestimonies()->count(),
            'negative_testimonies_count' => $this->negative_testimonies_count ?? $this->negativeTestimonies()->count(),
            'created_at' => $this->created_at,
            'published_at' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at,
            'url' => route('reports.index',['reportId' => $this->id])
        ];
        $user = Auth::user();
        $with = $request->query('with');
        if(!is_null($with)){
          $withParams = explode(',',$with);
          foreach ($withParams as $withParam) {
            switch($withParam){
              case 'report_goal':
                $res['goal'] = GoalResource::make($this->goal);
                break;
              case 'report_hierarchy':
                $res['hierarchy'] = GoalHierarchyResource::make($this->goal);
                break;
              case 'report_actions': 
                if($user){
                    $res['testimony'] = TestimonyResource::make($this->userTestimony($user->id));
                    $res['testimony_url'] = route('apiService.reports.testimonies.run',$this->id);    
                }
                break;
              case 'report_cover':
                $res['cover'] = $this->cover();
                break;
              case 'report_excerpt':
                // Pad tags with a space so adjacent blocks don't glue their words together.
                $res['excerpt'] = Str::limit(Str::squish(html_entity_decode(strip_tags(str_replace('<', ' <', (string) $this->content)))), 160);
                break;
              case 'report_highlights':
                $res['highlights'] = [
                    'status_change' => $this->statusChange(),
                    'indicator' => $this->indicatorChange(),
                    'milestone' => $this->type === 'milestone' && $this->milestone
                        ? ['order' => $this->milestone->order, 'title' => $this->milestone->title]
                        : null,
                ];
                break;
              default:
                break;
            }
          }
        }
        return $res;
    }

    /**
     * @return array{thumbnail_url: string, url: string}|null
     */
    private function cover(): ?array
    {
        $photo = $this->photos->first();

        if (is_null($photo)) {
            return null;
        }

        return [
            'thumbnail_url' => asset($photo->thumbnail_path ?? $photo->path),
            'url' => asset($photo->path),
        ];
    }

    /**
     * @return array{from: string|null, from_label: string, to: string, to_label: string}|null
     */
    private function statusChange(): ?array
    {
        if (is_null($this->status)) {
            return null;
        }

        return [
            'from' => $this->previous_status,
            'from_label' => $this->previous_status_label,
            'to' => $this->status,
            'to_label' => $this->status_label,
        ];
    }

    /**
     * @return array{kind: string, from: string, to: string, increment: string, is_decrease: bool, unit: string|null}|array{kind: string, period_label: string, measured_value: string, unit: string|null}|null
     */
    private function indicatorChange(): ?array
    {
        $goal = $this->goal;

        if ($this->type !== 'progress' || is_null($goal)) {
            return null;
        }

        if ($goal->isPeriodic() && $this->period) {
            return [
                'kind' => 'periodic',
                'period_label' => $this->period->label(),
                'measured_value' => indicator_number($this->measured_value),
                'unit' => $goal->indicator_unit,
            ];
        }

        if ($goal->isSimple() && ! is_null($this->progress)) {
            $from = $this->previous_progress ?? 0.0;

            return [
                'kind' => 'simple',
                'from' => indicator_number($from),
                'to' => indicator_number($from + $this->progress),
                'increment' => ($this->progress > 0 ? '+' : '').indicator_number($this->progress),
                'is_decrease' => $this->progress < 0,
                'unit' => $goal->indicator_unit,
            ];
        }

        return null;
    }
}
