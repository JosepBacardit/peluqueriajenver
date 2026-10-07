# Revisión independiente: reservas online (T013)

- **Alcance:** `git diff main..feature/booking-notifications` (4 commits apilados: `5ecbb28`, `e8ae32d`, `6372a43`, `53e660f`), contrastado con `.ai/specs/reservas.md` y `.ai/tasks/reservas/`.
- **Skill:** `architecture-review`.
- **Fecha:** 2026-10-03.
- **Revisor:** Claude Opus 5.5, en un contexto limpio. No ha participado en la implementación.
- **Tests:** `docker compose exec -T app php artisan test --compact` da 215 pasados y 1 omitido (`DeployCheckCommandTest.php:148`, que solo se omite al ejecutar como root), con 893 aserciones.
- **Nota:** T013 indica `.ai/reviews/reservas/` como destino, pero el usuario pidió `.ai/reviews/reservas.md`. Se ha seguido la instrucción del usuario. La matriz de cobertura de `.ai/tasks/reservas/index.md` no se ha tocado: la completa el agente `programador` al cerrar los hallazgos.

## Resumen

| Severidad | Nº |
| --- | --- |
| Critical | 0 |
| High | 1 |
| Medium | 4 |
| Low | 9 |

**Resolución (2026-10-03, agente `programador`, rama `feature/booking-review-fixes`):** 13 hallazgos resueltos (H1, M1–M3, L1–L9; L3 en parte, con el resto justificado) y 1 pendiente de decisión del usuario (M4). Cada uno lleva su resolución y su evidencia debajo.

**M4 resuelto (2026-10-06):** la PR #7 (`fix/cookie-consent`) condicionó GTM/GA4/Ahrefs al consentimiento y quitó el *beacon* `<noscript>` que no se podía condicionar; fusionada en `main` y de ahí a esta rama. Los 14 hallazgos de esta revisión quedan resueltos.

---

## High

### H1. El enlace personal `/cita/{token}` se envía a Google (GTM/GA4) y a Ahrefs

- **Estado:** resolved
- **Resolución:** `layouts/app.blade.php` envuelve GTM, su `noscript` y la carga diferida de GA4/Ahrefs en `@unless (View::hasSection('without_analytics'))`; `pages/cita.blade.php` define `without_analytics` y `og_url` = `route('reservas')` (el `canonical` ya apuntaba ahí). `/reservas` mantiene la analítica: su URL no lleva datos personales ni el token (solo `servicio`, `mes`, `fecha`), así que la parte «por coherencia» no se aplica. Test: `AppointmentPagePrivacyTest` (sin `googletagmanager`/`gtag(`/`ahrefs` y sin el token en ningún `<meta>`/`<link>`; la portada conserva la analítica). Fallaba antes del arreglo. Nuevo punto PRF-064.
- **Evidencia:**
  - `resources/views/pages/cita.blade.php:1` extiende `layouts.app`.
  - Ese layout carga sin condiciones Google Tag Manager (`resources/views/layouts/app.blade.php:4-9`), GA4 (`:129-135`) y Ahrefs Analytics (`:141-143`). GA4 y Ahrefs envían por defecto la URL completa de la página (`page_location`/`dl`), y en `/cita/{token}` esa URL incluye el token. También se publica en `og:url` (`:28`).
  - El token es la única credencial de la cita (`CustomerAppointmentController.php`, `show`/`cancel`): con él se ven el nombre, el servicio, el día y la hora y se puede **cancelar** la cita.
- **Impacto:**
  - Todos los tokens de todas las citas acaban en los informes de GA4 y Ahrefs, visibles para cualquiera con acceso a esas cuentas (salón, agencia u otros).
  - Esto anula la propiedad de «enlace imposible de adivinar» de PRF-039: el token está en manos de terceros.
  - Desde el punto de vista del RGPD, supone comunicar a Google y a Ahrefs un identificador ligado a datos personales sin base legal y sin consentimiento (véase M4). Además contradice la capa informativa («No se ceden datos a terceros», `lang/es/reservas.php:47`).
- **Recomendación:**
  - Que `/cita/{token}` (y, por coherencia, `/reservas`) no cargue scripts de analítica. Por ejemplo, con un `@section('analytics', false)` o un layout sin trackers, o condicionando los bloques de `layouts/app.blade.php` a `! request()->is('cita/*')`.
  - Quitar o sustituir `og:url` en esa página, por ejemplo por `route('reservas')`, igual que el `canonical`.
  - Añadir un test que compruebe que la respuesta de `/cita/{token}` no contiene `googletagmanager`, `gtag` ni `ahrefs`, ni el token en ningún `<meta>`.
  - Como defensa adicional, valorar el envío del token por POST o un redireccionamiento que lo saque de la URL visible. No es imprescindible si se quitan los trackers.

---

## Medium

### M1. Inyección de Markdown (enlaces) en los correos a partir de los datos del cliente

- **Estado:** resolved
- **Resolución:** `Markdown::withSecuredEncoding()` en `AppServiceProvider::boot()`. Test: `MailContentEscapingTest`, que renderiza los 4 Mailables con `[x](https://evil.example/…)` y `<https://evil.example/z>` en `customer_name` y `notes` y comprueba que no aparece `href="https://evil.example`. Los 4 casos fallaban antes del arreglo. No se añade la restricción opcional de caracteres en el nombre, porque con el escape ya no hace falta. Nuevo punto PRF-065.
- **Evidencia:**
  - Las vistas `resources/views/mail/*.blade.php` son Markdown y muestran `{{ $appointment->customer_name }}` (`appointment-confirmed.blade.php:4`, `appointment-cancelled.blade.php:5,11`, `new-appointment.blade.php:6`, `customer-cancelled-appointment.blade.php:7`) y `{{ $appointment->notes }}` (`new-appointment.blade.php:9`).
  - `{{ }}` solo escapa HTML. La sintaxis Markdown pasa intacta a CommonMark porque no se activa `Illuminate\Mail\Markdown::withSecuredEncoding()` (no aparece en `app/`, `bootstrap/` ni `config/`).
  - Se ha comprobado renderizando `AppointmentConfirmedMail` con `customer_name = "[Pulsa aqui para confirmar](https://evil.example/x)"`: el HTML contiene `<a href="https://evil.example/x"`.
- **Impacto:** cualquiera puede reservar con el email de otra persona (no se verifica) y un nombre como el del ejemplo. El salón envía entonces desde su dominio y su SMTP legítimos un correo con un enlace de *phishing* a un destinatario elegido por el atacante, hasta 5 por minuto y por IP (véase M2). Lo mismo ocurre en los correos al salón con `notes`. Esto daña la reputación del dominio (SPF/DKIM válidos) y expone a los clientes.
- **Recomendación:**
  - Llamar a `Markdown::withSecuredEncoding()` en `AppServiceProvider::boot()`. Escapa `[` y `<` en los `{{ }}` de los correos Markdown.
  - Añadir un test que renderice cada Mailable con `[x](https://evil.example)` en `customer_name` y `notes` y compruebe que no aparece `href="https://evil.example`.
  - Opcionalmente, rechazar en `customer_name` los caracteres `[]<>()` y los saltos de línea.

### M2. Una sola conexión puede llenar la agenda pública y usar el formulario como relé de correo

- **Estado:** resolved
- **Resolución:** `CreateAppointment` rechaza, dentro del mismo bloqueo y solo en reservas web, una tercera cita confirmada futura del mismo email o del mismo teléfono (comparado por los 9 últimos dígitos), con `TooManyUpcomingAppointmentsException` y el mensaje «Ya tienes 2 citas pendientes…». Nuevo limitador `booking-submissions` en `POST /reservas` (5 por minuto y 10 al día por IP, con mensaje propio para el diario). La limpieza ante una avalancha está documentada en `AGENTS.md` (sección «Booking abuse limits and cleanup»). Tests: `BookingAbuseLimitsTest` (email, teléfono en 3 formatos, canceladas/pasadas que no cuentan, panel sin límite, límite diario). Fallaban antes del arreglo. Nuevos puntos PRF-062 y PRF-063. El CAPTCHA queda solo como opción documentada.
- **Evidencia:**
  - El único freno es `throttle:bookings` con 5 envíos por minuto por IP (`AppServiceProvider.php:31-33`) y el honeypot.
  - El control de duplicados solo impide el mismo email a la misma hora (`CreateAppointment.php:50-55`).
  - No hay límite de citas futuras por email o teléfono ni límite diario por IP.
- **Impacto:**
  - Con capacidad 2 y unos 40 huecos de 15 minutos al día, un *script* desde una sola IP (7.200 envíos al día) puede ocupar toda la agenda de 60 días en horas, con emails o teléfonos inventados.
  - El salón tendría que cancelar las citas una a una (no hay cancelación masiva) y los clientes reales verían «sin horas».
  - Cada reserva envía además un correo de confirmación a una dirección arbitraria (es un relé, agravado por M1).
- **Recomendación:**
  - Limitar las citas confirmadas futuras por email y por teléfono normalizado (por ejemplo, 2) dentro de `CreateAppointment`, bajo el mismo bloqueo.
  - Añadir un segundo limitador diario por IP (por ejemplo, 10 al día) a `bookings`.
  - Documentar en `AGENTS.md` cómo detectar y limpiar una avalancha. Valorar un CAPTCHA sin cookies (Turnstile, Friendly Captcha) si ocurre.

### M3. El envío síncrono de correo no tiene *timeout* y puede convertir una reserva confirmada en un 504

- **Estado:** resolved
- **Resolución:** `config/mail.php` → `'timeout' => env('MAIL_TIMEOUT', 10)` y `MAIL_TIMEOUT=10` en `.env.example`. Test: `DeployCheckCommandTest` › «outgoing mail has a finite timeout…» (fallaba con `null`). El fallo de transporte ya lo cubría `AppointmentNotificationsTest`. Nuevo punto PRF-069.
- **Evidencia:**
  - `config/mail.php:48` tiene `'timeout' => null` para SMTP, así que se usa `default_socket_timeout` de PHP (60 s por operación).
  - `BookingController::store` envía hasta 2 correos de forma síncrona después de confirmar la cita (`BookingController.php:273`, vía `AppointmentNotifier::sendCreationNotices`). Lo mismo pasa al cancelar.
- **Impacto:** si el servidor SMTP no responde (caída de Hostalia, puerto filtrado), la petición tarda más de 60 a 120 s. nginx corta por `fastcgi_read_timeout` (60 s por defecto) y el cliente ve un 504 aunque su cita **sí** se ha creado. Si vuelve a intentarlo, recibe «Ya tienes una cita confirmada a esa hora» o «Esa hora ya no está disponible», sin enlace hasta que pase el cron. Esto contradice PRF-052 («el cliente ve la misma página de éxito»).
- **Recomendación:**
  - Poner `'timeout' => env('MAIL_TIMEOUT', 10)` en el *mailer* SMTP y documentar `MAIL_TIMEOUT` en `.env.example`.
  - Añadir un test que simule una excepción de transporte (ya existe) y otro que fije que la configuración tiene un *timeout* finito.

### M4. La nueva política de privacidad afirma que las cookies no técnicas dependen del consentimiento, pero la analítica se carga siempre

- **Estado:** resolved (2026-10-06)
- **Resolución:** corregido en la PR #7 (`fix/cookie-consent`, fusionada en `main` en `ebe99d1`, y de ahí a esta rama con el commit de merge `037516e`): GTM, GA4 y Ahrefs ya solo se cargan si `localStorage.cookieConsent === 'accepted'` (`window.__analyticsConsentLoaders`, consultado también al pulsar «Aceptar» en el banner); el *beacon* `<noscript>` de GTM, que se dispara sin JavaScript y no se podía condicionar, se ha quitado. Lo que decía la política de privacidad («Las cookies que no son técnicas se basan en tu consentimiento») ya es cierto. Sigue vigente, sin relación con esto, que `/cita/{token}` (H1) no carga ninguna analítica en absoluto. Quitado de los bloqueantes de `AGENTS.md` («Before deploying the booking system»).
- **Evidencia:**
  - `resources/views/pages/privacidad.blade.php:51` dice: «Las cookies que no son técnicas se basan en tu consentimiento.».
  - Sin embargo, `layouts/app.blade.php:4-9` y `:123-145` cargan GTM, GA4 y Ahrefs antes de cualquier elección.
  - `partials/cookie-banner.blade.php` solo guarda `cookieConsent` en `localStorage` y no activa ni bloquea nada (comentarios «Enable analytics… if needed»).
  - La capa básica de la reserva dice «No se ceden datos a terceros» (`lang/es/reservas.php:47`).
- **Impacto:** el comportamiento es anterior a esta rama, pero esta rama reescribe la política para las reservas y deja un texto que no se corresponde con la realidad, justo en la página que ahora recoge datos personales (y con H1, identificadores de cita). Esto es un riesgo LSSI/RGPD ante una reclamación y además bloquea la publicación según PRF-059.
- **Recomendación:**
  - Condicionar la carga de GTM, GA4 y Ahrefs a `cookieConsent === 'accepted'` (o usar Consent Mode v2 con `denied` por defecto). Si se decide no hacerlo ahora, corregir el texto de la política y de la capa para que no afirmen algo falso, y abrir la tarea aparte.
  - Confirmarlo con el usuario, porque afecta a todo el sitio y no solo a las reservas.

---

## Low

### L1. La protección contra el *overbooking* es correcta en MySQL, pero depende de una sutileza de InnoDB que ningún test cubre

- **Estado:** resolved
- **Resolución:** el docblock de `CreateAppointment::handle` explica por qué el `SELECT … FOR UPDATE` debe ser la primera lectura de la transacción en InnoDB REPEATABLE READ. Test de regresión: `CreateAppointmentTest` › «the row lock on the booking settings is the first query of the booking transaction», que fallaría si alguien leyera algo antes del bloqueo (el código ya era correcto, así que este test protege contra regresiones y no reproduce un fallo actual). El test de integración con dos conexiones MySQL no se añade: la suite usa SQLite en memoria y añadir un grupo contra el servicio `mysql` es una infraestructura nueva desproporcionada para esta tarea.
- **Evidencia:**
  - `CreateAppointment.php:48` hace `SELECT … FOR UPDATE` sobre la única fila de `booking_settings` como primera sentencia de la transacción. Después, `isAvailable()` y el control de duplicados hacen lecturas consistentes normales.
  - En REPEATABLE READ, InnoDB crea la *read view* en la primera lectura **no bloqueante**. Por eso la segunda transacción, que espera al bloqueo, ve la cita ya confirmada por la primera. El razonamiento es correcto y el envío de correo queda fuera de la transacción.
  - En cambio, `CreateAppointmentTest.php:55-68` solo prueba llamadas secuenciales en SQLite, y el docblock no explica por qué la lectura posterior ve los datos nuevos.
- **Impacto:** un cambio inocente (por ejemplo, leer `BookingSetting::current()` o la disponibilidad *antes* del `lockForUpdate` dentro de la misma transacción, o fijar `START TRANSACTION WITH CONSISTENT SNAPSHOT` o el nivel de aislamiento) reabriría el *overbooking* sin que falle ningún test.
- **Recomendación:**
  - Documentar en el docblock que el bloqueo **debe** ser la primera lectura de la transacción y por qué.
  - Opcionalmente, añadir un test de integración contra el servicio `mysql` de Docker (con dos conexiones) marcado como grupo aparte.

### L2. Una doble cancelación simultánea envía los correos dos veces

- **Estado:** resolved
- **Resolución:** `CancelAppointment` hace un `UPDATE … WHERE status = 'confirmed'` y devuelve `=== 1`. Test: `CreateAppointmentTest` › «cancelling the same appointment twice at once only cancels it once», con dos instancias obsoletas del mismo registro. Fallaba antes del arreglo.
- **Evidencia:** `CancelAppointment.php:20-27` lee `isConfirmed()` y después hace `update()` sin condición. Dos POST simultáneos (doble clic en `/cita/{token}/cancelar` o en el panel) devuelven `true` en ambos casos, y cada uno llama a `sendCancellationNotices`.
- **Impacto:** el cliente y el salón reciben correos de cancelación duplicados. No hay daño en los datos.
- **Recomendación:** hacer una actualización condicional, `Appointment::whereKey($id)->where('status', Confirmed)->update([...]) === 1`, y devolver ese resultado.

### L3. Los avisos fallidos solo constan en `laravel.log` y se reintentan sin límite

- **Estado:** resolved (parcial, el resto justificado)
- **Resolución:** la agenda muestra «Correo de confirmación no enviado…» en las citas confirmadas con email y `customer_notified_at` nulo. Test: `AgendaTest` › «the agenda flags confirmed appointments whose confirmation email could not be sent» (fallaba antes). No se añade el contador de intentos: el reintento termina solo al empezar la cita, como mucho son unas pocas trazas por cita y el salón ya ve el aviso. Nuevo punto PRF-066.
- **Evidencia:**
  - `AppointmentNotifier::send` hace `report($exception)` (`AppointmentNotifier.php:68-71`).
  - La agenda (`admin/agenda/index.blade.php`) no muestra `customer_notified_at` ni `salon_notified_at`.
  - `NotifyPendingAppointmentsCommand` reintenta cada 10 minutos hasta el inicio de la cita, aunque el error sea permanente (por ejemplo, un 550 por un email mal escrito), y deja una traza en el log en cada intento.
- **Impacto:** PRF-052 pide que el fallo quede «registrado para el salón», pero el salón no lo ve en el panel y no sabrá que un cliente no tiene su enlace. Además, el log se llena con trazas repetidas.
- **Recomendación:** mostrar en la agenda un aviso «Correo no enviado» en las citas confirmadas con email y `customer_notified_at` nulo. Opcionalmente, guardar un contador de intentos y dejar de reintentar a partir de N.

### L4. El límite de envíos en la cancelación del cliente no muestra ningún mensaje

- **Estado:** resolved
- **Resolución:** `pages/cita.blade.php` pinta `@error('booking')`. Test: `CustomerAppointmentTest` › «too many cancellation attempts show a message on the appointment page» (fallaba antes).
- **Evidencia:** el limitador `bookings` responde con `back()->withErrors(['booking' => …])` (`AppServiceProvider.php:33`), pero `pages/cita.blade.php` solo pinta `@error('confirm')` (`:53`) y `session('status')`.
- **Impacto:** a partir del sexto POST en un minuto, el cliente vuelve a su página sin saber qué ha pasado.
- **Recomendación:** pintar `@error('booking')` en `cita.blade.php` y añadir un test.

### L5. El acceso al panel se puede endurecer más

- **Estado:** resolved
- **Resolución:**
  - Límite adicional de 20 intentos por minuto solo por IP.
  - Con un email inexistente se ejecuta `Hash::check` contra un hash bcrypt ficticio de coste 12, para igualar el tiempo de respuesta.
  - Se quita «Mantener la sesión abierta».
  - `deploy:check` falla si `session.secure` no es `true`.
  - Tests: `AdminAuthenticationTest` (límite por IP rotando emails, `Hash::check` llamado con email inexistente, sin campo `remember`) y `DeployCheckCommandTest` › «it fails when the session cookie is not https-only». Todos fallaban antes.
  - Nuevos puntos PRF-067 y PRF-068.
- **Evidencia:**
  - `LoginController.php:205` limita solo por email+IP, así que se pueden rotar emails o IPs sin límite global.
  - `Auth::attempt` solo calcula el hash si el usuario existe, lo que permite enumerar emails del panel por tiempo de respuesta.
  - `config/session.php:172` deja `secure` en `env('SESSION_SECURE_COOKIE')` y `deploy:check` no lo exige.
  - «Mantener la sesión abierta» (`admin/login.blade.php:31`) crea una cookie *remember* de larga duración.
- **Impacto:** el riesgo es bajo, porque las contraseñas tienen 12 caracteres o más y el panel tiene pocos usuarios, pero ahora el panel guarda datos personales.
- **Recomendación:**
  - Añadir un límite adicional solo por IP (por ejemplo, 20 por minuto).
  - Hacer que `deploy:check` falle si `session.secure` no es `true` en producción.
  - Valorar quitar el *remember me* o documentar su caducidad.

### L6. `deploy:check` valida el correo solo dentro de la ventana de mantenimiento y no comprueba que el SMTP acepte las credenciales

- **Estado:** resolved
- **Resolución:**
  - `deploy.sh` tiene una función POSIX `check_env_mail` que se ejecuta en el *preflight*, antes de `sudo -v` y de `down`. Comprueba `MAIL_MAILER`, `MAIL_HOST`, `MAIL_FROM_ADDRESS` y `BOOKING_NOTIFICATION_EMAIL` en el `.env`.
  - `deploy:check --smtp` abre la conexión SMTP y se autentica sin enviar nada.
  - Tests: `DeployCheckCommandTest` › «deploy.sh refuses to start when the .env lacks the booking mail settings», que extrae la función de `deploy.sh` y la ejecuta con `sh` sobre 5 `.env` de ejemplo, y «with --smtp it fails when the smtp server cannot be reached». Ambos fallaban antes.
  - Documentado en `AGENTS.md`.
- **Evidencia:** en `deploy.sh`, `deploy:check` se ejecuta después de `php artisan down` y de `migrate --force`. `DeployCheckCommand::checkMail` solo comprueba la forma de la configuración (`mailer`, `host`, `from`, dirección del salón).
- **Impacto:** un `.env` sin el correo configurado deja el sitio en mantenimiento con las migraciones ya aplicadas, aunque esto está documentado en `AGENTS.md`. Unas credenciales SMTP erróneas pasan el *check* y todos los correos fallan en silencio, salvo en el log.
- **Recomendación:**
  - Añadir al *preflight* de `deploy.sh` (antes de `down`) una comprobación barata de que el `.env` define `MAIL_MAILER`, `MAIL_HOST`, `MAIL_FROM_ADDRESS` y `BOOKING_NOTIFICATION_EMAIL`.
  - Añadir a `deploy:check` una opción `--smtp` que abra la conexión y se autentique (`Mail::mailer()->getSymfonyTransport()->start()`) sin enviar nada, para usarla en el primer despliegue.

### L7. Los `down()` de las migraciones borran tablas con datos personales y `migrate:rollback` no está prohibido

- **Estado:** resolved
- **Resolución:** `AGENTS.md` añade `migrate:rollback` a los comandos prohibidos en producción y explica la marcha atrás correcta (revertir los commits y dejar las tablas sin uso). Es un cambio solo de documentación, así que no lleva test: no hay comportamiento de la aplicación que probar, y un test que busque texto en `AGENTS.md` no aportaría nada. Los `down()` se mantienen para el uso local.
- **Evidencia:** `2026_10_03_150000_create_appointments_table.php` y el resto de migraciones nuevas tienen `Schema::dropIfExists(...)` en `down()`. `AGENTS.md:191` prohíbe `migrate:fresh`, `refresh`, `reset` y `db:wipe`, pero no `migrate:rollback`.
- **Impacto:** la marcha atrás prevista en la especificación consiste en revertir los PRs y dejar las tablas sin uso. Pero un `migrate:rollback` en producción, quizá tras un despliegue fallido, borraría todas las citas sin aviso. Las migraciones son solo aditivas y seguras al aplicarse, con el orden de la clave foránea correcto y los índices dentro de los límites de utf8mb4.
- **Recomendación:** añadir `migrate:rollback` a la lista de comandos prohibidos en producción de `AGENTS.md` y explicar ahí la marcha atrás correcta.

### L8. Cada vista del calendario público lanza unas 120 consultas, sin caché ni límite

- **Estado:** resolved
- **Resolución:** `AvailabilityCalculator::daysWithAvailability` carga una sola vez los ajustes, todo el horario semanal y la ocupación del rango, y calcula cada día en memoria (`startTimesFor`). Test: `PublicBookingTest` › «the month calendar needs a small, fixed number of queries» (menos de 15 consultas; antes eran unas 120 y fallaba). Los tests del motor siguen en verde.
- **Evidencia:**
  - `BookingController::index` llama a `daysWithAvailability` para hasta 31 días.
  - Cada día ejecuta `availableStartTimes`, que lee `BookingSetting::current()`, `rangesFor()` y dos consultas de ocupación (`AvailabilityCalculator.php:38,45,49,110-121`).
  - La ruta GET no tiene *throttle* y su respuesta es `no-store`.
- **Impacto:** es una amplificación barata. Un bot que recorra `?servicio=&mes=` genera unas 120 consultas por petición. No es un problema con el tráfico actual, pero es fácil de mejorar.
- **Recomendación:** cargar una sola vez los ajustes, el horario de la semana y la ocupación del mes (`loadOccupation($from, $to)`) y calcular los días en memoria, o aplicar un *throttle* suave a la ruta GET.

### L9. Huecos en los tests frente a los criterios de la especificación

- **Estado:** resolved
- **Resolución:**
  - **(a)** `AdminRoutesRequireAuthenticationTest` recorre todas las rutas `admin/*` salvo `admin/login`, con todos sus métodos, y exige la redirección a `login`. El código ya era correcto, así que el test protege contra regresiones.
  - **(b)** Cubierto por el test de L1.
  - **(c)** Cubierto por los tests de H1, M1 y L4.
  - **(d)** La capa informativa marca el titular como «[Pendiente de confirmar: nombre o razón social del titular]», igual que la política. Test: `PublicBookingTest` › «the basic data-protection notice marks the data controller as pending like the privacy policy», que fallaba antes.
- **Evidencia:**
  - **(a) Autenticación del panel:** `AdminAuthenticationTest.php:9` solo comprueba que `/admin` redirige a un invitado. No hay ningún test que recorra todas las rutas `admin.*` (incluidos los POST, PUT y DELETE) y verifique el middleware `auth`. El código actual es correcto: todas están dentro del grupo `auth` en `routes/web.php:72-94`.
  - **(b) PRF-026:** solo se prueba la nueva comprobación secuencial (véase L1).
  - **(c) Correos:** no hay ningún test de inyección en los correos (M1), de analítica en `/cita` (H1) ni del mensaje de *throttle* en la cancelación (L4).
  - **(d) Primera capa de privacidad:** como responsable figura «Peluquería Jenver» (`lang/es/reservas.php:41`), mientras que la política marca el titular como pendiente (`privacidad.blade.php:23`). La primera capa debería mostrar el mismo dato o el mismo marcador pendiente.
- **Impacto:** las regresiones de seguridad no se detectarían, y la información de la primera capa no coincide con la de la política.
- **Recomendación:**
  - Añadir un test que recorra `Route::getRoutes()` con el prefijo `admin` (excepto `login`) y compruebe que un invitado recibe una redirección a `login` o un 419/405 en cada método.
  - Añadir los tests indicados en H1, M1 y L4.
  - Hacer que la primera capa use el mismo marcador «[Pendiente de confirmar: …]» que la política hasta tener el titular.

---

## Comprobado sin hallazgos

- **`AvailabilityCalculator` (reglas 1 a 3):**
  - La regla de solape es semiabierta y las citas canceladas no ocupan plaza.
  - Muestrear la ocupación solo en el propio inicio y en los inicios de citas o cierres dentro del intervalo es exacto, porque la ocupación solo sube en esos instantes.
  - Un cierre total resta la capacidad entera y las reducciones parciales se suman.
  - La rejilla se cuenta desde el inicio de cada tramo y la cita debe caber dentro de un único tramo.
  - La antelación mínima se mide en minutos absolutos y la máxima en días naturales.
  - En el cambio de hora se construye con `setTime` (hora de pared) y se suma la duración en tiempo absoluto. Como el salón no abre entre las 02:00 y las 03:00, no hay ambigüedad.
  - Los seis ejemplos de la especificación están cubiertos.
- **Zona horaria:**
  - `Europe/Madrid` se usa de forma coherente para parsear, guardar (`datetime` como hora local) y comparar.
  - Las tablas existentes (`sessions.last_activity`, `cache.expiration`, `jobs`) usan epoch y no les afecta.
  - Solo cambia la lectura de los `created_at` antiguos de `users`, una diferencia de 1 a 2 h sin consecuencias.
- **CSRF:** los formularios llevan `@csrf`, no hay exclusiones y las páginas que llevan token se sirven `no-store`.
- **Caché:** `CacheHeaders::isApi` cubre `/admin*`, `/reservas`, `/reservas/*` y `/cita/*` en GET. Las páginas de marketing mantienen su caché.
- **Token:** `Str::random(48)` usa CSPRNG y da unos 285 bits, con índice único. La búsqueda por igualdad en el índice no ofrece un canal de tiempo útil, enumerar es inviable y los enlaces inexistentes dan 404.
- **Honeypot:** se comprueba antes de validar y no crea ni envía nada.
- **Mass assignment:** todas las escrituras usan arrays explícitos o `validated()`, y `token`/`status` nunca vienen de la petición.
- **XSS en las vistas:** no hay `{!! !!}` nuevos. El `confirm()` de la agenda usa doble escape y no se puede romper (solo se ve `&#039;` en nombres con apóstrofo).
- **Precios:** `price_cents` no aparece en vistas públicas ni en correos al cliente, y está cubierto por tests.
- **Credenciales:** no hay credenciales en el repositorio. `DatabaseSeeder` está vacío, `.env` no se versiona y `admin:create-user` pide la contraseña de forma interactiva.
- **Despliegue:** las migraciones son solo de creación (más dos `insert` iniciales) y siguen el orden correcto de la clave foránea. `deploy.sh` añade `/reservas` a la comprobación final. Los permisos del log con cron (`0664` y directorios `2775`) ya están resueltos.
- **Correo:** los fallos no deshacen la reserva ni la cancelación. El reintento usa un margen de 5 minutos y `flock`, y cada aviso se marca al enviarse, así que no hay duplicados entre la petición y el cron.
