<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'duration_minutes', 'waits', 'price_cents', 'is_bookable_online', 'is_active', 'sort_order'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            // Waits inside the service (App\Booking\TimeProfile), or null.
            'waits' => 'array',
            'price_cents' => 'integer',
            'is_bookable_online' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Services offered on the public booking page.
     *
     * @param  Builder<Service>  $query
     */
    public function scopeBookableOnline(Builder $query): void
    {
        $query->where('is_active', true)->where('is_bookable_online', true);
    }

    /**
     * Human duration such as "45 min", "2 h" or "1 h 30 min".
     *
     * @return Attribute<string, never>
     */
    protected function durationLabel(): Attribute
    {
        return Attribute::get(fn (): string => self::formatDuration($this->duration_minutes));
    }

    public static function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => "{$rest} min",
            $rest === 0 => "{$hours} h",
            default => "{$hours} h {$rest} min",
        };
    }
}
