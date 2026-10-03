<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    /**
     * Any logged-in salon user may manage services (routes are behind auth).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'duration_minutes' => ['required', 'integer', 'between:5,600', 'multiple_of:5'],
            'price' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,9999.99'],
            'is_bookable_online' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ];
    }

    /**
     * Validated data mapped to the service's columns.
     *
     * @return array{name: string, duration_minutes: int, price_cents: int|null, is_bookable_online: bool, is_active: bool, sort_order: int}
     */
    public function serviceAttributes(): array
    {
        $price = $this->validated('price');

        return [
            'name' => trim($this->validated('name')),
            'duration_minutes' => (int) $this->validated('duration_minutes'),
            'price_cents' => $price === null ? null : (int) round(((float) $price) * 100),
            'is_bookable_online' => $this->boolean('is_bookable_online'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) ($this->validated('sort_order') ?? 0),
        ];
    }
}
