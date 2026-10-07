# T053 — La web pública lee el horario del panel

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-135, PRF-136
- **Depende de:** T052 (mismo modelo `OpeningHour`, sin cambios de esquema)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** el horario estaba escrito a mano en cuatro sitios (`lang/es/home.php`, `lang/es/navigation.php`, `contacto.blade.php`, `schema-local.blade.php`) y no reflejaba lo que se configuraba en `/admin/horario`.
- **Estado:** done
- **PR / rama:** `feature/opening-hours-ux`, desde `feature/agenda-service-filter` (`1ce486e`)

## Objetivo

Que el panel sea la única fuente del horario: la web pública (pie, portada, pregunta frecuente, `meta_description` de contacto, JSON-LD) lo lee de `OpeningHour`, nunca de un texto suelto.

## Implementación

`App\Booking\OpeningHoursSummary` (nueva clase, junto a `ServiceList`): una consulta (`OpeningHour::query()->orderBy('weekday')->orderBy('opens_at')->get()`), memorizada en una propiedad `private static`, suficiente como caché «por petición» porque PHP-FPM ejecuta un proceso por petición.

- `text()`: una frase agrupando días consecutivos con el mismo horario — p. ej. «Martes a viernes: 9:00–14:00 y 16:00–20:00 · Sábado: 9:00–14:00 · Domingo y lunes: cerrado». El agrupado es un recorrido lineal de lunes a domingo que funde días consecutivos idénticos, más un ajuste para el caso circular: si el primer grupo (empieza en lunes) y el último (termina en domingo) comparten el mismo horario, se funden en uno y ese grupo fundido se mueve al final de la lista (si no, «Domingo y lunes» saldría roto en dos, con el lunes pegado al principio).
- `schemaSpecifications()`: una entrada `OpeningHoursSpecification` por grupo y tramo; un grupo cerrado se omite entero — es lo que recomienda el propio schema.org, en vez del `"00:00"`-`"00:00"` que tenía el código anterior.
- `forgetCachedSchedule()`: limpia la caché estática; la llama `OpeningHoursController::update()` tras guardar, para que la misma petición (sobre todo en los tests) no siga viendo el horario de antes de guardar.

Se quitó el texto escrito a mano de:

- `lang/es/home.php`: `reserva.description` y `faq.online_answer` llevan ahora un marcador `:hours`, rellenado desde la vista con `OpeningHoursSummary::text()`; se quitó la clave `contacto.hours`.
- `lang/es/navigation.php`: se quitó `footer.contact.hours`.
- `resources/views/pages/home.blade.php`, `resources/views/pages/contacto.blade.php`, `resources/views/partials/footer.blade.php`: llaman a `OpeningHoursSummary::text()` directamente donde antes leían la clave quitada.
- `resources/views/pages/contacto.blade.php`: la `meta_description` del `@section` concatena `OpeningHoursSummary::text()`.
- `resources/views/partials/schema-faq.blade.php`: pasa `['hours' => OpeningHoursSummary::text()]` a `__('home.faq.online_answer', ...)`.
- `resources/views/partials/schema-local.blade.php`: `openingHoursSpecification` pasa a ser `OpeningHoursSummary::schemaSpecifications()` en vez del array fijo.

Un `grep -rn "9:00\|19:00\|Mar-Sáb\|martes a sábado"` sobre `resources/` y `lang/` confirmó que no quedaba ningún otro sitio con el horario escrito a mano.

### Caché HTTP (sin cambiarla: propuesta para el coordinador)

`CacheHeaders` pone `max-age` de 24 h a `/` y de 7 días a las demás páginas públicas (incluida `/contacto`), con `must-revalidate`. `must-revalidate` solo entra en juego **después** de que la copia en caché caduque; dentro de la ventana de `max-age` el navegador no pregunta nada al servidor. Así que, con la política actual, un cambio de horario en el panel tardaría hasta 24 h (`/`) o hasta 7 días (`/contacto` y el resto) en verse en un navegador que ya tuviera la página cacheada — el ETag/Last-Modified no acorta esa ventana, solo evita volver a descargar contenido sin cambios una vez que el navegador ya está revalidando por caducidad. Opciones, sin aplicar ninguna:

1. **No cambiar nada.** El horario cambia pocas veces; aceptar hasta 7 días de desfase en navegadores con caché fría es barato y no toca código de caché.
2. **Bajar el `max-age` de las páginas con horario** (`/`, `/contacto`, y cualquier otra que lo muestre) a algo como 1 hora, mantenerlo alto en el resto. Reduce el desfase a costa de más peticiones al servidor en esas páginas concretas.
3. **Invalidación activa:** que `OpeningHoursController::update()` dispare una purga de caché (CDN o `Cache-Control` dinámico) de esas páginas al guardar. Más preciso, pero añade una dependencia operativa (purga de CDN o un mecanismo de versión en la URL) que no existe hoy.

## Plan de pruebas

`tests/Feature/Booking/OpeningHoursSummaryTest.php` (nuevo): el horario de fábrica se agrupa como «Martes a sábado: 9:00–19:00 · Domingo y lunes: cerrado»; el JSON-LD de fábrica tiene una sola entrada y ningún tramo para los días cerrados; un horario partido («... y ...»); toda la semana cerrada («Lunes a domingo: cerrado»); toda la semana con el mismo horario; `text()` + `schemaSpecifications()` juntos hacen una sola consulta (`DB::enableQueryLog()`); `forgetCachedSchedule()` fuerza una consulta nueva.

`tests/Feature/PublicScheduleTest.php` (nuevo): la portada (pie, «Reserva tu cita», «Contacto») y `/contacto` (incluida la `meta_description`) muestran el horario configurado, no el de fábrica ni el texto anterior; la pregunta frecuente de reserva online lo recoge en su JSON-LD; el `openingHoursSpecification` del esquema local refleja el horario configurado y omite los días cerrados sin ningún `00:00`.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `vendor/bin/pint --test` sobre los archivos tocados · `npm run build`. Sin datos en MySQL: la base siguió con el horario de fábrica (Tue-Sat 9-19) en todo momento fuera de los tests, que usan `RefreshDatabase`.

## Fuera de alcance

Cambiar la política de `CacheHeaders` (decisión pendiente del coordinador, ver arriba).
