<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One opening range of a weekday. `weekday` is ISO-8601 (1 = Monday,
 * 7 = Sunday); `opens_at`/`closes_at` are "HH:MM:SS" local times.
 */
#[Fillable(['weekday', 'opens_at', 'closes_at'])]
class OpeningHour extends Model
{
    public const WEEKDAY_NAMES = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function opensAtMinutes(): int
    {
        return self::toMinutes($this->opens_at);
    }

    public function closesAtMinutes(): int
    {
        return self::toMinutes($this->closes_at);
    }

    /**
     * Minutes since midnight for an "HH:MM" or "HH:MM:SS" time.
     */
    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
