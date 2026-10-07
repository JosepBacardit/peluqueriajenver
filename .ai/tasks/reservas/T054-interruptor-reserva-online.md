# T054 — Interruptor «Reserva online activa»

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-137, PRF-138, PRF-139, PRF-140
- **Depende de:** ninguna
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `high`
- **Motivo:** el salón necesita poder apagar la reserva online (vacaciones, saturación, mantenimiento) sin dejar `/reservas` como un enlace roto ni tocar código.
- **Estado:** done
- **PR / rama:** `feature/opening-hours-ux`, desde `feature/agenda-service-filter` (`1ce486e`)

## Objetivo

Una casilla en Ajustes que, desactivada, convierte `/reservas` en una página de contacto (sin redirigir) y cambia todos los «Reservar cita» de la web a llamar o WhatsApp, sin tocar nada más (citas existentes, correos, cron, panel).

## Decisiones del usuario (2026-10-06)

- Columna booleana en `booking_settings`, activa por defecto.
- `/reservas` mantiene su URL y responde 200 con: el mensaje «La reserva online no está disponible en este momento. Pide tu cita por teléfono o WhatsApp», botones «Llamar» y «WhatsApp» de al menos 44 px, la dirección, el horario (`OpeningHoursSummary`) y un enlace a «Contacto».
- El `POST` de reserva se rechaza con ese mismo mensaje, sin crear nada, con test.
- Todos los «Reservar cita»/«Reservar online» de la web pasan a llamar o WhatsApp: cabecera (escritorio y móvil), hero de la portada y de las 4 páginas de servicio, sección «Reserva tu cita», pregunta frecuente.
- El JSON-LD quita el `ReserveAction` mientras esté desactivada.
- `/cita/{token}`, los correos, el cron y el resto del panel siguen igual. La agenda muestra un aviso visible «Reserva online desactivada».

## Migración y base local (comunicado al coordinador antes de ejecutarlo)

Las migraciones de reservas no se han ejecutado nunca en producción, así que la columna se añadió editando la migración de `booking_settings` de la PR (`2026_10_03_140100_create_booking_settings_table.php`), igual que T046 hizo con `appointments`, en vez de crear una migración aparte.

La migración ya está en el mismo *batch* (2) que `services` y `opening_hours`, que **sí** tienen datos locales (4 servicios, 5 tramos). Rehacer ese *batch* (`migrate:rollback`/`migrate:fresh`) habría borrado esas dos tablas. En vez de eso, para la base local se aplicó solo lo necesario: un `ALTER TABLE booking_settings ADD COLUMN online_booking_enabled TINYINT(1) NOT NULL DEFAULT 1` directo (vía `tinker`, con `-u www-data`), sin tocar la tabla `migrations` ni ninguna otra tabla. Verificado después: 4 servicios, 5 tramos, 2 usuarios y la columna nueva en `enabled`, todo intacto. La tabla `appointments` sigue vacía (no se ha creado ninguna cita).

## Implementación

- `BookingSetting`: columna `online_booking_enabled` (cast `boolean`, en el `Fillable`), método estático `onlineBookingEnabled()`. No memorizado (mismo patrón que el resto de usos de `current()` en este modelo): una lectura más por página pública no es un problema salvo donde ya hay un presupuesto de consultas fijo (la agenda, ver abajo).
- `BookingSettingsRequest`/`BookingSettingsController`: la casilla es `sometimes|boolean`; si la petición no la lleva (una API vieja, un test anterior a esta columna), el valor no cambia — nunca se apaga la reserva por sorpresa. La vista manda un campo oculto `value="0"` antes de la casilla, el patrón habitual de Laravel.
- `BookingController::index()`: si está desactivada, devuelve `pages.reservas-disabled` directamente, sin tocar el resto de la lógica de servicios/calendario.
- `StoreBookingRequest::authorize()`: devuelve el interruptor; `failedAuthorization()` redirige a `/reservas` (que ya muestra el mensaje) sin validar ni crear nada — así una petición manipulada con cualquier dato nunca llega a `rules()`.
- `AgendaController`: en vez de que el *layout* compartido (`admin.blade.php`, en todas las páginas del panel) añada una consulta nueva a `booking_settings`, `index()` hace una sola lectura (`BookingSetting::current()`) y reparte `capacity` a `dayData()`/`weekData()` (que antes la leían cada una por su cuenta) y `online_booking_enabled` a la vista — el aviso vive en `admin/agenda/index.blade.php`, no en el *layout*, para no añadir una consulta a los otros módulos (Servicios, Horario, Cierres, Ajustes) ni romper el recuento fijo de consultas de PRF-109/PRF-118.
- Los 9 sitios con un «Reservar cita»/«online» (`header.blade.php` x2, `home.blade.php` x2, las 4 páginas de servicio, `schema-faq.blade.php`) comprueban `BookingSetting::onlineBookingEnabled()` directamente; `schema-local.blade.php` omite `potentialAction` por completo en vez de apuntarlo a un tipo de acción que schema.org no tiene para «llama a este número».
- Nuevas cadenas en `lang/es/reservas.php` (`disabled.*`) y `lang/es/navigation.php` (`navbar.cta_disabled`); `lang/es/home.php` añade `faq.online_answer_disabled`.

## Plan de pruebas

`tests/Feature/Booking/OnlineBookingToggleTest.php` (nuevo, 15 tests): fresh install activo; `/reservas` con 200 y el mensaje/botones/dirección/horario; `/reservas` normal cuando está activo; `POST` rechazado sin crear nada (y con el interruptor activo, que sigue funcionando); cabecera, portada y las 4 páginas de servicio llamando en vez de reservar; la pregunta frecuente con el texto de desactivada; el JSON-LD sin `potentialAction`; el aviso del panel (presente/ausente); `/cita/{token}` y el resto del panel funcionando con la reserva desactivada.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `vendor/bin/pint --test` sobre los archivos tocados · `npm run build`. Sin citas creadas en MySQL.

## Fuera de alcance

La caché HTML (T055).
