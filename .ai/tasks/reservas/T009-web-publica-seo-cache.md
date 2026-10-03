# T009 — Caché, cabecera, sitemap y datos estructurados

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-038, PRF-056, PRF-057, PRF-058
- **Depende de:** T007, T008
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** son cambios localizados en piezas existentes.
- **Estado:** pending
- **PR / rama:** PR 3, `feature/booking-public`

## Plan
- `app/Http/Middleware/CacheHeaders.php`: `/reservas` y `/cita/` se sirven con `no-store, private`.
- `resources/views/partials/header.blade.php`: los dos CTA apuntan a `route('reservas')`.
- `SitemapController`: añade `reservas`.
- `partials/schema-local.blade.php`: `ReserveAction.urlTemplate` pasa a `route('reservas')`.

## Plan de pruebas
`tests/Feature/Booking/BookingPagesCachingTest.php`, `tests/Feature/HeaderBookingLinkTest.php`, la ampliación de `SitemapTest.php` y `tests/Feature/StructuredDataReserveActionTest.php`.

## Verificación
`docker compose exec -T app php artisan test --compact` · Pint.

## Riesgos
El resto de páginas siguen con su caché. Lo comprueba un test que verifica que la home mantiene `public`.
