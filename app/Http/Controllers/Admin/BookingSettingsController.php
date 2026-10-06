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
        $settings = BookingSetting::current();
        $data = $request->validated();
        $data['online_booking_enabled'] = $request->has('online_booking_enabled')
            ? $request->boolean('online_booking_enabled')
            : $settings->online_booking_enabled;

        $settings->update($data);

        return redirect()->route('admin.settings.edit')->with('status', 'Ajustes guardados.');
    }
}
