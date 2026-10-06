# T056 — Catálogo real de servicios

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-143
- **Depende de:** ninguna
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** cargar el catálogo real que dio la peluquera, cruzado con lo que la web ya anuncia (fase 1 de esta tarea: exploración de las 4 páginas de servicio, `lang/es/servicios.php`, `lang/es/home.php` y el JSON-LD, sin tocar nada).
- **Estado:** done
- **PR / rama:** `feature/opening-hours-ux`, desde `feature/agenda-service-filter` (`1ce486e`)

## Decisiones del usuario (2026-10-06)

- «Corte de chico» se elimina del catálogo propuesto en la fase 1: se considera el mismo servicio que «Corte caballero».
- «Arreglo de barba» (de «Arreglo de barba y corte de chico», partida en dos en la fase 1) dura 15 minutos, no 30.
- Los servicios «a confirmar» (Decoloración, Recogidos, Depilación de labio, Manicura, Maquillaje, Definición afro y rizos, Trenzas protectoras) se crean con `is_active = false`.
- «Peinado de boda» y «Maquillaje», con `is_bookable_online = false`.
- `sort_order` por familias (Color 10-19, Corte 20-29, Peinado 30-39, Tratamientos 40-49, Estética 50-59, Afro y rizos 60-69).
- Precio vacío (`price_cents = null`; el modelo y la migración ya lo admiten).
- En local: desactivar «Facilis debitis» e «Iste rem» (datos de prueba, sin ninguna cita); renombrar «Corte de Pelo Hombre» a «Corte caballero» (45 min) y «Corte/Arreglo barba» a «Arreglo de barba» (15 min), localizándolos por su nombre actual antes del `updateOrCreate` normal, para no dejar huérfanas las citas reales que ya los referencian (`appointment_services.service_id`, `restrictOnDelete`).
- Idempotente (por nombre).
- `DatabaseSeeder` llama siempre al seeder de servicios, en todos los entornos.

## Implementación

`database/seeders/ServiceCatalogSeeder.php`: primero renombra en el sitio (por nombre actual) las dos filas de prueba con citas reales y desactiva las dos sin ninguna; después recorre los 24 servicios del catálogo con `Service::updateOrCreate(['name' => ...], [...])`. `database/seeders/DatabaseSeeder.php` lo llama siempre (`$this->call(ServiceCatalogSeeder::class)`); las cuentas de administración siguen sin sembrarse ahí (T057).

Cambiar la duración de un servicio (al renombrarlo o después, desde el panel) nunca toca una cita que ya lo hubiera reservado: `appointment_services` copia nombre, duración y precio en el momento de la reserva (`AppointmentService::snapshotOf()`, PRF-126), no los vuelve a leer del servicio.

## Plan de pruebas

`tests/Feature/ServiceCatalogSeederTest.php` (nuevo, 10 tests): los 24 servicios con su familia/duración/sin precio; que «corte de chico» no existe; que «Arreglo de barba» queda en 15 minutos (un solo servicio, no dos de 30); «Peinado de boda» y «Maquillaje» no reservables online; los 7 «a confirmar» desactivados; los dos datos de prueba sin cita desactivados; «Corte de Pelo Hombre»/«Corte/Arreglo barba» renombrados en el sitio (mismo id) sin tocar la cita que ya los tenía reservados a su duración antigua; ejecutar el *seeder* dos veces no duplica nada; y, específicamente pedido por el coordinador, que **cambiar la duración de un servicio nunca altera una cita que ya lo había reservado**.

`tests/Feature/Admin/CreateAdminUserCommandTest.php`: el test «the database seeder never creates admin accounts» se extiende para comprobar también que sí siembra los 24 servicios.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `vendor/bin/pint --test` sobre los archivos tocados · `npm run build`. Ejecutado en local (`-u www-data`): ver el catálogo final en el informe de cierre.

## Fuera de alcance

Las cuentas de administración (T057), «Mi cuenta» (T058).
