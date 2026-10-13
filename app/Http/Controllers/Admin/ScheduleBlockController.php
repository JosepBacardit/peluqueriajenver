<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleBlockRequest;
use App\Models\Appointment;
use App\Models\Hairdresser;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScheduleBlockController extends Controller
{
    public function index(): View
    {
        return view('admin.blocks.index', [
            'blocks' => ScheduleBlock::query()->with('hairdresser')->where('ends_at', '>', now())->orderBy('starts_at')->get(),
            'hairdressers' => Hairdresser::query()->active()->ordered()->get(),
        ]);
    }

    /**
     * A closure of several hairdressers is saved as one closure for each
     * (PRF-170), stored as a reduction of 1 with her id, so anything that
     * only reads the reduction stays right.
     *
     * Existing appointments inside the new block are deliberately left
     * alone: the salon decides what to do with them. A hairdresser's
     * closure only warns about her own appointments; the unassigned ones
     * are counted again by the availability check like everything else.
     */
    public function store(ScheduleBlockRequest $request): RedirectResponse
    {
        $startsAt = CarbonImmutable::parse($request->validated('starts_at'));
        $endsAt = CarbonImmutable::parse($request->validated('ends_at'));
        $hairdresserIds = $request->hairdresserIds();
        $common = ['starts_at' => $startsAt, 'ends_at' => $endsAt, 'reason' => $request->validated('reason')];

        DB::transaction(function () use ($hairdresserIds, $common) {
            if ($hairdresserIds === []) {
                ScheduleBlock::create($common + ['capacity_reduction' => null]);

                return;
            }

            foreach ($hairdresserIds as $hairdresserId) {
                ScheduleBlock::create($common + ['capacity_reduction' => 1, 'hairdresser_id' => $hairdresserId]);
            }
        });

        $affected = Appointment::query()->confirmed()->overlapping($startsAt, $endsAt)
            ->when($hairdresserIds !== [], fn (Builder $query) => $query->whereIn('hairdresser_id', $hairdresserIds))
            ->count();
        $redirect = redirect()->route('admin.blocks.index')->with('status', 'Cierre guardado.');

        if ($affected === 1) {
            $redirect->with('warning', 'Hay 1 cita confirmada en ese periodo. No se ha cancelado: revísala en la agenda.');
        } elseif ($affected > 1) {
            $redirect->with('warning', "Hay {$affected} citas confirmadas en ese periodo. No se han cancelado: revísalas en la agenda.");
        }

        return $redirect;
    }

    public function destroy(ScheduleBlock $block): RedirectResponse
    {
        $block->delete();

        return redirect()->route('admin.blocks.index')->with('status', 'Cierre eliminado.');
    }
}
