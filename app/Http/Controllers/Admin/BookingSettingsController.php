<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingSettingsRequest;
use App\Models\BookingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => BookingSetting::current(),
            'intervals' => BookingSetting::SLOT_INTERVALS,
        ]);
    }

    public function update(BookingSettingsRequest $request): RedirectResponse
    {
        BookingSetting::current()->update($request->validated());

        return redirect()->route('admin.settings.edit')->with('status', 'Ajustes guardados.');
    }
}
