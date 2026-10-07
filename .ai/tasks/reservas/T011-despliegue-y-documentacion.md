# T011 — Comprobación de despliegue y documentación

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-055
- **Depende de:** T010
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** amplía un comando que ya existe y actualiza la documentación.
- **Estado:** done
- **PR / rama:** PR 4, `feature/booking-notifications`

## Plan
- `DeployCheckCommand` falla en estos casos:
  - `mail.default` está en `log`, `array` o vacío;
  - `mail.from.address` está vacío o es `hello@example.com`;
  - con `smtp`, `mail.mailers.smtp.host` está vacío o es `127.0.0.1`;
  - `booking.salon_notification_email` no es un email válido.
- `deploy.sh`: añade `/reservas` a las comprobaciones de `curl`.
- `AGENTS.md`: proyecto con reservas, panel, cuentas con `admin:create-user`, cron de `appointments:notify-pending` con `flock`, variables del `.env` y los requisitos pendientes antes de publicar.
- `.env.example`: `BOOKING_NOTIFICATION_EMAIL=`.

## Plan de pruebas
Ampliar `tests/Feature/DeployCheckCommandTest.php`.

## Verificación
`docker compose exec -T app php artisan test --compact` · Pint · `bash -n deploy.sh`.

## Riesgos
Con estos cambios, el primer despliegue fallará en `deploy:check` (dejando la web en mantenimiento) si el `.env` no tiene el correo configurado. Se documenta como requisito previo en `AGENTS.md`.
