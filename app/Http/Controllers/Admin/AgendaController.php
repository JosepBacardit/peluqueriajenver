<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
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
            'appointments' => Appointment::query()
                ->where('starts_at', '>=', $day)
                ->where('starts_at', '<', $day->addDay())
                ->orderBy('starts_at')
                ->orderBy('id')
                ->get(),
            'blocks' => ScheduleBlock::query()->overlapping($day, $day->addDay())->orderBy('starts_at')->get(),
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
}
