<?php

namespace App\Http\Controllers\Admin;

use App\Booking\AvailabilityCalculator;
use App\Booking\DayTimeline;
use App\Booking\ServiceList;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(private AvailabilityCalculator $calculator) {}

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

        // Service filter (PRF-120, PRF-132, T043/T048): loaded once, reused
        // both for the checkbox list's options and to validate "servicio"
        // against it — a malformed, repeated or inactive id is silently
        // ignored, never an error, and the list is capped at MAX_SERVICES
        // (extra ids beyond that are silently dropped too, same spirit).
        // $servicioQuery is the single query-string fragment every link in
        // the three views (tabs, Anterior/Siguiente/Hoy, the date form,
        // Nueva cita, each hueco libre, Mes's day cells) splats in, so the
        // filter survives navigation (including through Mes, which has no
        // selector of its own) without repeating the same ['servicio' =>
        // ...] literal at every one of those call sites.
        $services = Service::query()->where('is_active', true)->ordered()->get();
        $servicios = self::serviciosFromQuery($request->query('servicio'), $services);
        // A single selected service keeps using a scalar "servicio=N" (as
        // short as before T048, PRF-123's existing links), the array form
        // "servicio[]=..." only once there is more than one (PRF-132).
        $servicioIds = $servicios->pluck('id')->all();
        $servicioQuery = match (count($servicioIds)) {
            0 => [],
            1 => ['servicio' => $servicioIds[0]],
            default => ['servicio' => $servicioIds],
        };

        // One fetch reused for the capacity (Día/Semana) and for the
        // "Reserva online desactivada" banner, instead of a second,
        // separate query for the banner on top of dayData()/weekData()'s
        // own (both already fixed-query-count, PRF-109/PRF-118).
        //
        // Review finding L3: this does add one query to vista Mes, which
        // never read BookingSetting before — not trivial to remove, since
        // the banner is meant to show on Mes too (an admin flipping to
        // that tab should not stop seeing it), and showing it there
        // needs this same read regardless of how the code is shaped;
        // moving it inside match()'s 'mes' arm would just be the same
        // query under a different line, not a real saving. Justified,
        // not fixed: one extra cheap read per Mes page load, in exchange
        // for the banner never going missing on the one tab that doesn't
        // otherwise touch BookingSetting.
        $settings = BookingSetting::current();

        return view('admin.agenda.index', [
            'vista' => $vista,
            'day' => $day,
            'weekStart' => $weekStart,
            'weekEnd' => $weekLastDay,
            'month' => $month,
            // "volver" (review finding M1): where Nueva cita/Editar/Cancelar
            // should return to, so acting on a day doesn't silently drop
            // back to vista Día. Deliberately never carries "servicio"
            // (coordinator's decision 1): it stays its own query parameter
            // everywhere, same as "hora" already is, so volver's
            // "vista:fecha" format and its whitelist parsing never change.
            'volver' => "$vista:{$day->toDateString()}",
            'services' => $services,
            'servicios' => $servicios,
            'servicioQuery' => $servicioQuery,
            'onlineBookingEnabled' => $settings->online_booking_enabled,
            ...match ($vista) {
                'semana' => $this->weekData($weekStart, $servicios, $settings->capacity),
                'mes' => $this->monthData($month),
                default => $this->dayData($day, $servicios, $settings->capacity),
            },
        ]);
    }

    /**
     * The selected services (PRF-120, PRF-132), 1 to
     * Appointment::MAX_SERVICES of them, in the order $services (already
     * sorted) gives them — never a second query to validate "servicio"
     * against it. Anything not numeric, repeated, or past the maximum
     * count is dropped silently; a missing or empty "servicio" (or one
     * where every id is invalid) returns an empty collection, the same as
     * "Cualquiera".
     *
     * @param  Collection<int, Service>  $services
     * @return Collection<int, Service>
     */
    public static function serviciosFromQuery(mixed $value, Collection $services): Collection
    {
        if ($value === null || $value === '') {
            return collect();
        }

        $ids = is_array($value) ? $value : [$value];
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
        $ids = array_slice($ids, 0, Appointment::MAX_SERVICES);

        // The salon's order of an appointment's services (review finding
        // L2): the same ServiceList::sort() the booking is saved with.
        return ServiceList::sort($services->whereIn('id', $ids));
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
     * Besides the appointments/blocks the cards below the grid already
     * needed, this loads every opening-hours row once (for the shared
     * week-wide grid bounds, PRF-108, and this weekday's own ranges) and
     * the capacity (for the fixed lane count, PRF-109) to build the
     * timeline grid — 4 queries total, none repeated per block or per
     * lane.
     *
     * @return array{appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>, gridStart: int, gridEnd: int, timeline: array, nowLineTop: int|null}
     */
    private function dayData(CarbonImmutable $day, Collection $servicios, int $capacity): array
    {
        $appointments = Appointment::query()
            ->where('starts_at', '>=', $day)
            ->where('starts_at', '<', $day->addDay())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
        $blocks = ScheduleBlock::query()->overlapping($day, $day->addDay())->orderBy('starts_at')->get();
        $allRanges = OpeningHour::query()->get();
        $bounds = DayTimeline::weekBounds($allRanges);
        // Review finding H1: a confirmed appointment forced outside the
        // normal opening hours ("Guardar igualmente") must not be left
        // occupying an invisible lane — the grid grows to cover it.
        $bounds = DayTimeline::extendBounds($bounds['start'], $bounds['end'], $appointments);
        $dayRanges = $allRanges->where('weekday', $day->isoWeekday())->sortBy('opens_at')->values();
        $now = CarbonImmutable::now();

        $timeline = DayTimeline::build($day, $bounds['start'], $bounds['end'], $capacity, $dayRanges, $appointments, $blocks, $now);
        $timeline = $this->markServiceFit($timeline, $servicios, $day, $dayRanges, $appointments, $blocks, $capacity);

        return [
            'appointments' => $appointments,
            'blocks' => $blocks,
            'gridStart' => $bounds['start'],
            'gridEnd' => $bounds['end'],
            'timeline' => $timeline,
            'nowLineTop' => DayTimeline::nowLineTop($day, $bounds['start'], $bounds['end'], $now),
        ];
    }

    /**
     * PRF-120/123/124/132: with no service selected, returns $timeline
     * untouched (no extra work at all). With 1 to MAX_SERVICES, finds
     * which of its already-tappable free half hours the sum of their
     * durations fits into — no query, since
     * AvailabilityCalculator::fittingStartMinutes() works entirely off the
     * $ranges/$appointments/$blocks the caller already loaded — and flags
     * them for the view (DayTimeline::markServiceFit()).
     *
     * @param  Collection<int, Service>  $servicios
     */
    private function markServiceFit(array $timeline, Collection $servicios, CarbonImmutable $day, Collection $ranges, Collection $appointments, Collection $blocks, int $capacity): array
    {
        if ($servicios->isEmpty()) {
            return $timeline;
        }

        $durationMinutes = (int) $servicios->sum('duration_minutes');
        $candidates = DayTimeline::tappableFreeMinutes($timeline);
        $fitting = $this->calculator->fittingStartMinutes($durationMinutes, $candidates, $day, $ranges, $appointments, $blocks, $capacity);

        return DayTimeline::markServiceFit($timeline, $fitting, $durationMinutes);
    }

    /**
     * One query for the week's appointments and one for its blocks
     * (PRF-100, PRF-101): both grouped in PHP per day, never queried once
     * per day of the week. The timeline grid (T036) adds two more fixed
     * queries (every opening-hours row, and the capacity) reused for all
     * 7 days and for the shared grid bounds — still none repeated per day.
     *
     * @return array{days: list<array{date: CarbonImmutable, appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>, isClosed: bool, hasPartialClosure: bool, isToday: bool, timeline: array}>, gridStart: int, gridEnd: int}
     */
    private function weekData(CarbonImmutable $weekStart, Collection $servicios, int $capacity): array
    {
        $weekEnd = $weekStart->addWeek();

        $appointments = Appointment::query()
            ->where('starts_at', '>=', $weekStart)
            ->where('starts_at', '<', $weekEnd)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $blocks = ScheduleBlock::query()->overlapping($weekStart, $weekEnd)->orderBy('starts_at')->get();
        $allRanges = OpeningHour::query()->get();
        $openWeekdays = $allRanges->pluck('weekday')->unique()->all();
        $bounds = DayTimeline::weekBounds($allRanges);
        // Review finding H1: extend the whole week's shared bounds (every
        // column uses the same axis) so a confirmed appointment forced
        // outside the normal opening hours in any of its 7 days is never
        // left occupying an invisible lane.
        $bounds = DayTimeline::extendBounds($bounds['start'], $bounds['end'], $appointments);
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();

        $days = [];
        for ($date = $weekStart; $date->lt($weekEnd); $date = $date->addDay()) {
            $nextDate = $date->addDay();
            $dayAppointments = $appointments->filter(fn (Appointment $a) => $a->starts_at->gte($date) && $a->starts_at->lt($nextDate))->values();
            $dayBlocks = $blocks->filter(fn (ScheduleBlock $b) => $b->starts_at->lt($nextDate) && $b->ends_at->gt($date))->values();
            $dayRanges = $allRanges->where('weekday', $date->isoWeekday())->sortBy('opens_at')->values();
            // A full closure (review finding H1, PRF-105) closes the day
            // the same as having no opening-hours range; a partial one
            // (capacity reduction) does not, but is still worth flagging,
            // same as vista Día's amber banner.
            $hasFullClosure = $dayBlocks->contains(fn (ScheduleBlock $b) => $b->capacity_reduction === null);
            $isClosed = ! in_array($date->isoWeekday(), $openWeekdays, true) || $hasFullClosure;

            $timeline = DayTimeline::build($date, $bounds['start'], $bounds['end'], $capacity, $dayRanges, $dayAppointments, $dayBlocks, $now);
            $timeline = $this->markServiceFit($timeline, $servicios, $date, $dayRanges, $dayAppointments, $dayBlocks, $capacity);

            $days[] = [
                'date' => $date,
                'appointments' => $dayAppointments,
                'blocks' => $dayBlocks,
                'isClosed' => $isClosed,
                'hasPartialClosure' => ! $isClosed && $dayBlocks->contains(fn (ScheduleBlock $b) => $b->capacity_reduction !== null),
                'isToday' => $date->isSameDay($today),
                'timeline' => $timeline,
                'nowLineTop' => DayTimeline::nowLineTop($date, $bounds['start'], $bounds['end'], $now),
            ];
        }

        return ['days' => $days, 'gridStart' => $bounds['start'], 'gridEnd' => $bounds['end']];
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
