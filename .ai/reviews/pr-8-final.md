# Revisión final de integración: PR #8 (`feature/agenda-service-filter` → `main`)

- **Alcance:** `git diff origin/main...HEAD` con HEAD en `0e4d707` (68 commits, 274 archivos, unas 21 000 líneas). Incluye la fusión de la PR #7 (consentimiento de cookies). Se ha contrastado con `AGENTS.md`, `.ai/specs/reservas.md`, las revisiones por rama de `.ai/reviews/` y `developer-brain/syntheses/lecciones-revisiones.md`.
- **Skill:** `review`.
- **Fecha:** 2026-10-07.
- **Revisor:** Claude Opus 5.5, en un contexto limpio. No ha participado en la implementación.
- **Objetivo:** revisar la integración de toda la PR antes de fusionar y desplegar. No se repiten los hallazgos de las revisiones por rama, que ya están resueltos.
- **`.ai/rules/`:** no existe en este repositorio, así que no hay reglas de Laravel Boost que aplicar.

## Tests

**No se han podido ejecutar.** Docker no está levantado: `docker compose ps` devuelve `failed to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine ... The system cannot find the file specified`. Desde el host tampoco se pueden ejecutar, porque no hay `vendor/` (vive en un volumen de Docker) ni `pdo_sqlite`.

Antes de fusionar, el coordinador debe ejecutar:

```
docker compose up -d
docker compose exec -u www-data app php artisan test --compact
```

La última cifra registrada es de la revisión `seeders-account`: 706 tests pasados. Este informe no la ha reproducido.

**Sin comprobar en el navegador:** no hay navegador ni entorno levantado. Tampoco se ha comprobado nada contra el VPS.

## Resumen

| Severidad | Nº |
| --- | --- |
| Alta | 0 |
| Media | 1 |
| Baja | 8 |

- **M1.** El despliegue no siembra el catálogo de servicios. Con la configuración por defecto, cada «Reservar cita» de la web llevaría a «Ahora mismo no se pueden hacer reservas online», y el JSON-LD y la FAQ anunciarían una reserva online que no funciona.
- **L1.** Las URL de los correos se construyen con la cabecera `Host` de la petición: no hay `trustHosts()` ni `forceRootUrl()`.
- **L2.** Al cambiar el email de una cita, el cron puede enviar la confirmación por duplicado.
- **L3.** La comprobación previa de `deploy.sh` solo cubre el correo. Si falta `SESSION_SECURE_COOKIE` (que tampoco está en `.env.example`), el despliegue falla con la web ya en mantenimiento.
- **L4.** La política de privacidad promete borrar los datos a los 2 años, pero no hay ningún mecanismo que lo haga.
- **L5.** `AdminUsersSeeder` crea dos cuentas reales con la contraseña `password` en un repositorio público, y nada lo detecta en producción.
- **L6.** El tope diario de 10 reservas por IP cuenta también los envíos fallidos.
- **L7.** Dos comentarios de tests siguen diciendo que el HTML se cachea «durante días».
- **L8.** Las notas de «Before the first real deploy» de `AGENTS.md` contradicen `vps-ovh.md` en la ruta del VPS y en el acceso a GitHub (fuera del diff).

**Recomendación:** se puede fusionar cuando la suite esté en verde. Antes de desplegar hay que resolver M1, o al menos documentar el paso que falta. Los hallazgos bajos no bloquean la fusión, salvo L3, que conviene resolver antes del primer despliegue.

---

## Media

### M1. El despliegue no siembra el catálogo de servicios, y la web pública anuncia una reserva online que no funciona

- **Estado:** abierto.
- **Evidencia:**
  - `deploy.sh:219-228` ejecuta `migrate --force`, `optimize` y `deploy:check`, pero no ejecuta `db:seed` en ningún momento (`grep -n seed deploy.sh` no devuelve nada).
  - `composer.json` tampoco siembra.
  - Las migraciones crean el horario y los ajustes (`2026_10_03_140000_*` y `2026_10_03_140100_*`, con `online_booking_enabled = true`), pero no crean servicios.
  - La documentación da por hecho lo contrario:
    - `.ai/specs/reservas.md:322` dice: «Al desplegar se crean el horario inicial…, los ajustes iniciales… y… el catálogo real de servicios (PRF-143), sembrado siempre».
    - `AGENTS.md:112-114` dice: «called from `DatabaseSeeder` in every environment, including production».
    - La lista de requisitos para desplegar (`AGENTS.md:442-447`) solo dice «`php artisan db:seed` (if ever run) seeds the real service catalogue». En producción, además, `db:seed` necesita `--force`.
- **Impacto:** después del primer `./deploy.sh`, `services` está vacía y el interruptor de reserva online está activado. Por eso:
  - Todos los CTA «Reservar cita →» enlazan a `/reservas`, que muestra `reservas.no_services`. Son los del header (escritorio y móvil, `partials/header.blade.php`), el *hero* de la portada y las 4 páginas de servicio. Hasta ahora llevaban a `tel:`, así que es una regresión de conversión en la web de marketing.
  - El JSON-LD sigue publicando `potentialAction` → `ReserveAction` hacia `/reservas` (`schema-local.blade.php`), y la FAQ dice «Sí. Desde nuestra página de reservas eliges el servicio…» (`lang/es/home.php`, `faq.online_answer`). Las dos afirmaciones son falsas mientras no haya servicios (patrón H).
  - `deploy.sh` no lo detecta, porque `/reservas` responde 200 en cualquier caso.
  - La situación contraria también es arriesgada. En cuanto se siembre el catálogo, las reservas online quedan abiertas al instante con los valores por defecto: horario de 9 a 19, 17 servicios activos y duraciones aún sin revisar por el salón. Puede que entonces no exista todavía ninguna cuenta del panel para verlas, y la política de privacidad aún tiene el proveedor de correo «[Pendiente de confirmar]».
- **Recomendación:**
  1. Que el usuario decida cómo se siembra en producción:
     - o `deploy.sh` ejecuta `php artisan db:seed --class=ServiceCatalogSeeder --force` después de `migrate` (es idempotente con `firstOrCreate`, así que es seguro en cada despliegue);
     - o se añade como paso explícito, con `--force`, en «Before deploying the booking system».
  2. Corregir `.ai/specs/reservas.md:322` y `AGENTS.md:112-114` para que describan lo que pasa de verdad.
  3. Documentar el orden del primer despliegue para que las reservas no se abran antes de tiempo: desplegar, crear la cuenta con `admin:create-user`, apagar «Reserva online activa» si el salón aún no ha revisado el catálogo, sembrar, revisar y volver a encenderla.
  4. Añadir un test que falle si la FAQ o el JSON-LD anuncian la reserva online cuando no hay ningún servicio reservable. Como alternativa, hacer que `onlineBookingEnabled()` (o un método nuevo `onlineBookingAvailable()`) también exija que haya al menos un servicio `bookableOnline()`.

## Baja

### L1. Las URL de los correos dependen de la cabecera `Host` de la petición

- **Estado:** abierto. No se ha comprobado en el servidor.
- **Evidencia:**
  - Los enlaces se generan con `route()` dentro de la petición web:
    - `app/Mail/AppointmentConfirmedMail.php:28` (`cita.show`), que se envía desde `BookingController::store()` (`BookingController.php:192`);
    - `app/Mail/NewAppointmentMail.php:29` y `CustomerCancelledAppointmentMail.php:27` (`admin.agenda`);
    - `resources/views/vendor/mail/html/message.blade.php:11` (`asset()` del logo).
  - En HTTP, `UrlGenerator` usa la raíz de la petición, es decir, la cabecera `Host`.
  - `bootstrap/app.php` no configura `trustHosts()` y no hay ningún `URL::forceRootUrl()` (`grep -rn "forceRootUrl\|trustHosts" app bootstrap config` no devuelve nada).
  - Es el candidato 8 de las lecciones.
- **Impacto:** depende de nginx. Si el bloque de `www.peluqueriajenver.com` es el `default_server` del VPS, o acepta cualquier `Host`, un atacante puede hacer dos cosas con una reserva enviada con `Host: dominio-atacante`:
  - que el aviso al salón («Ver la agenda de ese día») apunte a `https://dominio-atacante/admin/agenda…`, una página falsa de *login*, enviado desde el remitente legítimo del salón;
  - reservar con el email de un tercero, que recibirá una confirmación auténtica con un enlace al dominio del atacante.

  Según `vps-ovh.md`, un dominio sin bloque propio se sirve con el bloque por defecto, así que el riesgo real depende de qué bloque sea ese.
- **Recomendación:** configurar `$middleware->trustHosts(at: ['www.peluqueriajenver.com', 'peluqueriajenver.com'])` en `bootstrap/app.php` (o `URL::forceRootUrl(config('app.url'))` en producción), y añadir un test que envíe una reserva con otro `Host` y compruebe que el correo enlaza a `APP_URL`. Para comprobarlo en el VPS: `curl -sk -o /dev/null -w '%{http_code}' -H 'Host: ejemplo.invalid' https://www.peluqueriajenver.com/`.

### L2. Al cambiar el email de una cita, el cron puede enviar la confirmación por duplicado

- **Estado:** abierto.
- **Evidencia:**
  - `RescheduleAppointment.php:183` pone `customer_notified_at = null` y confirma la transacción.
  - Después, `AppointmentController::update()` envía el correo de forma síncrona (`AppointmentController.php:145`, `sendChangeNotice()`).
  - `NotifyPendingAppointmentsCommand.php:28` solo deja margen respecto a `created_at` (`created_at <= now()->subMinutes(5)`). En una cita creada hace más de 5 minutos, que es casi siempre el caso de una cita que se edita, no hay ningún margen.
- **Impacto:** si el cron (cada 10 minutos) coincide con el envío síncrono, o con un SMTP lento (hasta `MAIL_TIMEOUT` = 10 s), la clienta recibe la confirmación dos veces. Es la misma familia que el candidato «reintento que se cruza con el envío original» de cobaprojects. El efecto es solo un correo repetido.
- **Recomendación:** añadir al comando `->where('updated_at', '<=', now()->subMinutes(5))`, o cambiar la condición a `greatest(created_at, updated_at)`, con un test de una cita antigua con el email recién cambiado.

### L3. La comprobación previa de `deploy.sh` solo cubre el correo, y `.env.example` no incluye `SESSION_SECURE_COOKIE`

- **Estado:** abierto.
- **Evidencia:**
  - `check_env_mail()` (`deploy.sh:68`) solo mira `MAIL_MAILER`, `MAIL_HOST`, `MAIL_FROM_ADDRESS` y `BOOKING_NOTIFICATION_EMAIL`.
  - `deploy:check`, que corre ya en mantenimiento (`deploy.sh:228`), además falla por `SESSION_SECURE_COOKIE` (`DeployCheckCommand.php:85`), `APP_NAME`, `MAIL_FROM_NAME`, `APP_ENV`, `APP_DEBUG` y `APP_URL`.
  - `.env.example` no tiene ninguna línea `SESSION_SECURE_COOKIE` (`grep SESSION .env.example` solo muestra `DRIVER`, `LIFETIME`, `ENCRYPT`, `PATH` y `DOMAIN`), aunque es un requisito nuevo de esta PR.
  - Además, `.env.example` trae `MAIL_FROM_ADDRESS="reservas@peluqueriajenver.com"`, un buzón provisional que pasa las dos comprobaciones aunque no exista.
- **Impacto:** si en el `.env` del VPS falta `SESSION_SECURE_COOKIE=true`, que es nuevo en esta PR, el primer despliegue entra en mantenimiento, ejecuta las migraciones y se queda caído en `deploy:check`. Es justo la caída evitable que `check_env_mail` quería impedir (lección 2026-10-02, patrón E).
- **Recomendación:**
  - extender la comprobación previa a `SESSION_SECURE_COOKIE=true` (y, ya puestos, a `APP_ENV`, `APP_DEBUG` y `APP_URL`), con su caso en `DeployCheckCommandTest`;
  - añadir `SESSION_SECURE_COOKIE=false` (local) a `.env.example`, con un comentario de que en producción va `true`;
  - valorar si `MAIL_FROM_ADDRESS` de `.env.example` debe ir vacío, para que no pase las comprobaciones por accidente.

### L4. La política de privacidad promete borrar las citas a los 2 años, pero nada las borra

- **Estado:** abierto. Decide el usuario.
- **Evidencia:**
  - `resources/views/pages/privacidad.blade.php:71` dice: «Conservamos los datos de cada cita durante 2 años… Pasado ese tiempo se eliminan.»
  - No hay ningún comando, *prune* ni tarea de cron que borre o anonimice citas (`grep -rni "prune\|purge\|retention" app routes database` no devuelve nada).
  - Las citas «are never deleted» (`CancelAppointment`, `AGENTS.md`).
  - `.ai/specs/reservas.md:509` deja «La purga automática… para el PR 5».
- **Impacto:** no hay urgencia (la primera cita cumplirá 2 años en 2028), pero el texto legal afirma un comportamiento que no existe (patrón H) y no consta como pendiente en `AGENTS.md`.
- **Recomendación:** apuntar en `AGENTS.md` («Before deploying» o en un apartado de pendientes) que la purga a los 2 años es obligatoria antes de 2028. Si se prefiere, matizar el texto («se eliminan o anonimizan») hasta que exista el comando.

### L5. `AdminUsersSeeder` siembra cuentas reales con la contraseña `password` en un repositorio público, y nada lo detecta en producción

- **Estado:** abierto. Es una decisión del usuario (2026-10-06); se registra el riesgo, no se discute la decisión.
- **Evidencia:** `database/seeders/AdminUsersSeeder.php:26-37` hace `updateOrCreate` de `peluqueriajenver@gmail.com` y `josep@cobaprojects.com` con `'password' => 'password'`. El repositorio es público (`git remote -v` → `https://github.com/JosepBacardit/peluqueriajenver.git`). El *login* limita a 5 intentos por minuto por email e IP y a 20 por IP, pero `password` es el primer intento de cualquier atacante.
- **Impacto:** si alguien ejecuta este *seeder* en el VPS, aunque sea por error con `db:seed --class=…`, el panel, con todos los datos personales de las clientas, queda abierto con una contraseña pública. Y como el *seeder* hace `updateOrCreate`, también **restablece** a `password` la contraseña que el salón hubiera puesto en «Mi cuenta».
- **Recomendación:** hacer que `deploy:check` falle si alguna cuenta tiene la contraseña `password` (`Hash::check('password', $user->password)` sobre `User::all()`, que son pocas). Como alternativa, que el *seeder* se niegue a ejecutarse con `app()->isProduction()`. Las dos opciones son compatibles con la decisión del usuario para el uso local.

### L6. El tope diario de 10 reservas por IP cuenta también los envíos fallidos

- **Estado:** abierto.
- **Evidencia:** `routes/web.php:60` aplica `throttle:booking-submissions`, y `AppServiceProvider.php:41-45` le da `perDay(10)` por IP. El *middleware* `ThrottleRequests` suma un intento antes de ejecutar el controlador, así que también cuenta:
  - los errores de validación (teléfono con mal formato, falta la casilla de privacidad);
  - el *honeypot*;
  - «esa hora ya no está disponible»;
  - y `TooManyUpcoming`.
- **Impacto:** una clienta que se equivoca unas cuantas veces, o varias clientas detrás de la misma IP (CGNAT móvil, wifi de un local), pueden agotar las 10 del día y ver «Has hecho demasiadas reservas hoy» sin haber reservado nada. Es el mismo matiz que la revisión de obranur-web señaló en el candidato 2 de las lecciones.
- **Recomendación:** mantener el `throttle` por minuto en la ruta y llevar el tope diario a `BookingController::store()`, con un `RateLimiter::hit()` solo cuando se crea una cita. Añadir un test con 10 envíos inválidos seguidos de uno válido.

### L7. Dos comentarios de tests siguen diciendo que el HTML público se cachea «durante días»

- **Estado:** abierto.
- **Evidencia:**
  - `tests/Feature/CookieConsentTest.php:15-16` dice «Pages are cached for days (see CacheHeaders)». Viene de la PR #7, escrita antes del cambio de caché de esta PR.
  - `tests/Feature/Booking/AppointmentPagePrivacyTest.php:33` dice «CacheHeaders caches this page for days». Se escribió en el *commit* de la fusión `0e4d707`.
  - Desde el 2026-10-06, `CacheHeaders` envía `no-cache, private` y un `ETag` (PRF-141). Es una interacción entre las dos ramas que ninguna revisión parcial podía ver (patrón D).
- **Impacto:** engaña a quien lea el test. El razonamiento («el filtro tiene que estar en JS») sigue siendo válido por otro motivo: el mismo HTML se sirve a todos los visitantes y se revalida con `ETag`.
- **Recomendación:** reescribir los dos comentarios: «the same HTML is served to every visitor (revalidated by ETag, see CacheHeaders)».

### L8. Las notas de «Before the first real deploy» de `AGENTS.md` contradicen `vps-ovh.md` (fuera del diff)

- **Estado:** abierto. Es anterior a la PR, pero afecta a la preparación de este despliegue.
- **Evidencia:**
  - `AGENTS.md:462` dice que la ruta del VPS no está anotada, y `AGENTS.md:469` pide comprobar la *deploy key* y el alias SSH `github-peluqueriajenver`. El mensaje de error de `git fetch` en `deploy.sh` remite a ese mismo alias.
  - En cambio, `developer-brain/knowledge/vps-ovh.md:18` (2026-10-02) dice `/var/www/peluqueriajenver` («repositorio público, remote por HTTPS, sin deploy key»).
  - La línea de cron de `AGENTS.md` ya usa `/var/www/peluqueriajenver`.
- **Impacto:** quien despliegue puede perder tiempo configurando una clave SSH que no hace falta, o diagnosticar mal un fallo de `git fetch`.
- **Recomendación:** confirmar en el VPS (`git -C /var/www/peluqueriajenver remote -v`) y actualizar las dos viñetas de `AGENTS.md` y el mensaje de `deploy.sh`.

---

## Comprobado sin hallazgos

- **Autenticación del panel:**
  - todas las rutas `admin.*` están dentro de `auth` + `auth.session`, y el `login` dentro de `guest`;
  - no hay registro ni «remember me»;
  - el *login* regenera la sesión, iguala el tiempo de respuesta con un *hash* ficticio y limita por email+IP y por IP;
  - el `logout` es un POST que invalida la sesión;
  - «Mi cuenta» solo cambia la contraseña propia, con `current_password`, `logoutOtherDevices()` y `regenerate()`;
  - `admin:create-user` pide la contraseña con `secret()` y exige un mínimo de 12 caracteres, compartido con «Mi cuenta».
- **CSRF:** todos los formularios POST, PUT y DELETE (públicos y del panel) llevan `@csrf`. La validación de CSRF del grupo `web` corre antes del `throttle`.
- **Redirecciones:** `volver` se valida contra vista y fecha (`AgendaController::volverFromQuery`), y `redirect()->intended()` solo admite URL internas. No hay redirecciones abiertas.
- **Tokens:**
  - `/cita/{token}` usa `Str::random(48)`, con índice único;
  - el token cambia al cambiar el email;
  - la página no carga GTM, GA4, Ahrefs ni Maps (`without_analytics`, que envuelve los dos bloques del *layout* fusionado de la PR #7);
  - `og:url` y `canonical` apuntan a `/reservas`;
  - el `Referrer-Policy: strict-origin-when-cross-origin` global no filtra la ruta a otros orígenes;
  - el *banner* de cookies no registra ningún *loader* en esa página.
- **Caché:**
  - `/admin*`, `/reservas*` y `/cita/*` responden `no-store` sin `ETag`;
  - las páginas públicas responden `no-cache, private` con un `ETag` del contenido;
  - ninguna vista pública fuera de esas rutas usa `@csrf`, `old()`, `session()` ni `@auth` (`grep` sobre `pages/`, `partials/` y `layouts/app`), así que ninguna página cacheable lleva estado de sesión ni datos personales;
  - la rama `HEAD` es correcta.
- **XSS:**
  - en las vistas, los únicos `{!! !!}` sirven atributos ARIA literales, cadenas de `lang/` o JSON-LD construido en PHP sin datos de la clienta; el horario sale de horas ya validadas;
  - en los correos, todo va con `{{ }}` y `Markdown::withSecuredEncoding()`;
  - no hay cambios en el JS del cliente ni sumideros `innerHTML`.
- **Precios:** `price_cents` no aparece en ninguna vista pública ni en ningún correo a la clienta.
- **Concurrencia y capacidad:**
  - `CreateAppointment` y `RescheduleAppointment` bloquean primero la fila de `booking_settings`, y el resto de lecturas va después del bloqueo;
  - las cancelaciones y los movimientos usan un `UPDATE` condicional sobre `status`;
  - `capacityProblem()` comprueba todos los instantes en los que la ocupación puede subir;
  - el duplicado y el tope por email o teléfono se comprueban bajo el bloqueo;
  - los servicios de la cita se guardan como copia congelada (`snapshotOf`) en la misma transacción.
  - Los servicios desactivados dejan de ofrecerse en la web y en «Nueva cita», pero se mantienen en la cita que ya los tenía. Un cambio de horario o un cierre no toca las citas existentes; los cierres además avisan de cuántas hay afectadas.
- **Zonas horarias:**
  - `app.timezone` es `Europe/Madrid`, fijado en el código;
  - las horas de cita son `dateTime` sin zona;
  - `createFromFormat('Y-m-d H:i')` va seguido de `startOfMinute()`;
  - `atMinute()` usa la hora de reloj, así que los días de cambio de hora salen bien.
- **Migraciones:**
  - solo añaden tablas; ninguna de las seis existe en `main` (`git diff --stat` las muestra todas como nuevas), así que no pueden figurar como `Ran` en producción;
  - el orden respeta las claves foráneas (`services` → `appointments` → `appointment_services`, con `restrictOnDelete` hacia `services`);
  - los nombres de índice tienen menos de 64 caracteres;
  - los datos iniciales de horario y ajustes se insertan en la propia migración, y `BookingSetting::current()` nunca encuentra la tabla vacía;
  - `migrate:rollback` está prohibido en `AGENTS.md`, con la vuelta atrás documentada mediante `git revert`.
- **Despliegue:**
  - `deploy.sh` entra en mantenimiento antes de tocar el código, usa `sudo -n` dentro del mantenimiento, comprueba también `/reservas` y no sugiere `git checkout` como vuelta atrás (patrón J revisado);
  - `MAIL_TIMEOUT` vale 10 s;
  - el cron de `appointments:notify-pending` lleva `flock -n`, corre como `deploy` y deja 5 minutos de margen sobre `created_at` (salvo lo indicado en L2);
  - el comando se registra automáticamente desde `app/Console/Commands`;
  - no hay ningún `env()` fuera de `config/`, así que `optimize` no rompe nada.
- **Textos legales:**
  - `avisos-legales` ya dice «Titular: Isabel Lechuga Valverde», con el nombre comercial y el NIF 53650299Q;
  - `privacidad` recoge al responsable, la base 6.1.b, los encargados y los derechos;
  - el único marcador pendiente es el proveedor de correo, que es un bloqueo conocido (si los avisos al salón van a `peluqueriajenver@gmail.com`, Google también será encargado de ese tratamiento, y conviene decidirlo junto con el SMTP);
  - el email de contacto es el mismo en `avisos-legales`, `cookies` y `privacidad`.
- **Web pública:**
  - los CTA, el JSON-LD (`ReserveAction`, `openingHoursSpecification`), la FAQ, el pie y la página de contacto leen el horario y el interruptor de la base de datos;
  - el `sitemap` añade `/reservas`;
  - los tests de la PR #7 se adaptaron con `RefreshDatabase` sin debilitar sus comprobaciones, y `AppointmentPagePrivacyTest` se reforzó.
- **Tests (lectura, no ejecución):** hay cobertura nombrada del orden del bloqueo (`sqlWithVisibleLocks`), de la caché por ruta, del escapado de los correos, de los límites de abuso, del interruptor y del *seeder*. Huecos relevantes: los de M1, L1, L2, L3 y L6, descritos en cada hallazgo.
