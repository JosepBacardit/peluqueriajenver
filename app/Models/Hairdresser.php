<?php

namespace App\Models;

use Database\Factories\HairdresserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hairdresser of the salon. Never deleted, only deactivated: the
 * booking capacity is the number of active ones (AvailabilityCalculator),
 * and customers only ever see her name.
 */
#[Fillable(['name', 'is_active'])]
class Hairdresser extends Model
{
    /** @use HasFactory<HairdresserFactory> */
    use HasFactory;

    public const MAX_NAME_LENGTH = 50;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Hairdresser>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The order they were created in: the agenda's columns, the selectors.
     *
     * @param  Builder<Hairdresser>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('id');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Confirmed appointments assigned to her that have not started yet:
     * while she has any, she cannot be deactivated.
     *
     * @return HasMany<Appointment, $this>
     */
    public function upcomingAppointments(): HasMany
    {
        return $this->appointments()->confirmed()->where('starts_at', '>', now())->orderBy('starts_at');
    }
}
