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
 * real catalogue, not test data, and `updateOrCreate()` by name makes it
 * safe to run again after an edit here (DatabaseSeeder calls it always).
 *
 * `price_cents` is left `null` everywhere: the salon sets real prices from
 * the panel; `Service::$fillable` and its migration both already allow
 * that (`price_cents` is nullable). `deploy.sh` never calls `db:seed`
 * (only `migrate --force`), so this only runs when someone deliberately
 * types it; still, running it again *after* the salon has set real
 * prices from the panel would reset every catalogue row's price back to
 * `null` (name, duration and the other flags here are meant to be the
 * long-term source of truth and safe to reapply, but a price is not).
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
            Service::query()->where('name', $oldName)->update(['name' => $newName]);
        }

        Service::query()->whereIn('name', self::DEACTIVATED_TEST_LEFTOVERS)->update(['is_active' => false]);

        foreach (self::CATALOG as $entry) {
            Service::updateOrCreate(
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
