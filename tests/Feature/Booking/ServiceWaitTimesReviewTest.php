<?php

use App\Booking\AvailabilityCalculator;
use App\Booking\TimeProfile;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/*
 * Findings of the independent review .ai/reviews/service-wait-times.md
 * (T066), with the user's decisions.
 */

/**
 * @return array<string, mixed>
 */
function reviewServicePayload(array $steps, array $overrides = []): array
{
    return array_merge([
        'name' => 'Coloración', 'price' => '', 'is_bookable_online' => '1', 'is_active' => '1', 'sort_order' => 1,
        'work_1' => '', 'wait_1' => '', 'work_2' => '', 'wait_2' => '', 'work_3' => '',
    ], $steps, $overrides);
}

test('M1: the service forms leave validation to the server, so the plain messages always show', function () {
    $this->actingAs(User::factory()->create());
    $service = Service::factory()->create();

    $this->get(route('admin.services.create'))->assertSee('novalidate', false);
    $this->get(route('admin.services.edit', $service))->assertSee('novalidate', false);
});

test('M1: a 0 in a wait or in a later work counts as empty, with no error', function (array $steps, int $duration, ?array $waits) {
    $this->actingAs(User::factory()->create());

    $this->post(route('admin.services.store'), reviewServicePayload($steps))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.services.index'));

    expect(Service::sole()->duration_minutes)->toBe($duration);
    expect(Service::sole()->waits)->toBe($waits);
})->with([
    'no wait' => [['work_1' => '30', 'wait_1' => '0'], 30, null],
    'no wait, no second work' => [['work_1' => '30', 'wait_1' => '0', 'work_2' => '0'], 30, null],
    'no second wait' => [['work_1' => '30', 'wait_1' => '45', 'work_2' => '45', 'wait_2' => '0', 'work_3' => '0'], 120, [['start' => 30, 'minutes' => 45]]],
]);

test('M1: a 0 in the first work is still an error', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('admin.services.store'), reviewServicePayload(['work_1' => '0']))
        ->assertSessionHasErrors(['work_1' => 'Como mínimo, 5 minutos.']);
});

test('L1: the order of the steps is checked along with every other field', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('admin.services.store'), reviewServicePayload(['work_1' => '30', 'wait_1' => '45'], ['name' => '']))
        ->assertSessionHasErrors(['name', 'work_2' => 'Después de una espera tiene que haber un tiempo de trabajo.']);
});

test('L4: the error of a total over 10 hours is tied to the steps', function () {
    $this->actingAs(User::factory()->create());

    $this->from(route('admin.services.create'))
        ->followingRedirects()
        ->post(route('admin.services.store'), reviewServicePayload(['work_1' => '300', 'wait_1' => '200', 'work_2' => '200']))
        ->assertSee('id="duration_minutes-error"', false)
        ->assertSee('<fieldset class="space-y-3" aria-describedby="duration_minutes-error">', false)
        ->assertSee('name="work_1" type="number" inputmode="numeric" min="5" max="600" step="5" value="300" class="step-minutes w-24 min-h-11 text-lg text-center bg-black border border-[#2A2A2A] px-2 focus:border-gold focus:outline-none" aria-invalid="true" aria-describedby="duration_minutes-error"', false);
});

test('L5: an appointment whose stored waits do not fit is read as one with no waits, never a 500', function () {
    Log::spy();
    BookingSetting::current()->update(['capacity' => 1, 'min_notice_minutes' => 0]);
    // A wait running past the end of a 60-minute appointment.
    $broken = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00', 'waits' => [['start' => 30, 'minutes' => 45]]]);

    expect(TimeProfile::fromAppointment($broken)->activeOffsets())->toBe([[0, 60]]);
    // Taken the whole hour (more room taken, never less): no overbooking.
    expect(app(AvailabilityCalculator::class)->isAvailable(30, CarbonImmutable::parse('2030-01-08 10:30'), CarbonImmutable::parse('2030-01-07 20:00'), applyPublicRules: false))->toBeFalse();

    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    $this->get(route('admin.agenda', ['fecha' => '2030-01-08']))->assertOk();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'waits'));
});

test('L5: a service whose stored waits do not fit is offered with no waits, never a 500', function () {
    Log::spy();
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $broken = Service::factory()->create(['duration_minutes' => 60, 'waits' => [['start' => 50, 'minutes' => 30]]]);

    expect(TimeProfile::fromServices([$broken])->waits)->toBe([]);
    $this->get(route('reservas', ['servicio' => $broken->id, 'fecha' => '2030-01-08']))->assertOk();
});

test('L5: the catalogue seeder never leaves waits that no longer fit a renamed service', function () {
    Service::factory()->create(['name' => 'Corte de Pelo Hombre', 'duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]]]);

    $this->seed(ServiceCatalogSeeder::class);

    $renamed = Service::where('name', 'Corte caballero')->sole();
    expect($renamed->duration_minutes)->toBe(45);
    expect($renamed->waits)->toBeNull();
});
