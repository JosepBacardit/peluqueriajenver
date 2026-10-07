<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleBlockRequest;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ScheduleBlockController extends Controller
{
    public function index(): View
    {
        return view('admin.blocks.index', [
            'blocks' => ScheduleBlock::query()->where('ends_at', '>', now())->orderBy('starts_at')->get(),
        ]);
    }

    /**
     * Existing appointments inside the new block are deliberately left
     * alone: the salon decides what to do with them.
     */
    public function store(ScheduleBlockRequest $request): RedirectResponse
    {
        $startsAt = CarbonImmutable::parse($request->validated('starts_at'));
        $endsAt = CarbonImmutable::parse($request->validated('ends_at'));

        ScheduleBlock::create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity_reduction' => $request->validated('capacity_reduction'),
            'reason' => $request->validated('reason'),
        ]);

        $affected = Appointment::query()->confirmed()->overlapping($startsAt, $endsAt)->count();
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
