<?php

namespace App\Http\Controllers\Admin;

use App\Booking\OpeningHoursSummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpeningHoursRequest;
use App\Models\OpeningHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OpeningHoursController extends Controller
{
    public function edit(): View
    {
        $days = [];

        foreach (OpeningHour::WEEKDAY_NAMES as $weekday => $name) {
            $days[$weekday] = ['name' => $name, 'ranges' => []];
        }

        foreach (OpeningHour::query()->orderBy('weekday')->orderBy('opens_at')->get() as $hour) {
            $days[$hour->weekday]['ranges'][] = [
                'opens_hour' => substr($hour->opens_at, 0, 2),
                'opens_minute' => substr($hour->opens_at, 3, 2),
                'closes_hour' => substr($hour->closes_at, 0, 2),
                'closes_minute' => substr($hour->closes_at, 3, 2),
            ];
        }

        // A day with no ranges at all loads with "Cerrado" already checked
        // (PRF-134): there is no other way to represent it, since a blank
        // pair of selects on a day that simply hasn't been filled in yet
        // would look identical.
        foreach ($days as $weekday => $day) {
            $days[$weekday]['closed'] = $day['ranges'] === [];
        }

        return view('admin.opening-hours.edit', ['days' => $days]);
    }

    /**
     * Replaces the whole week in one transaction, so a failure never
     * leaves half a schedule.
     */
    public function update(OpeningHoursRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            OpeningHour::query()->delete();

            foreach ($request->ranges() as $weekday => $ranges) {
                foreach ($ranges as $range) {
                    OpeningHour::create([
                        'weekday' => $weekday,
                        'opens_at' => $range['opens'].':00',
                        'closes_at' => $range['closes'].':00',
                    ]);
                }
            }
        });

        // The public pages read the schedule through OpeningHoursSummary's
        // own per-request cache; without this, a test (or a request that
        // somehow rendered a public page earlier in the same process)
        // saving a new schedule and then reading it back would still see
        // the one read before the save.
        OpeningHoursSummary::forgetCachedSchedule();

        return redirect()->route('admin.opening-hours.edit')->with('status', 'Horario guardado.');
    }
}
