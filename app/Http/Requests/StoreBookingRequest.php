<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Rules\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * False while the "Reserva online activa" switch is off, or on with
     * no service reservable online (PRF-147): checked before any field
     * rule, so a POST sent in either case (a stale tab, a replayed
     * request) never reaches validation or creates anything.
     */
    public function authorize(): bool
    {
        return BookingSetting::onlineBookingAvailable();
    }

    /**
     * Failing this is always "booking is not available" (switch off, or
     * no bookable-online service) — sent back to /reservas, which shows
     * that explanation on its own (BookingController::index()).
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(redirect()->route('reservas'));
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
            // "list" (review finding M2): service_ids[a]=3 or
            // service_ids[5]=3 is rejected here instead of reaching the
            // controller with keys it does not expect.
            'service_ids' => ['required', 'list', 'min:1', 'max:'.Appointment::MAX_SERVICES],
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

    /**
     * A bad list of services (repeated, more than MAX_SERVICES, keys that
     * are not a list, not ids) can only come from a tampered form, and the
     * step it was sent from has no checkboxes to show the error next to.
     * So, instead of going back there with an error nobody would see
     * (review finding M2), it goes back to step 1 with whatever valid ids
     * it had checked and the "invalid selection" notice linked to the
     * checkboxes.
     */
    protected function failedValidation(Validator $validator): void
    {
        $servicesFailed = collect($validator->errors()->keys())
            ->contains(fn (string $key) => $key === 'service_ids' || str_starts_with($key, 'service_ids.'));

        if ($servicesFailed) {
            $raw = $this->input('service_ids');
            $ids = array_values(array_unique(array_filter(is_array($raw) ? $raw : [$raw], fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))));
            $this->redirect = route('reservas', $ids === [] ? [] : ['servicio' => $ids, 'cambiar' => 1]);
        }

        parent::failedValidation($validator);
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
