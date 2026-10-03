<?php

namespace App\Http\Requests\Admin;

use App\Models\OpeningHour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The weekly schedule form: for each ISO weekday (1-7), two optional
 * ranges `days[<weekday>][0|1][opens|closes]` in "HH:MM".
 */
class OpeningHoursRequest extends FormRequest
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
        $time = ['nullable', 'date_format:H:i', 'regex:/^\d{2}:\d[05]$/'];

        return [
            'days' => ['required', 'array'],
            'days.*' => ['array', 'max:2'],
            'days.*.*.opens' => $time,
            'days.*.*.closes' => $time,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.*.*.opens.regex' => 'Las horas deben ser múltiplos de 5 minutos.',
            'days.*.*.closes.regex' => 'Las horas deben ser múltiplos de 5 minutos.',
            'days.*.*.opens.date_format' => 'Escribe la hora con el formato HH:MM.',
            'days.*.*.closes.date_format' => 'Escribe la hora con el formato HH:MM.',
        ];
    }

    /**
     * Cross-field checks per day: complete ranges, end after start, and a
     * second range that starts after the first one ends.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->ranges() as $weekday => $ranges) {
                $error = $this->dayError($this->input("days.{$weekday}", []));

                if ($error !== null) {
                    $validator->errors()->add("days.{$weekday}", OpeningHour::WEEKDAY_NAMES[$weekday].': '.$error);
                }
            }
        }];
    }

    /**
     * Complete ranges per weekday, as minutes since midnight.
     *
     * @return array<int, list<array{opens: string, closes: string}>>
     */
    public function ranges(): array
    {
        $ranges = [];

        foreach (array_keys(OpeningHour::WEEKDAY_NAMES) as $weekday) {
            $ranges[$weekday] = [];

            foreach ((array) $this->input("days.{$weekday}", []) as $range) {
                if (filled($range['opens'] ?? null) && filled($range['closes'] ?? null)) {
                    $ranges[$weekday][] = ['opens' => $range['opens'], 'closes' => $range['closes']];
                }
            }
        }

        return $ranges;
    }

    /**
     * @param  array<int, array{opens?: string|null, closes?: string|null}>  $dayInput
     */
    private function dayError(array $dayInput): ?string
    {
        $previousClose = null;

        foreach ($dayInput as $range) {
            $opens = $range['opens'] ?? null;
            $closes = $range['closes'] ?? null;

            if (blank($opens) && blank($closes)) {
                continue;
            }

            if (blank($opens) || blank($closes)) {
                return 'cada tramo necesita hora de inicio y de fin.';
            }

            if (OpeningHour::toMinutes($closes) <= OpeningHour::toMinutes($opens)) {
                return 'la hora de fin debe ser posterior a la de inicio.';
            }

            if ($previousClose !== null && OpeningHour::toMinutes($opens) < $previousClose) {
                return 'el segundo tramo debe empezar cuando termina el primero o después.';
            }

            $previousClose = OpeningHour::toMinutes($closes);
        }

        return null;
    }
}
