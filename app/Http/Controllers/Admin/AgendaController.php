<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(Request $request): View
    {
        $day = self::dayFromQuery($request->query('fecha'));

        return view('admin.agenda.index', [
            'day' => $day,
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
