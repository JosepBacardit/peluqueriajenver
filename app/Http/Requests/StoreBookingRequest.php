<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Rules\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * A booking sent from the public booking page. Whether the time is still
 * available is decided afterwards by CreateAppointment, not here.
 */
class StoreBookingRequest extends FormRequest
{
    /**
     * Name of the honeypot field: hidden from people, filled by bots.
     */
    public const HONEYPOT = 'website';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * A filled honeypot gets the same kind of answer as a real booking
     * (a redirect with a success message) but nothing is saved or sent.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled(self::HONEYPOT)) {
            throw new HttpResponseException(
                redirect()->route('reservas')->with('status', __('reservas.messages.received'))
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // PRF-127: 1 to MAX_SERVICES distinct services. Whether each
            // exists and is reservable online is checked afterwards in the
            // controller (as it already was for a single service), since
            // rejecting the whole booking either way needs the same
            // "esa hora ya no está disponible" message (PRF-128, PRF-033).
            'service_ids' => ['required', 'array', 'min:1', 'max:'.Appointment::MAX_SERVICES],
            'service_ids.*' => ['distinct', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:20', new PhoneNumber],
            'customer_email' => ['required', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'privacy' => ['accepted'],
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->validated('date').' '.$this->validated('time'))->startOfMinute();
    }

    /**
     * @return array{customer_name: string, customer_phone: string, customer_email: string, notes: string|null}
     */
    public function customer(): array
    {
        return [
            'customer_name' => $this->validated('customer_name'),
            'customer_phone' => $this->validated('customer_phone'),
            'customer_email' => $this->validated('customer_email'),
            'notes' => $this->validated('notes'),
        ];
    }
}
