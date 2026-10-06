# T058 — «Mi cuenta»: cambiar la propia contraseña desde el panel

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-145, PRF-146
- **Depende de:** ninguna
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `high`
- **Motivo:** con cuentas de administración ya en uso (T057), el panel necesita una forma de cambiar su contraseña sin pasar por el servidor.
- **Estado:** done
- **PR / rama:** `feature/opening-hours-ux`, desde `feature/agenda-service-filter` (`1ce486e`)

## Decisiones del usuario (2026-10-06)

- `GET /admin/cuenta` y `PUT /admin/cuenta/password`.
- Contraseña actual, nueva y confirmación.
- Longitud mínima en `User::MIN_PASSWORD_LENGTH` (12, el mismo valor que ya usaba `admin:create-user`, movido a una sola constante compartida).
- *Rate limiter* `password-change`, por usuario (no por IP: ya está autenticado).
- `auth.session` en el grupo del panel y `Auth::logoutOtherDevices()` al cambiarla.
- Mensaje de éxito con `session('status')` (el mismo patrón que ya usa el resto del panel).
- Enlace desde el nombre del usuario en escritorio y desde el menú hamburguesa, antes de «Cerrar sesión».
- Objetivos de 44 px y accesible.
- **No** se puede cambiar la contraseña de otro usuario: todas las cuentas tienen los mismos permisos, así que dejarlo abriría una vía de suplantación entre ellas sin ningún rastro de quién lo hizo. La recuperación de una cuenta olvidada sigue siendo `php artisan admin:create-user` desde el servidor (no depende de que el correo SMTP esté configurado, al contrario que un «olvidé mi contraseña» por email).

## Implementación

- `User::MIN_PASSWORD_LENGTH = 12` (antes, una constante privada solo de `CreateAdminUserCommand`, que ahora la referencia en su lugar).
- `App\Http\Requests\Admin\UpdatePasswordRequest`: `current_password` (la regla propia de Laravel, comprobada contra el guard autenticado, nunca un `Hash::check()` hecho a mano) y `password` (`min:`.User::MIN_PASSWORD_LENGTH, `confirmed`).
- `App\Http\Controllers\Admin\AccountController`: `edit()` muestra la vista; `updatePassword()` guarda la contraseña nueva y llama a `Auth::logoutOtherDevices($password)`.
- `routes/web.php`: el grupo `Route::middleware('auth')->name('admin.')` pasa a `['auth', 'auth.session']` (alias ya incluido en el framework, `Illuminate\Session\Middleware\AuthenticateSession` — no hace falta registrarlo). Nuevas rutas `admin.account.edit` y `admin.account.update-password`, esta última con `throttle:password-change`.
- `App\Providers\AppServiceProvider`: *rate limiter* `password-change`, `Limit::perMinutes(10, 5)` por `user.id`, con su propio mensaje.
- `resources/views/admin/account/edit.blade.php` (nueva) y `resources/views/layouts/admin.blade.php`: el nombre del usuario en escritorio pasa de texto suelto a enlace a «Mi cuenta»; el menú hamburguesa añade «Mi cuenta (Nombre)» justo antes de «Cerrar sesión», con el borde superior que antes llevaba el formulario de salir.

### Cómo funciona `auth.session` + `logoutOtherDevices()`

`AuthenticateSession` guarda, en la sesión de cada dispositivo, el hash de la contraseña que tenía el usuario la última vez que esa sesión hizo una petición; en cada petición nueva, compara ese hash guardado con el hash **actual** del usuario en la base de datos, y si no coinciden, cierra esa sesión. Cambiar la contraseña ya cambia ese hash actual por sí solo; `Auth::logoutOtherDevices()` solo lo deja explícito y fuerza un nuevo *hash* (mismo valor, nueva sal). La sesión que hizo el cambio se re-sincroniza sola justo después de su propia respuesta (el middleware guarda el hash nuevo al salir), así que nunca se desconecta a sí misma; cualquier otra sesión con el hash antiguo se desconecta en su primera petición siguiente. Probado end to end sin necesitar dos navegadores reales: un test inyecta el hash antiguo en una sesión de prueba con `withSession()` y comprueba que esa sesión recibe una redirección a `/login` en su siguiente petición, mientras que la sesión que hizo el cambio sigue dentro.

## Plan de pruebas

`tests/Feature/Admin/AccountManagementTest.php` (nuevo, 9 tests): requiere sesión; muestra el nombre y el email; cambia la contraseña con los datos correctos; rechaza la contraseña actual incorrecta sin cambiar nada; rechaza una nueva demasiado corta; rechaza una confirmación que no coincide; bloquea tras demasiados intentos (por usuario, con su propio mensaje); cierra una sesión que siga con el *hash* antiguo en su siguiente petición; y la sesión que hizo el cambio sigue dentro justo después.

`tests/Feature/Admin/CreateAdminUserCommandTest.php`: sin cambios de comportamiento (el texto «at least 12 characters» sigue igual al moverse la constante a `User`).

`tests/Feature/Admin/AdminLayoutTest.php`: el test de la navegación del panel se actualiza para el nuevo enlace «Mi cuenta» antes de «Cerrar sesión» en el menú móvil.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa, incluida toda `tests/Feature/Admin/` para confirmar que `auth.session` no rompe ninguna sesión de prueba existente) · `vendor/bin/pint --test` sobre los archivos tocados · `npm run build`.

## Fuera de alcance

Cambiar la contraseña de otro usuario (decisión del usuario: no se hace); recuperación por email (exigiría SMTP en producción).
