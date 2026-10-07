# T057 — Cuentas de administración pedidas por el usuario

- **Tipo:** SIMPLE
- **Puntos de referencia:** PRF-144
- **Depende de:** ninguna
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** el usuario pidió crear dos cuentas concretas del panel.
- **Estado:** done
- **PR / rama:** `feature/opening-hours-ux`, desde `feature/agenda-service-filter` (`1ce486e`)

## Decisión del usuario (2026-10-06)

`peluqueriajenver@gmail.com` y `josep@cobaprojects.com`, las dos con la contraseña `password`, idempotente (`updateOrCreate` por email). Sin restricciones de entorno ni comprobación en `deploy:check` (decisión explícita del usuario, que sustituye la propuesta más cautelosa de la fase 1 de este mismo informe). **Nunca** en `DatabaseSeeder`: solo a mano, con `php artisan db:seed --class=AdminUsersSeeder`.

## Implementación

`database/seeders/AdminUsersSeeder.php`: recorre las dos cuentas y hace `User::updateOrCreate(['email' => ...], ['name' => ..., 'password' => 'password'])`. No se registra en `database/seeders/DatabaseSeeder.php` (que sigue sin crear ninguna cuenta, solo el catálogo de servicios de T056). Documentado en `AGENTS.md` junto a la nota ya existente sobre `admin:create-user`.

Ejecutado en local al cerrar la tarea (`-u www-data`): ver el listado final de usuarios en el informe de cierre.

## Plan de pruebas

`tests/Feature/AdminUsersSeederTest.php` (nuevo): crea las dos cuentas con la contraseña pedida; es idempotente (ejecutarlo dos veces no duplica nada); y, sobre todo, que **nunca** se ejecuta como parte de `php artisan db:seed` normal (mismo `$this->seed()` que ya usaba `CreateAdminUserCommandTest`, ahora comprobando también que sigue sin crear ninguna cuenta tras añadir T056).

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `vendor/bin/pint --test` sobre los archivos tocados.

## Fuera de alcance

El catálogo de servicios (T056), «Mi cuenta» (T058, que es la vía normal para cambiar una contraseña propia después de crear estas cuentas).
