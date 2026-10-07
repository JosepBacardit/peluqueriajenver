# T060 — Reserva online disponible, no solo activa

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-147, PRF-148
- **Depende de:** T054 (interruptor), T056 (catálogo real de servicios)
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** revisión final de integración `.ai/reviews/pr-8-final.md`, hallazgo M1, recomendación 4. Con la casilla «Reserva online activa» encendida pero sin ningún servicio reservable online (catálogo vacío tras desplegar, o todos desactivados o solo por teléfono), `/reservas` mostraba el mensaje suelto «Ahora mismo no se pueden hacer reservas online» dentro del propio formulario, mientras los 9 «Reservar cita»/«Reservar online» de la web, la FAQ y el JSON-LD seguían anunciando una reserva online que no funciona de verdad.
- **Estado:** done
- **PR / rama:** `feature/agenda-service-filter` (PR #8)

## Decisión del usuario (2026-10-07)

- El texto de los dos avisos nuevos del panel: «Reserva online activa, pero no hay ningún servicio reservable online» + enlace a Servicios, tanto en la agenda como en Ajustes.
- Actualizar `AGENTS.md` y `.ai/specs/reservas.md` en este mismo cambio.
- Borrar la clave `reservas.no_services` y la rama `@if ($services->isEmpty())`, ya inalcanzable.

## Implementación

- `BookingSetting::onlineBookingAvailable()` (nuevo, `app/Models/BookingSetting.php`): `onlineBookingEnabled() && Service::query()->bookableOnline()->exists()`, con corta-circuito (no paga la consulta de servicios si la casilla ya está apagada). `onlineBookingEnabled()` no cambia: sigue siendo el valor crudo de la casilla, usado donde hace falta (agenda, Ajustes, los tests del propio interruptor).
- Los 9 sitios públicos que antes preguntaban `onlineBookingEnabled()` para decidir si anuncian la reserva online pasan a `onlineBookingAvailable()`: `header.blade.php` (x2), `home.blade.php` (x2), las 4 páginas de servicio, `schema-faq.blade.php`, `schema-local.blade.php`; además `BookingController::index()` y `StoreBookingRequest::authorize()`.
- `BookingController::index()` ya no puede llegar a `pages.reservas` con `$services` (reservable online) vacío, así que el `@if ($services->isEmpty())` de `pages/reservas.blade.php` y la clave `reservas.no_services` quedan retirados.
- `AgendaController::index()`: añade `'hasBookableOnlineService' => $services->contains('is_bookable_online', true)` reutilizando la colección de servicios activos que ya cargaba para el filtro (PRF-120) — cero consultas nuevas. `admin/agenda/index.blade.php` añade un segundo aviso, distinto del de PRF-140, para «casilla encendida, sin servicio reservable online».
- `BookingSettingsController::edit()`: añade `'hasBookableOnlineService' => Service::query()->bookableOnline()->exists()` (una consulta nueva, página de panel sin presupuesto fijo de consultas). `admin/settings/edit.blade.php` muestra el mismo aviso junto a la casilla.

## Plan de pruebas

`tests/Feature/Booking/OnlineBookingToggleTest.php`: duplica cada test de «casilla apagada» para el caso «casilla encendida, sin ningún servicio reservable online» (helper `removeBookableServices()`), más los dos avisos nuevos del panel (agenda y Ajustes). `tests/Feature/Booking/PublicBookingTest.php`: el test «without online services the page invites to call or use whatsapp» pasa a esperar la página completa `reservas-disabled`, no el mensaje suelto.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `vendor/bin/pint --dirty --format agent`.

## Fuera de alcance

Memorizar `onlineBookingAvailable()` por petición para ahorrar las consultas adicionales en cada uno de los 9 sitios públicos: el coste es una `exists()` indexada, misma familia ya aceptada en PRF-109/PRF-118 (L3 de `.ai/reviews/opening-hours-ux.md`); no se ha medido ningún problema real de rendimiento que lo justifique.
