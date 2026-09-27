<?php

namespace App;

use App\Services\Indicators\ComplianceCalculator;
use App\Services\Indicators\PeriodInput;
use App\Services\Indicators\PeriodState;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GoalPeriod extends Model
{
    protected $table = 'goal_periods';

    protected $fillable = ['number', 'starts_on', 'ends_on', 'target_value'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'target_value' => 'float',
            'skipped_at' => 'datetime',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    public function skippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'skipped_by');
    }

    public function progressReport(): HasOne
    {
        return $this->hasOne(Report::class, 'goal_period_id')->where('type', 'progress');
    }

    public function isSkipped(): bool
    {
        return $this->skipped_at !== null;
    }

    public function state(?CarbonInterface $today = null): PeriodState
    {
        return (new ComplianceCalculator())->stateOf($this->toPeriodInput(), $today ?? CarbonImmutable::today());
    }

    public function label(): string
    {
        return "Período {$this->number}";
    }

    public function rangeLabel(): string
    {
        return $this->starts_on->translatedFormat('M Y').' – '.$this->ends_on->translatedFormat('M Y');
    }

    public function toPeriodInput(): PeriodInput
    {
        return new PeriodInput(
            number: $this->number,
            startsOn: $this->starts_on,
            endsOn: $this->ends_on,
            target: $this->target_value,
            measured: $this->progressReport?->measured_value,
            skipped: $this->isSkipped(),
        );
    }
}
