<?php

namespace App\Http\Requests\Admin;

use App\Booking\TimeProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The service's times come as steps, in the order they happen (user's
 * request, 2026-10-07: hairdressers who do not use these tools much must
 * find it simple): Trabajo 1, Espera 1, Trabajo 2, Espera 2, Trabajo 3.
 * Only the first is required. The duration is their sum and the waits
 * follow from them (TimeProfile::fromSteps()); both are stored as before.
 */
class ServiceRequest extends FormRequest
{
    /**
     * The step fields, in order: every other one is a wait. With
     * TimeProfile::MAX_WAITS_PER_SERVICE = 2 waits, 3 works.
     */
    public const STEP_FIELDS = ['work_1', 'wait_1', 'work_2', 'wait_2', 'work_3'];

    /**
     * How each step is named in "Rellena primero …".
     */
    private const STEP_NAMES = ['el trabajo 1', 'la espera 1', 'el trabajo 2', 'la espera 2', 'el trabajo 3'];

    private const MAX_TOTAL_MINUTES = 600;

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
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'price' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,9999.99'],
            'is_bookable_online' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ];

        foreach (self::STEP_FIELDS as $index => $field) {
            $rules[$field] = ['bail', $index === 0 ? 'required' : 'nullable', 'integer', 'min:5', 'max:'.self::MAX_TOTAL_MINUTES, 'multiple_of:5'];
        }

        return $rules;
    }

    /**
     * Plain words, next to the step they are about.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = ['work_1.required' => 'Escribe cuántos minutos dura el trabajo 1.'];

        foreach (self::STEP_FIELDS as $field) {
            $messages["{$field}.integer"] = 'Escribe solo el número de minutos.';
            $messages["{$field}.min"] = 'Como mínimo, 5 minutos.';
            $messages["{$field}.max"] = 'Como máximo, '.self::MAX_TOTAL_MINUTES.' minutos.';
            $messages["{$field}.multiple_of"] = 'Usa múltiplos de 5 minutos (5, 10, 15…).';
        }

        return $messages;
    }

    /**
     * Once every step is valid on its own: no gap before a filled step,
     * the last one a work (a wait always sits between two works), and the
     * total within the limit. The error goes on the step to fix.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $filled = array_map(fn (string $field) => filled($this->input($field)), self::STEP_FIELDS);
            $last = (int) array_key_last(array_filter($filled));

            for ($index = 1; $index < $last; $index++) {
                if (! $filled[$index]) {
                    $validator->errors()->add(self::STEP_FIELDS[$index], 'Rellena primero '.self::STEP_NAMES[$index].'.');

                    return;
                }
            }

            if ($last % 2 === 1) {
                $validator->errors()->add(self::STEP_FIELDS[$last + 1], 'Después de una espera tiene que haber un tiempo de trabajo.');

                return;
            }

            if (array_sum($this->steps()) > self::MAX_TOTAL_MINUTES) {
                $validator->errors()->add('duration_minutes', 'El servicio entero no puede pasar de 10 horas ('.self::MAX_TOTAL_MINUTES.' minutos).');
            }
        }];
    }

    /**
     * Validated data mapped to the service's columns.
     *
     * @return array{name: string, duration_minutes: int, waits: list<array{start: int, minutes: int}>|null, price_cents: int|null, is_bookable_online: bool, is_active: bool, sort_order: int}
     */
    public function serviceAttributes(): array
    {
        $price = $this->validated('price');
        $profile = TimeProfile::fromSteps($this->steps());

        return [
            'name' => trim($this->validated('name')),
            'duration_minutes' => $profile->durationMinutes,
            'waits' => $profile->waitsForStorage(),
            'price_cents' => $price === null ? null : (int) round(((float) $price) * 100),
            'is_bookable_online' => $this->boolean('is_bookable_online'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) ($this->validated('sort_order') ?? 0),
        ];
    }

    /**
     * The filled steps' minutes, in order (after() guarantees there is no
     * gap once validation passes).
     *
     * @return list<int>
     */
    private function steps(): array
    {
        $steps = [];

        foreach (self::STEP_FIELDS as $field) {
            if (filled($this->input($field))) {
                $steps[] = (int) $this->input($field);
            }
        }

        return $steps;
    }
}
