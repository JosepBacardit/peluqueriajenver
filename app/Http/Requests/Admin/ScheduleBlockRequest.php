<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A closure of the whole salon ("total") or of one or more hairdressers
 * ("hairdressers", PRF-170): a day off, a medical appointment, a leave.
 * The old unnamed "reduce N appointments at a time" closures are no longer
 * created, only still honoured where they exist.
 */
class ScheduleBlockRequest extends FormRequest
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
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'scope' => ['required', Rule::in(['total', 'hairdressers'])],
            'hairdresser_ids' => ['exclude_unless:scope,hairdressers', 'required', 'array', 'min:1'],
            'hairdresser_ids.*' => ['exclude_unless:scope,hairdressers', 'distinct', 'integer', Rule::exists('hairdressers', 'id')->where('is_active', true)],
            'reason' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.required' => 'Elige si cierra todo el salón o solo alguna peluquera.',
            'scope.in' => 'Elige si cierra todo el salón o solo alguna peluquera.',
            'hairdresser_ids.required' => 'Marca qué peluqueras no están.',
            'hairdresser_ids.*.exists' => 'Elige solo peluqueras activas.',
        ];
    }

    /**
     * The hairdressers this closure is for, or [] for the whole salon.
     *
     * @return list<int>
     */
    public function hairdresserIds(): array
    {
        return $this->validated('scope') === 'hairdressers'
            ? array_values(array_map('intval', $this->validated('hairdresser_ids')))
            : [];
    }
}
