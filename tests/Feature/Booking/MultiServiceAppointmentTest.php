<?php

use App\Actions\CancelAppointment;
use App\Actions\CreateAppointment;
use App\Actions\RescheduleAppointment;
use App\Booking\AppointmentNotMovableException;
use App\Booking\ServiceList;
use App\Booking\SlotUnavailableException;
use App\Booking\TooManyUpcomingAppointmentsException;
use App\Enums\AppointmentSource;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\BookingSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * Several services in one appointment (PRF-125 to PRF-131), at the level
 * of the actions. Reference day: Tuesday 2030-01-08, open 09:00-19:00;
 * "now" is the evening before; capacity 1 unless a test says otherwise.
 */
beforeEach(function () {
    BookingSetting::current()->update(['capacity' => 1, 'min_notice_minutes' => 0, 'slot_interval_minutes' => 5]);
    $this->now = CarbonImmutable::parse('2030-01-07 20:00');
    $this->corte = Service::factory()->create(['name' => 'Corte de Pelo Hombre', 'duration_minutes' => 15, 'price_cents' => 1200, 'sort_order' => 1]);
    $this->barba = Service::factory()->create(['name' => 'Corte/Arreglo barba', 'duration_minutes' => 10, 'price_cents' => 800, 'sort_order' => 2]);
    $this->tinte = Service::factory()->create(['name' => 'Tinte', 'duration_minutes' => 60, 'price_cents' => 3500, 'sort_order' => 3]);
});

/**
 * @return array{customer_name: string, customer_phone: string, customer_email: string|null, notes: string|null}
 */
function multiCustomer(string $email = 'pau@example.test', string $phone = '611 111 111'): array
{
    return ['customer_name' => 'Pau', 'customer_phone' => $phone, 'customer_email' => $email, 'notes' => null];
}

function bookServices(array $services, string $startsAt = '2030-01-08 10:00', bool $publicRules = false, ?array $customer = null): Appointment
{
    return app(CreateAppointment::class)->handle(
        collect($services), CarbonImmutable::parse($startsAt), $customer ?? multiCustomer(),
        $publicRules ? AppointmentSource::Web : AppointmentSource::Admin, $publicRules, test()->now,
    );
}

/**
 * @param  array<int, Service>  $services
 */
function rebook(Appointment $appointment, array $services, string $startsAt = '2030-01-08 10:00'): void
{
    app(RescheduleAppointment::class)->handle(
        $appointment, collect($services), CarbonImmutable::parse($startsAt),
        $appointment->only(['customer_name', 'customer_phone', 'customer_email', 'notes']), false, test()->now,
    );
}

/**
 * @return list<array{service_id: int, position: int, service_name: string, duration_minutes: int, price_cents: int|null}>
 */
function itemsOf(Appointment $appointment): array
{
    return AppointmentService::query()->where('appointment_id', $appointment->id)->orderBy('position')
        ->get(['service_id', 'position', 'service_name', 'duration_minutes', 'price_cents'])
        ->map(fn (AppointmentService $item) => $item->only(['service_id', 'position', 'service_name', 'duration_minutes', 'price_cents']))
        ->all();
}

/**
 * Makes the first query matching $sqlFragment fail, as a database error
 * halfway through an action would.
 */
function failOnQuery(string $sqlFragment): void
{
    DB::listen(function ($query) use ($sqlFragment) {
        if (str_contains($query->sql, $sqlFragment)) {
            throw new RuntimeException('Simulated database failure');
        }
    });
}

test('one appointment holds several services one after another, in the salon\'s order, for the sum of their durations', function () {
    $appointment = bookServices([$this->barba, $this->corte]);

    expect($appointment->services_label)->toBe('Corte de Pelo Hombre + Corte/Arreglo barba');
    expect($appointment->ends_at->format('H:i'))->toBe('10:25');
    expect($appointment->durationMinutes())->toBe(25);
    expect(itemsOf($appointment))->toBe([
        ['service_id' => $this->corte->id, 'position' => 1, 'service_name' => 'Corte de Pelo Hombre', 'duration_minutes' => 15, 'price_cents' => 1200],
        ['service_id' => $this->barba->id, 'position' => 2, 'service_name' => 'Corte/Arreglo barba', 'duration_minutes' => 10, 'price_cents' => 800],
    ]);
    expect($appointment->services->pluck('id')->all())->toBe([$this->corte->id, $this->barba->id]);
});

test('the services keep the name, duration and price they had when booked', function () {
    $appointment = bookServices([$this->corte, $this->barba]);

    $this->corte->update(['name' => 'Corte caballero', 'duration_minutes' => 30, 'price_cents' => 1500]);

    expect($appointment->fresh()->services_label)->toBe('Corte de Pelo Hombre + Corte/Arreglo barba');
    expect($appointment->fresh()->ends_at->format('H:i'))->toBe('10:25');
    expect(itemsOf($appointment)[0])->toMatchArray(['service_name' => 'Corte de Pelo Hombre', 'duration_minutes' => 15, 'price_cents' => 1200]);
});

test('availability is checked for the whole length of the services', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 10:20', 'ends_at' => '2030-01-08 11:00']);

    // The haircut alone (10:00-10:15) fits; with the beard (until 10:25) it does not.
    expect(fn () => bookServices([$this->corte, $this->barba]))->toThrow(SlotUnavailableException::class);
    expect(bookServices([$this->corte])->ends_at->format('H:i'))->toBe('10:15');
});

test('an appointment with several services takes a single place', function () {
    BookingSetting::current()->update(['capacity' => 2]);

    bookServices([$this->corte, $this->barba], customer: multiCustomer('a@example.test', '611 000 001'));
    bookServices([$this->corte], customer: multiCustomer('b@example.test', '611 000 002'));

    expect(fn () => bookServices([$this->barba], customer: multiCustomer('c@example.test', '611 000 003')))->toThrow(SlotUnavailableException::class);
    expect(Appointment::count())->toBe(2);
});

test('up to the maximum number of services can be booked together', function () {
    $services = collect([$this->corte, $this->barba, $this->tinte])
        ->merge(Service::factory()->count(Appointment::MAX_SERVICES - 3)->create(['duration_minutes' => 5, 'sort_order' => 9]));

    $appointment = bookServices($services->all());

    expect(AppointmentService::where('appointment_id', $appointment->id)->count())->toBe(Appointment::MAX_SERVICES);
    expect(Appointment::MAX_SERVICES)->toBe(5);
});

test('an empty list, too many services or a repeated service books nothing', function (Closure $services) {
    expect(fn () => bookServices($services()))->toThrow(InvalidArgumentException::class);

    expect(Appointment::count())->toBe(0);
    expect(AppointmentService::count())->toBe(0);
})->with([
    'no service' => [fn () => []],
    'one more than the maximum' => [fn () => Service::factory()->count(Appointment::MAX_SERVICES + 1)->create(['duration_minutes' => 5])->all()],
    'the same service twice' => [fn () => [test()->corte, test()->corte]],
]);

test('the per-customer limit of upcoming online bookings counts appointments, not services', function () {
    $customer = multiCustomer('limite@example.test', '622 222 222');

    bookServices([$this->corte, $this->barba, $this->tinte], '2030-01-08 10:00', publicRules: true, customer: $customer);
    bookServices([$this->corte], '2030-01-08 12:00', publicRules: true, customer: $customer);

    expect(fn () => bookServices([$this->barba], '2030-01-08 14:00', publicRules: true, customer: $customer))
        ->toThrow(TooManyUpcomingAppointmentsException::class);
});

test('the appointment and its services are written after the lock, in one transaction', function () {
    $queries = sqlWithVisibleLocks(fn () => bookServices([$this->corte, $this->barba]));

    $first = fn (string $fragment) => collect($queries)->search(fn (string $sql) => str_contains($sql, $fragment));

    expect($queries[0])->toContain('"booking_settings"')->toContain('for update');
    expect($first('insert into "appointments"'))->toBeLessThan($first('insert into "appointment_services"'));
});

test('a failure while writing the services leaves no appointment behind', function () {
    failOnQuery('insert into "appointment_services"');

    expect(fn () => bookServices([$this->corte, $this->barba]))->toThrow(RuntimeException::class);

    expect(Appointment::count())->toBe(0);
    expect(AppointmentService::count())->toBe(0);
});

test('moving keeps the frozen data of the services it already had and takes the current data of the new ones', function () {
    $appointment = bookServices([$this->corte]);
    $this->corte->update(['name' => 'Corte caballero', 'duration_minutes' => 30, 'price_cents' => 1500]);

    rebook($appointment, [$this->barba, $this->corte], '2030-01-08 12:00');

    $moved = $appointment->fresh();
    expect($moved->services_label)->toBe('Corte de Pelo Hombre + Corte/Arreglo barba');
    expect($moved->starts_at->format('H:i'))->toBe('12:00');
    expect($moved->ends_at->format('H:i'))->toBe('12:25');
    expect(itemsOf($moved))->toBe([
        ['service_id' => $this->corte->id, 'position' => 1, 'service_name' => 'Corte de Pelo Hombre', 'duration_minutes' => 15, 'price_cents' => 1200],
        ['service_id' => $this->barba->id, 'position' => 2, 'service_name' => 'Corte/Arreglo barba', 'duration_minutes' => 10, 'price_cents' => 800],
    ]);
});

test('removing a service shortens the appointment', function () {
    $appointment = bookServices([$this->corte, $this->barba]);

    rebook($appointment, [$this->barba]);

    expect($appointment->fresh()->ends_at->format('H:i'))->toBe('10:10');
    expect($appointment->fresh()->services_label)->toBe('Corte/Arreglo barba');
    expect(collect(itemsOf($appointment))->pluck('service_id')->all())->toBe([$this->barba->id]);
});

test('moving with the same services leaves them exactly as booked', function () {
    $appointment = bookServices([$this->corte, $this->barba]);
    $itemIds = AppointmentService::where('appointment_id', $appointment->id)->orderBy('position')->pluck('id')->all();
    $this->barba->update(['name' => 'Barba', 'duration_minutes' => 20]);

    rebook($appointment, [$this->barba, $this->corte], '2030-01-08 12:00');

    $moved = $appointment->fresh();
    expect(AppointmentService::where('appointment_id', $appointment->id)->orderBy('position')->pluck('id')->all())->toBe($itemIds);
    expect($moved->services_label)->toBe('Corte de Pelo Hombre + Corte/Arreglo barba');
    expect($moved->ends_at->format('H:i'))->toBe('12:25');
});

test('the new list of services is checked against availability for its whole length', function () {
    $appointment = bookServices([$this->corte]);
    Appointment::factory()->create(['starts_at' => '2030-01-08 10:20', 'ends_at' => '2030-01-08 11:00']);

    expect(fn () => rebook($appointment, [$this->corte, $this->barba]))->toThrow(SlotUnavailableException::class);
    expect(collect(itemsOf($appointment))->pluck('service_id')->all())->toBe([$this->corte->id]);
});

test('an invalid list of services changes nothing', function () {
    $appointment = bookServices([$this->corte]);

    expect(fn () => rebook($appointment, [$this->barba, $this->barba]))->toThrow(InvalidArgumentException::class);
    expect(fn () => rebook($appointment, []))->toThrow(InvalidArgumentException::class);

    expect(collect(itemsOf($appointment))->pluck('service_id')->all())->toBe([$this->corte->id]);
});

test('the services are replaced after the lock and only once the conditional update has matched the appointment', function () {
    $appointment = bookServices([$this->corte]);

    $queries = sqlWithVisibleLocks(fn () => rebook($appointment, [$this->corte, $this->barba], '2030-01-08 12:00'));
    $first = fn (string $fragment) => collect($queries)->search(fn (string $sql) => str_contains($sql, $fragment));

    expect($queries[0])->toContain('"booking_settings"')->toContain('for update');
    expect($first('update "appointments"'))->toBeLessThan($first('delete from "appointment_services"'));
    expect($first('delete from "appointment_services"'))->toBeLessThan($first('insert into "appointment_services"'));
});

test('a move with new services sent after a concurrent cancellation leaves the services untouched', function () {
    $appointment = bookServices([$this->corte]);
    $cancelRequest = Appointment::find($appointment->id);
    $moveRequest = Appointment::find($appointment->id);

    app(CancelAppointment::class)->handle($cancelRequest);

    expect(fn () => rebook($moveRequest, [$this->corte, $this->barba], '2030-01-08 12:00'))->toThrow(AppointmentNotMovableException::class);
    expect(itemsOf($appointment))->toHaveCount(1);
    expect($appointment->fresh()->services_label)->toBe('Corte de Pelo Hombre');
    expect(AppointmentService::count())->toBe(1);
});

test('two concurrent moves with different services leave exactly the services of the last one', function () {
    $appointment = bookServices([$this->corte]);
    $firstRequest = Appointment::find($appointment->id);
    $secondRequest = Appointment::find($appointment->id);

    rebook($firstRequest, [$this->corte, $this->barba], '2030-01-08 12:00');
    rebook($secondRequest, [$this->tinte], '2030-01-08 15:00');

    $final = $appointment->fresh();
    expect(collect(itemsOf($final))->pluck('service_id')->all())->toBe([$this->tinte->id]);
    expect($final->services_label)->toBe('Tinte');
    expect($final->ends_at->format('H:i'))->toBe('16:00');
    expect(AppointmentService::count())->toBe(1);
});

test('a failure while rewriting the services undoes the whole move', function () {
    $appointment = bookServices([$this->corte]);
    failOnQuery('insert into "appointment_services"');

    expect(fn () => rebook($appointment, [$this->corte, $this->barba], '2030-01-08 12:00'))->toThrow(RuntimeException::class);

    $unchanged = $appointment->fresh();
    expect($unchanged->starts_at->format('H:i'))->toBe('10:00');
    expect($unchanged->services_label)->toBe('Corte de Pelo Hombre');
    expect(collect(itemsOf($unchanged))->pluck('service_id')->all())->toBe([$this->corte->id]);
});

test('the factory books one service by default and the given ones with withServices', function () {
    $default = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:45']);
    $several = Appointment::factory()->withServices($this->corte, $this->barba)->create(['starts_at' => '2030-01-08 12:00']);

    expect(itemsOf($default))->toHaveCount(1);
    expect(itemsOf($default)[0]['duration_minutes'])->toBe(45);
    expect(itemsOf($default)[0]['service_name'])->toBe($default->services_label);
    expect($several->services_label)->toBe('Corte de Pelo Hombre + Corte/Arreglo barba');
    expect($several->ends_at->format('H:i'))->toBe('12:25');
    expect(collect(itemsOf($several))->pluck('service_id')->all())->toBe([$this->corte->id, $this->barba->id]);
});

test('ServiceList returns a plain collection in the salon\'s order', function () {
    $ordered = ServiceList::ordered([$this->tinte, $this->corte, $this->barba]);

    expect($ordered)->toBeInstanceOf(Collection::class);
    expect($ordered->pluck('id')->all())->toBe([$this->corte->id, $this->barba->id, $this->tinte->id]);
});

/**
 * Runs $sideEffect once, right after the move has re-read the
 * appointment's services — that is, inside the window between that
 * re-read and the conditional UPDATE, where a cancellation (which takes no
 * lock on booking_settings) can still commit (review finding M1). SQLite
 * has a single connection, so the side effect runs inside the move's own
 * transaction: when the move is refused and rolled back, the side effect
 * is rolled back with it. What these tests check is that the move refuses
 * and writes nothing of its own, or overwrites everything consistently.
 */
function duringMoveWindow(Closure $sideEffect): void
{
    $fired = false;

    DB::listen(function ($query) use (&$fired, $sideEffect) {
        if (! $fired && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "appointment_services" where "appointment_id"')) {
            $fired = true;
            $sideEffect();
        }
    });
}

test('a cancellation committed between the re-read and the conditional update stops the move before it touches the services', function () {
    $appointment = bookServices([$this->corte]);
    $itemsBefore = itemsOf($appointment);

    duringMoveWindow(fn () => DB::table('appointments')->where('id', $appointment->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]));

    expect(fn () => rebook($appointment, [$this->corte, $this->barba], '2030-01-08 12:00'))->toThrow(AppointmentNotMovableException::class);

    // The refused move wrote nothing: same time, same summary, same rows.
    $after = $appointment->fresh();
    expect($after->starts_at->format('H:i'))->toBe('10:00');
    expect($after->services_label)->toBe('Corte de Pelo Hombre');
    expect(itemsOf($after))->toBe($itemsBefore);
});

test('a move committed by someone else inside the window is overwritten whole, never mixed', function () {
    $appointment = bookServices([$this->corte]);

    // What another move would have committed: a new time, label and rows.
    duringMoveWindow(function () use ($appointment) {
        DB::table('appointments')->where('id', $appointment->id)->update(['services_label' => 'Tinte', 'starts_at' => '2030-01-08 15:00:00', 'ends_at' => '2030-01-08 16:00:00']);
        DB::table('appointment_services')->where('appointment_id', $appointment->id)->delete();
        DB::table('appointment_services')->insert(['appointment_id' => $appointment->id, 'service_id' => $this->tinte->id, 'position' => 1, 'service_name' => 'Tinte', 'duration_minutes' => 60, 'price_cents' => 3500, 'created_at' => now(), 'updated_at' => now()]);
    });

    rebook($appointment, [$this->corte, $this->barba], '2030-01-08 12:00');

    $final = $appointment->fresh();
    expect($final->services_label)->toBe('Corte de Pelo Hombre + Corte/Arreglo barba');
    expect($final->starts_at->format('H:i'))->toBe('12:00');
    expect(collect(itemsOf($final))->pluck('service_id')->all())->toBe([$this->corte->id, $this->barba->id]);
    expect(AppointmentService::count())->toBe(2);
});

test('the same services in a different order are not a change: rows, summary and order are kept', function () {
    $appointment = bookServices([$this->corte, $this->barba]);
    $itemIds = AppointmentService::where('appointment_id', $appointment->id)->orderBy('position')->pluck('id')->all();
    // The salon reorders its catalogue: the beard now goes first.
    $this->barba->update(['sort_order' => 0]);

    $outcome = app(RescheduleAppointment::class)->handle(
        $appointment, collect([$this->barba, $this->corte]), CarbonImmutable::parse('2030-01-08 10:00'),
        $appointment->only(['customer_name', 'customer_phone', 'customer_email', 'notes']), false, $this->now,
    );

    expect($outcome->rescheduled)->toBeFalse();
    expect(AppointmentService::where('appointment_id', $appointment->id)->orderBy('position')->pluck('id')->all())->toBe($itemIds);
    expect($appointment->fresh()->services_label)->toBe('Corte de Pelo Hombre + Corte/Arreglo barba');
});

test('services with the same "Orden" go in the order they were created, never by name', function () {
    // Created in this order; by name (any collation) "Arreglo" would go first.
    $corte = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 15, 'sort_order' => 7]);
    $arreglo = Service::factory()->create(['name' => 'arreglo', 'duration_minutes' => 10, 'sort_order' => 7]);

    $appointment = bookServices([$arreglo, $corte]);

    expect($appointment->services_label)->toBe('Corte + arreglo');
    expect(ServiceList::sort([$arreglo, $corte])->pluck('id')->all())->toBe([$corte->id, $arreglo->id]);
});
