# T012 — Política de privacidad

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-059, PRF-060
- **Depende de:** T007
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** es un texto legal sin lógica, pero sin datos inventados.
- **Estado:** done
- **PR / rama:** PR 4, `feature/booking-notifications`

## Plan
Reestructurar `resources/views/pages/privacidad.blade.php` con estos apartados: responsable, datos que se tratan (reservas), finalidad, base legal, encargados (alojamiento en un servidor de OVH y proveedor de correo pendiente), conservación, derechos y reclamación ante la AEPD. Los valores no confirmados se marcan con «[Pendiente de confirmar: …]». Se quita `peluqueriajenver@email.com`.

## Plan de pruebas
`tests/Feature/PrivacyPolicyTest.php`: secciones presentes, marcas de pendiente y ausencia del email inventado en todas las páginas públicas.

## Verificación
`docker compose exec -T app php artisan test --compact`.

## Riesgos
La página no se puede publicar con las marcas pendientes. Bloquea la publicación (spec, preguntas abiertas).
