<?php

namespace App\Http\Requests\Admin;

use App\Models\Hairdresser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or editing a hairdresser from the panel. Whether she can be
 * deactivated (PRF-163) is decided by UpdateHairdresser, under the
 * bookings' lock.
 */
class HairdresserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.Hairdresser::MAX_NAME_LENGTH, Rule::unique('hairdressers', 'name')->ignore($this->route('hairdresser'))],
            'is_active' => ['boolean'],
            // PRF-173: the active services she stands out in, if any.
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['distinct', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre de la peluquera.',
            'name.unique' => 'Ya hay una peluquera con ese nombre.',
            'name.max' => 'Como mucho, '.Hairdresser::MAX_NAME_LENGTH.' caracteres.',
            'service_ids.*.exists' => 'Elige solo servicios activos.',
        ];
    }

    /**
     * The services she stands out in (PRF-173); none when nothing is
     * checked.
     *
     * @return list<int>
     */
    public function standoutServiceIds(): array
    {
        return array_values(array_map('intval', $this->validated('service_ids') ?? []));
    }

    /**
     * @return array{name: string, is_active: bool}
     */
    public function hairdresserAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
