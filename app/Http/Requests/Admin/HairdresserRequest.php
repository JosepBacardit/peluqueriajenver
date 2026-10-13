<?php

namespace App\Http\Requests\Admin;

use App\Models\Appointment;
use App\Models\Hairdresser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating or editing a hairdresser from the panel. She can only be
 * deactivated once she has no upcoming confirmed appointments assigned:
 * the salon reassigns or cancels them by hand first (user decision,
 * 2026-10-13).
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
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Hairdresser|null $hairdresser */
            $hairdresser = $this->route('hairdresser');

            if ($hairdresser === null || ! $hairdresser->is_active || $this->boolean('is_active')) {
                return;
            }

            $upcoming = $hairdresser->upcomingAppointments()->get(['starts_at']);

            if ($upcoming->isEmpty()) {
                return;
            }

            /** @var Appointment $first */
            $first = $upcoming->first();
            $count = $upcoming->count();
            $validator->errors()->add('is_active', sprintf(
                '%s tiene %s a partir de hoy (la primera, el %s a las %s). Reasígnalas o cancélalas antes de desactivarla.',
                $hairdresser->name,
                $count === 1 ? '1 cita asignada' : "{$count} citas asignadas",
                $first->starts_at->format('d/m/Y'),
                $first->starts_at->format('H:i'),
            ));
        }];
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
