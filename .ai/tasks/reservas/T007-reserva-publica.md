# T007 — Reserva pública

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-014 (página), PRF-016 (parte pública), PRF-026, PRF-027, PRF-028, PRF-029, PRF-030, PRF-031, PRF-032, PRF-033, PRF-034, PRF-035, PRF-036, PRF-037, PRF-061
- **Depende de:** T004
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `high`
- **Motivo:** es el flujo público con muchos casos límite, aunque la lógica de fondo ya está en T004.
- **Estado:** done
- **PR / rama:** PR 3, `feature/booking-public`

## Plan
1. `BookingController`:
   - `index`: GET `/reservas` con `?servicio=&mes=&fecha=`. Muestra los pasos: servicios, calendario y horas con el formulario.
   - `store`: POST `/reservas`.
2. `StoreBookingRequest`:
   - campos y regla de teléfono `regex:/^\+?[0-9\s\-()]{9,20}$/`, con 9–15 dígitos;
   - casilla `privacy` (`accepted`);
   - honeypot `website`.
3. *Rate limiter* `bookings`: 5 por minuto por IP, definido en `AppServiceProvider`.
4. Textos en `lang/es/reservas.php`. Vistas `pages/reservas.blade.php` y los *partials* del calendario y de la información básica de privacidad.
5. Duplicado (mismo email y misma hora confirmada) → mensaje.
6. Honeypot → redirección a la página de reservas con un mensaje de éxito genérico, sin cita.

## Criterios de aceptación
Los de PRF-027 a PRF-037, además de los siguientes:
- la página no tiene «€» (se añade `reservas` al *dataset* de `PublicPagesHaveNoPublicPricingTest`) (PRF-014, PRF-061);
- los servicios no reservables no salen (PRF-016);
- una hora ocupada después de mostrar el formulario se rechaza (PRF-026).

## Plan de pruebas
`tests/Feature/Booking/PublicBookingTest.php` (listado, calendario, horas, alta, validaciones, manipulación, ocupada, duplicado, honeypot y *throttle*) y la ampliación del *dataset* de precios.

## Verificación
`docker compose exec -T app php artisan test --compact` · Pint · `docker compose exec node npm run build` y visita manual de `http://localhost:8082/reservas`.

## Riesgos
Las páginas públicas existentes no cambian.
