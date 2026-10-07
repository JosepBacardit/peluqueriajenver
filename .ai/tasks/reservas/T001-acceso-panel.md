# T001 — Acceso al panel, base regional y layout del admin

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-001, PRF-002, PRF-003, PRF-004, PRF-005, PRF-006, PRF-007, PRF-008, PRF-009
- **Depende de:** —
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** son los cimientos de autenticación y de la estructura del panel, de los que dependen todas las demás tareas.
- **Estado:** done
- **PR / rama:** PR 1, `feature/booking-admin-foundation`

## Objetivo
Que solo las personas del salón con una cuenta creada desde la consola puedan entrar en un panel con un menú común preparado para crecer, y que toda la aplicación trabaje en español y en hora de Madrid.

## Contexto
- `config/app.php` tiene `timezone => 'UTC'` y `locale => env('APP_LOCALE', 'en')`. No existe `lang/es/validation.php`.
- `app/Models/User.php` y `UserFactory` existen sin usarse. La tabla `users` la crea la migración por defecto.
- `bootstrap/app.php` no configura redirecciones de `auth` ni de `guest`.
- `CacheHeaders` ya excluye de la caché las rutas que empiezan por `/admin`.

## Plan
1. `config/app.php`: `timezone => 'Europe/Madrid'` y `locale => 'es'`, fijados en el código porque la web solo existe en español (documentado en `AGENTS.md`). `.env.example`: `APP_LOCALE=es`.
2. Crear `lang/es/validation.php` con los mensajes de las reglas que se usan y los `attributes` de los campos.
3. Rutas `/admin/login` (GET y POST, con nombre `login`) y `/admin/logout` (POST). `Admin\LoginController` con `Auth::attempt`, `RateLimiter` con la clave email + IP y 5 intentos por minuto, y `session()->regenerate()`.
4. `bootstrap/app.php`: `redirectGuestsTo` a `/admin/login` y `redirectUsersTo` a la agenda.
5. `resources/views/layouts/admin.blade.php`: el menú sale de un único array de módulos, de modo que añadir un módulo es añadir una entrada. Incluye el botón de cerrar sesión y `noindex`.
6. Comando `admin:create-user` (`App\Console\Commands\CreateAdminUserCommand`), interactivo: nombre, email y contraseña con `secret()` dos veces, mínimo 12 caracteres. Si el email ya existe, ofrece cambiar la contraseña.
7. `/admin` redirige a la agenda. Mientras no exista la agenda (T006), se usa una pantalla de inicio provisional.

## Fuera de alcance
Los módulos en sí (T002–T006).

## Criterios de aceptación
- Sin sesión, `/admin` y cualquier ruta del panel redirigen al login (PRF-001).
- Un login válido entra en el panel (PRF-002). Uno inválido muestra el mensaje genérico (PRF-003). El 6.º intento queda bloqueado (PRF-004).
- No existe ninguna ruta de registro (PRF-005).
- El comando crea la cuenta, rechaza contraseñas cortas o que no coinciden, y cambia la contraseña de un email existente solo si se confirma (PRF-006).
- No hay ningún seeder con credenciales (PRF-007).
- Cerrar sesión invalida la sesión (PRF-008).
- El menú aparece en cada pantalla del panel (PRF-009).

## Plan de pruebas
- `tests/Feature/Admin/AdminAuthenticationTest.php`: redirección sin sesión, login correcto, login incorrecto, *throttle*, logout y que no hay ruta de registro.
- `tests/Feature/Admin/CreateAdminUserCommandTest.php`: alta, contraseña corta, no coinciden, email existente con «sí» y con «no».
- `tests/Feature/Admin/AdminLayoutTest.php`: el menú de módulos se ve en cada pantalla (se amplía en cada tarea).
- PRF-007: verificación con `grep` del `DatabaseSeeder`.

## Verificación
`docker compose exec -T app php artisan test --compact` · `docker compose exec -T app vendor/bin/pint --dirty --format agent`

## Riesgos
Cambiar la zona horaria afecta a `date()` en el sitemap (`lastmod`), que es aceptable. Las páginas públicas no deben cambiar: lo demuestra la suite existente.
