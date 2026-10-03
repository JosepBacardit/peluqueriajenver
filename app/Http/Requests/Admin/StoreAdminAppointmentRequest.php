<?php

namespace App\Http\Requests\Admin;

use App\Rules\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An appointment recorded by the salon (usually a phone or WhatsApp
 * booking). Email is optional here.
 */
class StoreAdminAppointmentRequest extends FormRequest
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
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:\d[05]$/'],
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:20', new PhoneNumber],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'time.regex' => 'La hora debe ser múltiplo de 5 minutos.',
            'service_id.exists' => 'Elige un servicio activo.',
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->validated('date').' '.$this->validated('time'))->startOfMinute();
    }

    /**
     * @return array{customer_name: string, customer_phone: string, customer_email: string|null, notes: string|null}
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
