<?php

use App\Models\Appointment;
use App\Models\Service;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the catalog seeds the 24 real services, grouped by family, with no price set', function () {
    (new ServiceCatalogSeeder)->run();

    expect(Service::count())->toBe(24);
    expect(Service::query()->whereNotNull('price_cents')->count())->toBe(0);

    $balayage = Service::where('name', 'Balayage')->first();
    expect($balayage->duration_minutes)->toBe(120);
    expect($balayage->sort_order)->toBe(10);
    expect($balayage->is_bookable_online)->toBeTrue();
    expect($balayage->is_active)->toBeTrue();
});

test('running the seeder twice does not duplicate anything', function () {
    (new ServiceCatalogSeeder)->run();
    (new ServiceCatalogSeeder)->run();

    expect(Service::count())->toBe(24);
});

test('"corte de chico" does not exist: dropped, agreed to be the same as "corte caballero"', function () {
    (new ServiceCatalogSeeder)->run();

    expect(Service::where('name', 'like', '%chico%')->exists())->toBeFalse();
});

test('"arreglo de barba y corte de chico" became one 15-minute service, not two 30-minute ones', function () {
    (new ServiceCatalogSeeder)->run();

    $service = Service::where('name', 'Arreglo de barba')->first();
    expect($service)->not->toBeNull();
    expect($service->duration_minutes)->toBe(15);
});

test('peinado de boda and maquillaje are not bookable online', function () {
    (new ServiceCatalogSeeder)->run();

    expect(Service::where('name', 'Peinado de boda')->first()->is_bookable_online)->toBeFalse();
    expect(Service::where('name', 'Maquillaje')->first()->is_bookable_online)->toBeFalse();
});

test('the services the hairdresser never mentioned start deactivated, for the salon to confirm', function () {
    (new ServiceCatalogSeeder)->run();

    foreach (['Decoloración', 'Recogidos', 'Depilación de labio', 'Manicura', 'Maquillaje', 'Definición afro y rizos', 'Trenzas protectoras'] as $name) {
        expect(Service::where('name', $name)->first()->is_active)
            ->toBeFalse("{$name} should start deactivated");
    }
});

test('local Faker test leftovers with no appointment are deactivated, not deleted', function () {
    Service::factory()->create(['name' => 'Facilis debitis', 'is_active' => true]);
    Service::factory()->create(['name' => 'Iste rem', 'is_active' => true]);

    (new ServiceCatalogSeeder)->run();

    expect(Service::where('name', 'Facilis debitis')->first()->is_active)->toBeFalse();
    expect(Service::where('name', 'Iste rem')->first()->is_active)->toBeFalse();
});

test('"corte de pelo hombre" and "corte/arreglo barba" are renamed in place, keeping their id and their appointments', function () {
    $hombre = Service::factory()->create(['name' => 'Corte de Pelo Hombre', 'duration_minutes' => 15]);
    $barba = Service::factory()->create(['name' => 'Corte/Arreglo barba', 'duration_minutes' => 10]);

    $appointment = Appointment::factory()->withServices($hombre)->create();
    $appointmentService = $appointment->items()->first();

    (new ServiceCatalogSeeder)->run();

    // Same row, same id: the FK in appointment_services (restrictOnDelete)
    // never had to be touched.
    expect(Service::find($hombre->id)->name)->toBe('Corte caballero');
    expect(Service::find($hombre->id)->duration_minutes)->toBe(45);
    expect(Service::find($barba->id)->name)->toBe('Arreglo de barba');
    expect(Service::find($barba->id)->duration_minutes)->toBe(15);
    expect(Service::where('name', 'Corte de Pelo Hombre')->exists())->toBeFalse();
    expect(Service::where('name', 'Corte/Arreglo barba')->exists())->toBeFalse();

    // Renaming/updating the service's duration from 15 to 45 minutes must
    // not touch the appointment that already booked it at 15.
    expect($appointmentService->fresh()->duration_minutes)->toBe(15);
    expect($appointment->fresh()->durationMinutes())->toBe(15);
});

test('running the seeder a second time is a no-op for the already-renamed rows', function () {
    Service::factory()->create(['name' => 'Corte de Pelo Hombre', 'duration_minutes' => 15]);

    (new ServiceCatalogSeeder)->run();
    $idAfterFirstRun = Service::where('name', 'Corte caballero')->first()->id;

    (new ServiceCatalogSeeder)->run();

    expect(Service::where('name', 'Corte caballero')->count())->toBe(1);
    expect(Service::where('name', 'Corte caballero')->first()->id)->toBe($idAfterFirstRun);
});

/**
 * What T054/T055's "a confirmar" rows and the renaming above both rely
 * on: a service's own duration (and name, price) is never read back from
 * an existing appointment — it is copied once, at booking time, into
 * appointment_services (PRF-126), so editing the catalogue afterwards
 * (by hand from the panel, or by re-running this seeder) can never change
 * what an existing appointment already shows the customer or the salon.
 */
test('changing a service duration never alters an appointment that already booked it', function () {
    $service = Service::factory()->create(['duration_minutes' => 45]);
    $appointment = Appointment::factory()->withServices($service)->create();
    $appointmentService = $appointment->items()->first();

    $service->update(['duration_minutes' => 90]);

    expect($appointmentService->fresh()->duration_minutes)->toBe(45);
    expect($appointment->fresh()->durationMinutes())->toBe(45);
});
