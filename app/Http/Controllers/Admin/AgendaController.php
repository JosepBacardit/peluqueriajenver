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
        // The week's last day (Sunday), inclusive — only for the "Semana
        // del ... al ..." label and the desktop grid's aria-label. Distinct
        // from weekData()'s $weekEnd, the exclusive bound its queries use
        // (review finding L1).
        $weekLastDay = $weekStart->addDays(6);
        $month = $day->startOfMonth();

        return view('admin.agenda.index', [
            'vista' => $vista,
            'day' => $day,
            'weekStart' => $weekStart,
            'weekEnd' => $weekLastDay,
            'month' => $month,
            // "volver" (review finding M1): where Nueva cita/Editar/Cancelar
            // should return to, so acting on a day doesn't silently drop
            // back to vista Día.
            'volver' => "$vista:{$day->toDateString()}",
            ...match ($vista) {
                'semana' => $this->weekData($weekStart),
                'mes' => $this->monthData($month),
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
        if (self::isValidDateString($value)) {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        }

        return CarbonImmutable::today();
    }

    /**
     * Decodes and validates the "volver" parameter (review finding M1): a
     * "vista:fecha" string built by the agenda views, telling
     * AppointmentController where to send the salon back after creating,
     * moving or cancelling an appointment. Both parts are checked against
     * the same whitelist/format as "vista" and "fecha" above (never an
     * arbitrary string), and the result is only ever used to build a
     * route() call to admin.agenda — never as a raw redirect URL, so this
     * can't become an open redirect.
     *
     * @return array{vista: string, fecha: string}|null null when missing or invalid.
     */
    public static function volverFromQuery(mixed $value): ?array
    {
        if (! is_string($value) || ! str_contains($value, ':')) {
            return null;
        }

        [$vista, $fecha] = explode(':', $value, 2);

        if (! in_array($vista, self::VIEWS, true) || ! self::isValidDateString($fecha)) {
            return null;
        }

        return ['vista' => $vista, 'fecha' => $fecha];
    }

    private static function isValidDateString(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $day !== null && $day->format('Y-m-d') === $value;
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
     * @return array{days: list<array{date: CarbonImmutable, appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>, isClosed: bool, hasPartialClosure: bool, isToday: bool}>}
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
            $dayBlocks = $blocks->filter(fn (ScheduleBlock $b) => $b->starts_at->lt($nextDate) && $b->ends_at->gt($date))->values();
            // A full closure (review finding H1, PRF-105) closes the day
            // the same as having no opening-hours range; a partial one
            // (capacity reduction) does not, but is still worth flagging,
            // same as vista Día's amber banner.
            $hasFullClosure = $dayBlocks->contains(fn (ScheduleBlock $b) => $b->capacity_reduction === null);
            $isClosed = ! in_array($date->isoWeekday(), $openWeekdays, true) || $hasFullClosure;

            $days[] = [
                'date' => $date,
                'appointments' => $appointments->filter(fn (Appointment $a) => $a->starts_at->gte($date) && $a->starts_at->lt($nextDate))->values(),
                'blocks' => $dayBlocks,
                'isClosed' => $isClosed,
                'hasPartialClosure' => ! $isClosed && $dayBlocks->contains(fn (ScheduleBlock $b) => $b->capacity_reduction !== null),
                'isToday' => $date->isSameDay($today),
            ];
        }

        return ['days' => $days];
    }

    /**
     * One aggregate query for the month's occupancy (PRF-102): never one
     * query per day, and never loading every appointment of the month.
     *
     * @return array{occupancy: Collection<string, int>, openWeekdays: list<int>, fullClosures: Collection<int, ScheduleBlock>}
     */
    private function monthData(CarbonImmutable $month): array
    {
        $monthEnd = $month->addMonth();

        $occupancy = Appointment::query()->confirmed()
            ->where('starts_at', '>=', $month)
            ->where('starts_at', '<', $monthEnd)
            ->selectRaw('DATE(starts_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $fullClosures = ScheduleBlock::query()
            ->whereNull('capacity_reduction')
            ->overlapping($month, $monthEnd)
            ->get(['starts_at', 'ends_at']);

        return [
            'occupancy' => $occupancy,
            'openWeekdays' => self::openWeekdays(),
            'fullClosures' => $fullClosures,
        ];
    }

    /**
     * @return list<int> ISO-8601 weekdays (1-7) with at least one opening range.
     */
    private static function openWeekdays(): array
    {
        return OpeningHour::query()->distinct()->pluck('weekday')->all();
    }
}
