<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HairdresserRequest;
use App\Models\Hairdresser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Hairdressers cannot be deleted: one who no longer works at the salon is
 * deactivated, so her past appointments keep pointing at her.
 */
class HairdresserController extends Controller
{
    public function index(): View
    {
        return view('admin.hairdressers.index', ['hairdressers' => Hairdresser::query()->ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.hairdressers.create', ['hairdresser' => new Hairdresser(['is_active' => true])]);
    }

    public function store(HairdresserRequest $request): RedirectResponse
    {
        Hairdresser::create($request->hairdresserAttributes());

        return redirect()->route('admin.hairdressers.index')->with('status', 'Peluquera creada.');
    }

    public function edit(Hairdresser $hairdresser): View
    {
        return view('admin.hairdressers.edit', ['hairdresser' => $hairdresser]);
    }

    public function update(HairdresserRequest $request, Hairdresser $hairdresser): RedirectResponse
    {
        $hairdresser->update($request->hairdresserAttributes());

        return redirect()->route('admin.hairdressers.index')->with('status', 'Peluquera actualizada.');
    }
}
