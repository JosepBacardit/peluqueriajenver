<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * The salon's real service catalogue (2026-10-06), built from the list the
 * hairdresser gave the coordinator, cross-checked against what the public
 * site already advertises (the 4 service pages, lang/es/servicios.php,
 * lang/es/home.php and the JSON-LD): some of her lines cover several
 * services the site lists separately ("Mechas todo tipo" → Balayage,
 * Babylights, Mechas clásicas; "Alisado permanente o keratina" → two
 * services; "Arreglo de barba y corte de chico" → split into "Arreglo de
 * barba", 15 min, per the user — "Corte de chico" was dropped, decided to
 * be the same as "Corte caballero"). A few the site advertises were never
 * in her list at all (Decoloración, Recogidos, Manicura, Maquillaje, the
 * two cabello afro/rizos services): those start `is_active = false`, so
 * the salon only turns them on once she confirms their real duration from
 * the panel, never from a silent guess here.
 *
 * Runs in every environment (local, testing and production): it is the
 * real catalogue, not test data, so `DatabaseSeeder` calls it always.
 *
 * Review finding M1 (.ai/reviews/seeders-account.md, 2026-10-06): the
 * CATALOG loop below seeds each row only once, with `firstOrCreate()` by
 * name, not `updateOrCreate()`. The salon is expected to edit this
 * catalogue from the panel right after it is seeded — activate a service
 * marked "a confirmar", correct a duration, reorder — and a later
 * `db:seed`/`ServiceCatalogSeeder` run (e.g. after a 25th service is
 * added to CATALOG below) must never silently revert any of that back to
 * these fixed values. `price_cents` is always `null` on a *new* row (the
 * salon sets real prices from the panel; `Service::$fillable` and its
 * migration both allow that, it is nullable) and is never touched again
 * once the row exists, for the same reason. Correcting a mistake in
 * CATALOG itself (a wrong duration, a typo) now needs its own deliberate
 * step — e.g. a one-off `Service::where('name', ...)->update([...])`,
 * the same shape as RENAMED_ON_FIRST_RUN below — not just editing this
 * array and re-running the seeder.
 */
class ServiceCatalogSeeder extends Seeder
{
    /**
     * Local-only clean-up before the real catalogue: two services are
     * Faker-generated test leftovers with no appointment attached (safe to
     * deactivate outright), and two more are the stand-ins an earlier task
     * used for "Corte caballero" and "Arreglo de barba" before this
     * catalogue existed — real appointments already reference them
     * (`appointment_services.service_id` is `restrictOnDelete`), so they
     * are renamed in place by their current name rather than replaced:
     * the row (and its id) survives, its duration changes to the real
     * catalogue's value, and the appointments that already booked it keep
     * their own frozen duration (AppointmentService::snapshotOf()) either
     * way — see ServiceCatalogSeederTest.
     *
     * Each lookup is by the name the rows have *before* this seeder has
     * ever run, so running it again is a no-op here (the old names no
     * longer exist) and never touches a service the salon later renames
     * to something else on purpose.
     */
    private const RENAMED_ON_FIRST_RUN = [
        'Corte de Pelo Hombre' => 'Corte caballero',
        'Corte/Arreglo barba' => 'Arreglo de barba',
    ];

    /**
     * Faker-generated test leftovers with no appointment, deactivated
     * rather than deleted (simplest, and nothing forces a delete here).
     */
    private const DEACTIVATED_TEST_LEFTOVERS = ['Facilis debitis', 'Iste rem'];

    /**
     * @var list<array{name: string, duration_minutes: int, sort_order: int, is_bookable_online: bool, is_active: bool}>
     */
    private const CATALOG = [
        // Color (familia 10-19)
        ['name' => 'Balayage', 'duration_minutes' => 120, 'sort_order' => 10, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Babylights', 'duration_minutes' => 120, 'sort_order' => 11, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Mechas clásicas', 'duration_minutes' => 120, 'sort_order' => 12, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Coloración completa', 'duration_minutes' => 120, 'sort_order' => 13, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Retoque de raíz', 'duration_minutes' => 30, 'sort_order' => 14, 'is_bookable_online' => true, 'is_active' => true],
        // A confirmar: la web la anuncia, no estaba en la lista de la peluquera.
        ['name' => 'Decoloración', 'duration_minutes' => 120, 'sort_order' => 15, 'is_bookable_online' => true, 'is_active' => false],

        // Corte (20-29)
        ['name' => 'Corte mujer', 'duration_minutes' => 45, 'sort_order' => 20, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Corte caballero', 'duration_minutes' => 45, 'sort_order' => 21, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Corte niños', 'duration_minutes' => 45, 'sort_order' => 22, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Arreglo de barba', 'duration_minutes' => 15, 'sort_order' => 23, 'is_bookable_online' => true, 'is_active' => true],

        // Peinado (30-39)
        // Solo por teléfono/WhatsApp: necesita prueba previa y acuerdo.
        ['name' => 'Peinado de boda', 'duration_minutes' => 150, 'sort_order' => 30, 'is_bookable_online' => false, 'is_active' => true],
        ['name' => 'Peinado de eventos', 'duration_minutes' => 60, 'sort_order' => 31, 'is_bookable_online' => true, 'is_active' => true],
        // A confirmar: mismo origen que "Peinado de eventos" ("Peinado normal" en la lista de la peluquera).
        ['name' => 'Recogidos', 'duration_minutes' => 60, 'sort_order' => 32, 'is_bookable_online' => true, 'is_active' => false],
        ['name' => 'Ondas y rizos', 'duration_minutes' => 90, 'sort_order' => 33, 'is_bookable_online' => true, 'is_active' => true],

        // Tratamientos (40-49)
        ['name' => 'Alisado permanente', 'duration_minutes' => 60, 'sort_order' => 40, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Keratina', 'duration_minutes' => 60, 'sort_order' => 41, 'is_bookable_online' => true, 'is_active' => true],
        ['name' => 'Hidratación', 'duration_minutes' => 60, 'sort_order' => 42, 'is_bookable_online' => true, 'is_active' => true],

        // Estética (50-59)
        ['name' => 'Diseño de cejas', 'duration_minutes' => 20, 'sort_order' => 50, 'is_bookable_online' => true, 'is_active' => true],
        // A confirmar: ¿cubre todo lo que la web llama "Depilación Facial"?
        ['name' => 'Depilación de labio', 'duration_minutes' => 20, 'sort_order' => 51, 'is_bookable_online' => true, 'is_active' => false],
        // A confirmar: la web la anuncia, no estaba en la lista de la peluquera.
        ['name' => 'Manicura', 'duration_minutes' => 45, 'sort_order' => 52, 'is_bookable_online' => true, 'is_active' => false],
        ['name' => 'Pedicura', 'duration_minutes' => 60, 'sort_order' => 53, 'is_bookable_online' => true, 'is_active' => true],
        // A confirmar, y solo por teléfono/WhatsApp: la web solo la menciona
        // como complemento del peinado de boda, nunca como servicio suelto.
        ['name' => 'Maquillaje', 'duration_minutes' => 60, 'sort_order' => 54, 'is_bookable_online' => false, 'is_active' => false],

        // Cabello afro y rizos (60-69): la web los anuncia (pie, JSON-LD)
        // pero no tienen página propia ni estaban en la lista de la peluquera.
        ['name' => 'Definición afro y rizos', 'duration_minutes' => 60, 'sort_order' => 60, 'is_bookable_online' => true, 'is_active' => false],
        ['name' => 'Trenzas protectoras', 'duration_minutes' => 90, 'sort_order' => 61, 'is_bookable_online' => true, 'is_active' => false],
    ];

    public function run(): void
    {
        foreach (self::RENAMED_ON_FIRST_RUN as $oldName => $newName) {
            // The main loop below only ever *creates* now (review finding
            // M1), so it can no longer be relied on to also fix up these
            // two rows' duration/order/flags the way it used to — this
            // applies the matching CATALOG entry's full values itself,
            // in the same update, reusing CATALOG instead of repeating
            // the numbers here (and risking the two drifting apart).
            $catalogEntry = collect(self::CATALOG)->firstWhere('name', $newName);

            Service::query()->where('name', $oldName)->update([
                'name' => $newName,
                'duration_minutes' => $catalogEntry['duration_minutes'],
                // A new length: any waits set for the old one may no
                // longer fit (review L5).
                'waits' => null,
                'price_cents' => null,
                'is_bookable_online' => $catalogEntry['is_bookable_online'],
                'is_active' => $catalogEntry['is_active'],
                'sort_order' => $catalogEntry['sort_order'],
            ]);
        }

        Service::query()->whereIn('name', self::DEACTIVATED_TEST_LEFTOVERS)->update(['is_active' => false]);

        foreach (self::CATALOG as $entry) {
            // firstOrCreate(), not updateOrCreate() (review finding M1):
            // a row the salon already edited from the panel (activated,
            // retimed, reordered) is left exactly as the salon left it —
            // only a name that does not exist yet gets these values.
            Service::firstOrCreate(
                ['name' => $entry['name']],
                [
                    'duration_minutes' => $entry['duration_minutes'],
                    'price_cents' => null,
                    'is_bookable_online' => $entry['is_bookable_online'],
                    'is_active' => $entry['is_active'],
                    'sort_order' => $entry['sort_order'],
                ]
            );
        }
    }
}
