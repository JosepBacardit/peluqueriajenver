<?php

namespace App\Http\Requests\Admin;

use App\Booking\TimeProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * Each wait row is "from minute X, for Y minutes", both or neither.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'duration_minutes' => ['required', 'integer', 'between:5,600', 'multiple_of:5'],
            'waits' => ['nullable', 'array:0,1'],
            'waits.*' => ['array:start,minutes'],
            'waits.*.start' => ['nullable', 'required_with:waits.*.minutes', 'integer', 'between:5,595', 'multiple_of:5'],
            'waits.*.minutes' => ['nullable', 'required_with:waits.*.start', 'integer', 'between:5,590', 'multiple_of:5'],
            'price' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,9999.99'],
            'is_bookable_online' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        for ($row = 0; $row < TimeProfile::MAX_WAITS_PER_SERVICE; $row++) {
            $attributes["waits.{$row}.start"] = 'el minuto en que empieza la espera '.($row + 1);
            $attributes["waits.{$row}.minutes"] = 'la duración de la espera '.($row + 1);
        }

        return $attributes;
    }

    /**
     * Once every field is valid on its own: each wait must end before the
     * service does (so there is always an active stretch after it), and
     * the second one must start after the first one ends (so there is an
     * active stretch between them). The first row always comes first.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $duration = (int) $this->input('duration_minutes');
            $previous = null; // [row, end minute] of the previous filled row

            foreach ($this->filledWaitRows() as $row => $wait) {
                $number = $row + 1;

                if ($duration <= $wait['start'] + $wait['minutes']) {
                    $validator->errors()->add("waits.{$row}.minutes", "La espera {$number} tiene que terminar antes del final del servicio.");

                    return;
                }

                if ($previous !== null && $wait['start'] <= $previous[1]) {
                    $validator->errors()->add("waits.{$row}.start", "La espera {$number} tiene que empezar después de que termine la espera ".($previous[0] + 1).', con tiempo de trabajo entre las dos.');

                    return;
                }

                $previous = [$row, $wait['start'] + $wait['minutes']];
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
        $duration = (int) $this->validated('duration_minutes');

        return [
            'name' => trim($this->validated('name')),
            'duration_minutes' => $duration,
            'waits' => (new TimeProfile($duration, array_values($this->filledWaitRows())))->waitsForStorage(),
            'price_cents' => $price === null ? null : (int) round(((float) $price) * 100),
            'is_bookable_online' => $this->boolean('is_bookable_online'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) ($this->validated('sort_order') ?? 0),
        ];
    }

    /**
     * The wait rows with both fields filled, by row index (an empty row is
     * simply no wait).
     *
     * @return array<int, array{start: int, minutes: int}>
     */
    private function filledWaitRows(): array
    {
        $rows = [];

        foreach ((array) $this->input('waits', []) as $row => $wait) {
            if (is_array($wait) && filled($wait['start'] ?? null) && filled($wait['minutes'] ?? null)) {
                $rows[(int) $row] = ['start' => (int) $wait['start'], 'minutes' => (int) $wait['minutes']];
            }
        }

        ksort($rows);

        return $rows;
    }
}
