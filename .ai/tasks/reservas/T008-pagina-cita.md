# T008 — Página de la cita y cancelación

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-039, PRF-040, PRF-041, PRF-042, PRF-043, PRF-044
- **Depende de:** T007
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** son una página y una acción acotadas.
- **Estado:** pending
- **PR / rama:** PR 3, `feature/booking-public`

## Plan
`AppointmentController` público:
- `show`: GET `/cita/{token}`.
- `cancel`: POST `/cita/{token}/cancelar`, con *throttle*.
- El token es de 48 caracteres de `Str::random`, guardado y único. Es un enlace personal no adivinable, sin `URL::signedRoute`, para que no dependa de `APP_KEY`.
- La vista `pages/cita.blade.php` lleva `noindex`.
- El plazo de cancelación se lee de `BookingSetting`.

## Criterios de aceptación
Los de PRF-039 a PRF-044.

## Plan de pruebas
`tests/Feature/Booking/CustomerAppointmentTest.php`.

## Verificación
`docker compose exec -T app php artisan test --compact` · Pint.

## Riesgos
Ninguno.
