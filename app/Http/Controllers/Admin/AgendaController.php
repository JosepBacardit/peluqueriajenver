<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AgendaController extends Controller
{
    /**
     * Valid values for the "vista" query parameter (PRF-099).
     */
    private const VIEWS = ['dia', 'semana', 'mes'];

    public function index(Request $request): View
    {
        $vista = self::viewFromQuery($request->query('vista'));
        $day = self::dayFromQuery($request->query('fecha'));
        $weekStart = $day->startOfWeek(CarbonInterface::MONDAY);

        return view('admin.agenda.index', [
            'vista' => $vista,
            'day' => $day,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->addDays(6),
            'month' => $day->startOfMonth(),
            ...match ($vista) {
                'semana' => $this->weekData($weekStart),
                default => $this->dayData($day),
            },
        ]);
    }

    /**
     * The requested view (dia|semana|mes), or "dia" when missing or invalid.
     */
    public static function viewFromQuery(mixed $value): string
    {
        return is_string($value) && in_array($value, self::VIEWS, true) ? $value : 'dia';
    }

    /**
     * The requested day (Y-m-d), or today when missing or invalid.
     */
    public static function dayFromQuery(mixed $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            if ($day !== null && $day->format('Y-m-d') === $value) {
                return $day;
            }
        }

        return CarbonImmutable::today();
    }

    /**
     * @return array{appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>}
     */
    private function dayData(CarbonImmutable $day): array
    {
        return [
            'appointments' => Appointment::query()
                ->where('starts_at', '>=', $day)
                ->where('starts_at', '<', $day->addDay())
                ->orderBy('starts_at')
                ->orderBy('id')
                ->get(),
            'blocks' => ScheduleBlock::query()->overlapping($day, $day->addDay())->orderBy('starts_at')->get(),
        ];
    }

    /**
     * One query for the week's appointments and one for its blocks
     * (PRF-100, PRF-101): both grouped in PHP per day, never queried once
     * per day of the week.
     *
     * @return array{days: list<array{date: CarbonImmutable, appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>, isClosed: bool, isToday: bool}>}
     */
    private function weekData(CarbonImmutable $weekStart): array
    {
        $weekEnd = $weekStart->addWeek();

        $appointments = Appointment::query()
            ->where('starts_at', '>=', $weekStart)
            ->where('starts_at', '<', $weekEnd)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $blocks = ScheduleBlock::query()->overlapping($weekStart, $weekEnd)->orderBy('starts_at')->get();
        $openWeekdays = self::openWeekdays();
        $today = CarbonImmutable::today();

        $days = [];
        for ($date = $weekStart; $date->lt($weekEnd); $date = $date->addDay()) {
            $nextDate = $date->addDay();

            $days[] = [
                'date' => $date,
                'appointments' => $appointments->filter(fn (Appointment $a) => $a->starts_at->gte($date) && $a->starts_at->lt($nextDate))->values(),
                'blocks' => $blocks->filter(fn (ScheduleBlock $b) => $b->starts_at->lt($nextDate) && $b->ends_at->gt($date))->values(),
                'isClosed' => ! in_array($date->isoWeekday(), $openWeekdays, true),
                'isToday' => $date->isSameDay($today),
            ];
        }

        return ['days' => $days];
    }

    /**
     * @return list<int> ISO-8601 weekdays (1-7) with at least one opening range.
     */
    private static function openWeekdays(): array
    {
        return OpeningHour::query()->distinct()->pluck('weekday')->all();
    }
}
