<?php

namespace App\Http\Controllers\Admin;

use App\Actions\UpdateHairdresser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HairdresserRequest;
use App\Models\Hairdresser;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Hairdressers cannot be deleted: one who no longer works at the salon is
 * deactivated, so her past appointments keep pointing at her.
 */
class HairdresserController extends Controller
{
    /**
     * The services a hairdresser can stand out in (PRF-173).
     *
     * @return Collection<int, Service>
     */
    private static function services(): Collection
    {
        return Service::query()->where('is_active', true)->ordered()->get();
    }

    public function index(): View
    {
        return view('admin.hairdressers.index', ['hairdressers' => Hairdresser::query()->ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.hairdressers.create', ['hairdresser' => new Hairdresser(['is_active' => true]), 'services' => self::services(), 'standoutIds' => []]);
    }

    public function store(HairdresserRequest $request): RedirectResponse
    {
        Hairdresser::create($request->hairdresserAttributes())->standoutServices()->sync($request->standoutServiceIds());

        return redirect()->route('admin.hairdressers.index')->with('status', 'Peluquera creada.');
    }

    public function edit(Hairdresser $hairdresser): View
    {
        return view('admin.hairdressers.edit', [
            'hairdresser' => $hairdresser,
            'services' => self::services(),
            'standoutIds' => $hairdresser->standoutServices()->pluck('services.id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function update(HairdresserRequest $request, Hairdresser $hairdresser, UpdateHairdresser $updateHairdresser): RedirectResponse
    {
        $updateHairdresser->handle($hairdresser, $request->hairdresserAttributes(), $request->standoutServiceIds());

        return redirect()->route('admin.hairdressers.index')->with('status', 'Peluquera actualizada.');
    }
}
