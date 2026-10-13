<?php

namespace App\Actions;

use App\Booking\AvailabilityCalculator;
use App\Booking\TimeProfile;
use App\Booking\UnavailabilityReason;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Hairdresser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a hairdresser from the panel. Deactivating her takes a hairdresser
 * away from every day ahead, so (PRF-163, review M3) it is refused while
 * she has upcoming confirmed appointments assigned, or while any upcoming
 * unassigned one would be left with nobody to do it.
 *
 * Under the same row lock as every booking (CreateAppointment), so a
 * booking for her cannot slip in between the check and the change: the
 * change is made first and checked inside the transaction, which rolls it
 * back when refused.
 */
class UpdateHairdresser
{
    public function __construct(private AvailabilityCalculator $calculator) {}

    /**
     * @param  array{name: string, is_active: bool}  $attributes
     * @param  list<int>  $standoutServiceIds
     *
     * @throws ValidationException
     */
    public function handle(Hairdresser $hairdresser, array $attributes, array $standoutServiceIds, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();

        DB::transaction(function () use ($hairdresser, $attributes, $standoutServiceIds, $now) {
            BookingSetting::query()->lockForUpdate()->orderBy('id')->firstOrFail();

            $deactivating = $hairdresser->fresh()->is_active && ! $attributes['is_active'];

            $hairdresser->update($attributes);
            // Services deactivated since keep no mark: only active ones are offered.
            $hairdresser->standoutServices()->sync($standoutServiceIds);

            if (! $deactivating) {
                return;
            }

            $assigned = $hairdresser->upcomingAppointments()->get(['starts_at']);

            if ($assigned->isNotEmpty()) {
                throw ValidationException::withMessages(['is_active' => self::message(
                    $hairdresser->name.' tiene '.($assigned->count() === 1 ? '1 cita asignada' : $assigned->count().' citas asignadas'),
                    $assigned->first(),
                    'Reasígnalas o cancélalas antes de desactivarla.',
                )]);
            }

            $leftWithNobody = Appointment::query()->confirmed()->whereNull('hairdresser_id')->where('starts_at', '>', $now)->orderBy('starts_at')->get()
                ->filter(fn (Appointment $appointment) => in_array(
                    $this->calculator->unavailabilityReason(TimeProfile::fromAppointment($appointment), $appointment->starts_at, $now, false, excludeAppointmentId: $appointment->id),
                    [UnavailabilityReason::Full, UnavailabilityReason::Closed],
                    true,
                ));

            if ($leftWithNobody->isNotEmpty()) {
                throw ValidationException::withMessages(['is_active' => self::message(
                    'Sin '.$hairdresser->name.', '.($leftWithNobody->count() === 1 ? '1 cita sin peluquera asignada se quedaría sin nadie que la atienda' : $leftWithNobody->count().' citas sin peluquera asignada se quedarían sin nadie que las atienda'),
                    $leftWithNobody->first(),
                    'Asígnalas, muévelas o cancélalas antes de desactivarla.',
                )]);
            }
        });
    }

    private static function message(string $what, Appointment $first, string $then): string
    {
        return sprintf('%s a partir de hoy (la primera, el %s a las %s). %s', $what, $first->starts_at->format('d/m/Y'), $first->starts_at->format('H:i'), $then);
    }
}
