<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
            // Empty means a full closure.
            'capacity_reduction' => ['nullable', 'integer', 'between:1,10'],
            'reason' => ['nullable', 'string', 'max:150'],
        ];
    }
}
