# T015 — Remitente de producción y comprobación de despliegue

- **Tipo:** SIMPLE
- **Puntos de referencia:** PRF-070, PRF-071
- **Depende de:** T011 (`DeployCheckCommand` ya existe)
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `low`
- **Motivo:** cambio de configuración y una comprobación adicional en un comando ya existente; sin ambigüedad ni riesgo de concurrencia.
- **Estado:** done
- **PR / rama:** `feature/booking-admin-tweaks`, apilada sobre `feature/booking-review-fixes`

## Contexto

`.env.example:1` tiene `APP_NAME=Laravel`. `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` (líneas 34-38) ya apuntan a `hello@example.com`/`${APP_NAME}`. `App\Console\Commands\DeployCheckCommand::checkMail()` ya falla si `MAIL_FROM_ADDRESS` es `hello@example.com`, pero no comprueba `APP_NAME`. El valor real del buzón de envío sigue sin decidir (pregunta abierta de la especificación); aquí solo se documenta un valor provisional.

## Plan

1. `.env.example`: `APP_NAME="Peluquería Jenver"`, `MAIL_FROM_ADDRESS="reservas@peluqueriajenver.com"` (provisional, comentario indicando que es provisional hasta decidir el SMTP real).
2. `DeployCheckCommand::checkMail()`: añadir una comprobación de `config('app.name') !== 'Laravel'`, con el mismo patrón de `$this->error(...)`/`$ok = false` que las demás.
3. `AGENTS.md`, sección «Production deploys»: documentar la nueva comprobación en la lista de lo que falla `deploy:check`.

## Plan de pruebas

`tests/Feature/Booking/DeployCheckCommandTest.php`: nuevo caso que fija `APP_NAME=Laravel` (con el resto de la configuración correcta) y espera que el comando falle con un mensaje que lo mencione; otro que confirma que pasa con un nombre distinto.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent`.

## Riesgos

Ninguno: es un cambio de configuración y una comprobación adicional, sin tocar el flujo de reservas.
