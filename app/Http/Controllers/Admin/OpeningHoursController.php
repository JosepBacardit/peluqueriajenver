<?php

namespace App\Http\Controllers\Admin;

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

        foreach (OpeningHour::query()->orderBy('opens_at')->get() as $hour) {
            $days[$hour->weekday]['ranges'][] = [
                'opens' => substr($hour->opens_at, 0, 5),
                'closes' => substr($hour->closes_at, 0, 5),
            ];
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

        return redirect()->route('admin.opening-hours.edit')->with('status', 'Horario guardado.');
    }
}
