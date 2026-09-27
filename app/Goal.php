<?php

namespace App;

use App\Services\Indicators\ComplianceCalculator;
use App\Services\Indicators\Direction;
use App\Services\Indicators\IndicatorSummary;
use App\Services\Indicators\MeasurementMode;
use App\Services\Indicators\Nature;
use App\Services\Indicators\PeriodType;
use App\Services\Indicators\TargetSemantics;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use SoftDeletes;

    protected $table = 'goals';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.
    protected $appends = ['progress_percentage','status_label'];

    protected $attributes = [
        'measurement_mode' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'measurement_mode' => MeasurementMode::class,
            'indicator_goal' => 'float',
            'indicator_progress' => 'float',
            'indicator_direction' => Direction::class,
            'indicator_nature' => Nature::class,
            'target_semantics' => TargetSemantics::class,
            'period_type' => PeriodType::class,
            'period_start' => 'immutable_date',
            'period_count' => 'integer',
        ];
    }

    public function objective()
    {
        return $this->belongsTo('App\Objective');
    }

    public function milestones()
    {
        return $this->hasMany('App\Milestone','goal_id')->orderBy('order','ASC');
    }

    public function reports()
    {
        return $this->hasMany('App\Report','goal_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(GoalPeriod::class, 'goal_id')->orderBy('number');
    }

    public function isPeriodic(): bool
    {
        return $this->measurement_mode === MeasurementMode::Periodic;
    }

    public function isSimple(): bool
    {
        return $this->measurement_mode === MeasurementMode::Simple;
    }

    public function acceptsProgressReports(): bool
    {
        return $this->measurement_mode !== MeasurementMode::None;
    }

    /**
     * Mode and period structure cannot change once progress has been reported.
     */
    public function hasProgressReports(): bool
    {
        return $this->reports()->where('type', 'progress')->exists();
    }

    public function indicatorSummary(?CarbonInterface $today = null): ?IndicatorSummary
    {
        if (! $this->isPeriodic()) {
            return null;
        }

        $this->loadMissing('periods.progressReport');

        return (new ComplianceCalculator())->summarize(
            $this->indicator_direction,
            $this->indicator_nature,
            $this->target_semantics ?? TargetSemantics::Incremental,
            $this->periods->map->toPeriodInput()->all(),
            $today ?? CarbonImmutable::today(),
        );
    }

    public function hasReport($reportId){
        return $this->reports()->where('id', $reportId)->exists();
    }

    public function getStatusLabelAttribute()
    {
        switch($this->status){
            case 'reached':
                return 'Alcanzada';
                break;
            case 'ongoing':
                return 'En progreso';
                break;
            case 'delayed':
                return 'No cumplida';
                break;
            case 'inactive':
                return 'Inactiva';
                break;

            default:
                return '???';
        }
    }

    /**
     * Simple goals: progress towards the final value. Periodic goals: compliance to date.
     */
    public function getProgressPercentageAttribute(){
        return match ($this->measurement_mode) {
            MeasurementMode::Simple => $this->indicator_goal ? round(($this->indicator_progress / $this->indicator_goal) * 100) : null,
            MeasurementMode::Periodic => ($compliance = $this->indicatorSummary()?->toDateCompliance) === null ? null : round($compliance * 100),
            default => null,
        };
    }

    public function getProgressLabelAttribute(): string
    {
        return $this->progress_percentage === null ? '—' : $this->progress_percentage.'%';
    }
}
