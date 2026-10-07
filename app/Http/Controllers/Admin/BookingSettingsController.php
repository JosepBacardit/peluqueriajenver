<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingSettingsRequest;
use App\Models\BookingSetting;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => BookingSetting::current(),
            'intervals' => BookingSetting::SLOT_INTERVALS,
            // PRF-148: this admin page has no fixed query budget, unlike
            // the agenda (PRF-109/PRF-118), so one extra read here is fine.
            'hasBookableOnlineService' => Service::query()->bookableOnline()->exists(),
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
