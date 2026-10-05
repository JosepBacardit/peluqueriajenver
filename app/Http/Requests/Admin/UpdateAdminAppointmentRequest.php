<?php

namespace App\Http\Requests\Admin;

use App\Models\Appointment;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Moving an appointment from the panel and editing its customer details,
 * with the same field rules as recording a new one. The appointment may
 * keep its own service even if it has been deactivated since; any other
 * service must be active.
 *
 * `force` is the salon's explicit "save anyway" after the warning that the
 * new time is full or outside opening hours. The button carries the
 * service, date and time it was shown for (slotKey()), so it only
 * confirms that exact choice: if the salon changes any of them after the
 * warning, the new choice is checked (and warned about) again.
 */
class UpdateAdminAppointmentRequest extends StoreAdminAppointmentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->route('appointment');

        return array_merge(parent::rules(), [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(
                fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $appointment->service_id)
            )],
            'force' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * Identifies the service, date and time a "save anyway" confirmation
     * applies to.
     */
    public function slotKey(): string
    {
        return implode('|', [$this->validated('service_id'), $this->validated('date'), $this->validated('time')]);
    }

    public function confirmsSlot(): bool
    {
        return $this->validated('force') === $this->slotKey();
    }
}
