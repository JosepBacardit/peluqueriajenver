# T014 — Resolver la revisión independiente

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-062, PRF-063, PRF-064, PRF-065, PRF-066, PRF-067, PRF-068, PRF-069, además de PRF-026, PRF-052 y PRF-055 (que se refuerzan)
- **Depende de:** T013
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** son correcciones de seguridad, privacidad y concurrencia sobre código ya revisado.
- **Estado:** done
- **PR / rama:** `feature/booking-review-fixes`, apilada sobre `feature/booking-notifications`

## Objetivo
Resolver los hallazgos de `.ai/reviews/reservas.md` con la skill `fix-review`. Quedan resueltos H1, M1–M3 y L1–L9. M4 queda pendiente de la decisión del usuario.

## Evidencia
Cada hallazgo indica en el registro de la revisión su resolución y el test que la demuestra. Tests nuevos o ampliados:
- `AppointmentPagePrivacyTest`
- `MailContentEscapingTest`
- `BookingAbuseLimitsTest`
- `AdminRoutesRequireAuthenticationTest`
- `CreateAppointmentTest` (bloqueo como primera consulta y doble cancelación)
- `AgendaTest` (aviso de correo no enviado)
- `CustomerAppointmentTest` (mensaje de *throttle*)
- `AdminAuthenticationTest` (límite por IP, hash con email inexistente y sin «recordarme»)
- `DeployCheckCommandTest` (cookie segura, `--smtp`, *timeout* y `check_env_mail` de `deploy.sh`)
- `PublicBookingTest` (consultas del calendario y responsable pendiente)

## Verificación
`docker compose exec -T app php artisan test --compact` · `docker compose exec -T app vendor/bin/pint --dirty --format agent` · `bash -n deploy.sh`.
