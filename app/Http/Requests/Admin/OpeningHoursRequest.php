<?php

namespace App\Http\Requests\Admin;

use App\Models\OpeningHour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The weekly schedule form. For each ISO weekday (1-7):
 * `days[<weekday>][closed]` (checkbox) and, for each of up to two ranges,
 * `days[<weekday>][<0|1>][opens_hour|opens_minute|closes_hour|closes_minute]`
 * (native `<select>`s instead of `<input type="time">`, which the iPhone
 * cannot clear — PRF-133). An hour of '' removes that range; its minute is
 * then irrelevant. `closed` wins over anything sent for that day's ranges,
 * even a tampered request (PRF-134).
 */
class OpeningHoursRequest extends FormRequest
{
    /** @var list<string> */
    public const HOURS = ['07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22'];

    /** @var list<string> */
    public const MINUTES = ['00', '15', '30', '45'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hour = ['nullable', Rule::in(['', ...self::HOURS])];
        $minute = ['nullable', Rule::in(self::MINUTES)];

        return [
            'days' => ['required', 'array'],
            'days.*' => ['array'],
            'days.*.closed' => ['nullable', 'boolean'],
            'days.*.0' => ['nullable', 'array'],
            'days.*.1' => ['nullable', 'array'],
            'days.*.*.opens_hour' => $hour,
            'days.*.*.opens_minute' => $minute,
            'days.*.*.closes_hour' => $hour,
            'days.*.*.closes_minute' => $minute,
        ];
    }

    /**
     * Cross-field checks per day: complete ranges, end after start, and a
     * second range that starts after the first one ends. Skipped entirely
     * for a day marked "closed".
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (array_keys(OpeningHour::WEEKDAY_NAMES) as $weekday) {
                if ($this->dayClosed($weekday)) {
                    continue;
                }

                $error = $this->dayError($this->rawRanges($weekday));

                if ($error !== null) {
                    $validator->errors()->add("days.{$weekday}", OpeningHour::WEEKDAY_NAMES[$weekday].': '.$error);
                }
            }
        }];
    }

    /**
     * Complete ranges per weekday, as "HH:MM" strings ready to save. A day
     * marked "closed" never contributes a range, whatever its selects held.
     *
     * @return array<int, list<array{opens: string, closes: string}>>
     */
    public function ranges(): array
    {
        $ranges = [];

        foreach (array_keys(OpeningHour::WEEKDAY_NAMES) as $weekday) {
            $ranges[$weekday] = [];

            if ($this->dayClosed($weekday)) {
                continue;
            }

            foreach ($this->rawRanges($weekday) as $range) {
                if ($range['opens'] !== null) {
                    $ranges[$weekday][] = ['opens' => $range['opens'], 'closes' => $range['closes']];
                }
            }
        }

        return $ranges;
    }

    private function dayClosed(int $weekday): bool
    {
        return $this->boolean("days.{$weekday}.closed");
    }

    /**
     * The day's up to two ranges as "HH:MM" strings (or null when their
     * hour select was left at "—").
     *
     * @return list<array{opens: string|null, closes: string|null}>
     */
    private function rawRanges(int $weekday): array
    {
        $ranges = [];

        foreach ([0, 1] as $index) {
            $range = (array) $this->input("days.{$weekday}.{$index}", []);

            $ranges[] = [
                'opens' => $this->combine($range['opens_hour'] ?? '', $range['opens_minute'] ?? '00'),
                'closes' => $this->combine($range['closes_hour'] ?? '', $range['closes_minute'] ?? '00'),
            ];
        }

        return $ranges;
    }

    private function combine(string $hour, string $minute): ?string
    {
        return $hour === '' ? null : sprintf('%02d:%02d', (int) $hour, (int) $minute);
    }

    /**
     * @param  list<array{opens: string|null, closes: string|null}>  $dayRanges
     */
    private function dayError(array $dayRanges): ?string
    {
        $previousClose = null;

        foreach ($dayRanges as $range) {
            $opens = $range['opens'];
            $closes = $range['closes'];

            if ($opens === null && $closes === null) {
                continue;
            }

            if ($opens === null || $closes === null) {
                return 'Elige inicio y fin, o deja los dos en —.';
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
