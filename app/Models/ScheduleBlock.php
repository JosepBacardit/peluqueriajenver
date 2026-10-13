<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period in which the capacity is reduced by `capacity_reduction`
 * appointments at a time, or fully closed when it is null. With a
 * hairdresser_id it is that hairdresser's own block (capacity_reduction 1):
 * she is not available, the others are.
 *
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 */
#[Fillable(['starts_at', 'ends_at', 'capacity_reduction', 'hairdresser_id', 'reason'])]
class ScheduleBlock extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'capacity_reduction' => 'integer',
            'hairdresser_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<ScheduleBlock>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    /**
     * @return BelongsTo<Hairdresser, $this>
     */
    public function hairdresser(): BelongsTo
    {
        return $this->belongsTo(Hairdresser::class);
    }

    public function isFullClosure(): bool
    {
        return $this->capacity_reduction === null;
    }
}
