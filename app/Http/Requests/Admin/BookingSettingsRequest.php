<?php

namespace App\Http\Requests\Admin;

use App\Models\BookingSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'capacity' => ['required', 'integer', 'between:1,10'],
            'slot_interval_minutes' => ['required', 'integer', Rule::in(BookingSetting::SLOT_INTERVALS)],
            'min_notice_minutes' => ['required', 'integer', 'between:0,10080'],
            'max_advance_days' => ['required', 'integer', 'between:1,365'],
            'cancellation_limit_hours' => ['required', 'integer', 'between:0,168'],
            // "sometimes": the form always sends it (a hidden "0" before
            // the checkbox, the usual Laravel pattern), but a request
            // that omits it entirely (an old API caller, a test written
            // before this field existed) leaves the switch as it was
            // instead of silently turning booking off.
            'online_booking_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
