# Revisión independiente: ajustes del panel y los correos (T015–T018)

- **Alcance:** `git diff feature/booking-review-fixes...feature/booking-admin-tweaks` (5 commits: `05b62c7` especificación, `03de9e8` T015, `eeb3a5c` T018, `0c6fff2` T016, `cb80642` T017), contrastado con `.ai/specs/reservas.md` (PRF-070 a PRF-086) y `.ai/tasks/reservas/T015`–`T018`.
- **Skills:** `review` y `architecture-review` (T017 es ARCHITECTURAL).
- **Fecha:** 2026-10-05.
- **Revisor:** Claude Opus 5.5, en un contexto limpio. No ha participado en la implementación.
- **Tests:** `docker compose exec -T -u www-data app php artisan test --compact` → 304 pasados, 1221 aserciones. Único aviso: Pest no puede escribir su caché de resultados (`vendor/pestphp/pest/.temp`, permisos del volumen); no afecta al resultado.
- **Navegador (Chrome, `localhost:8082`, rama `feature/booking-admin-tweaks`):**
  - T018: comprobado. En `/` y en las 4 páginas de servicio, todos los «Reservar cita →» llevan a `/reservas`. «Solicitar Diagnóstico», «Llamar ahora», «Llamar: 633 912 050» y WhatsApp siguen igual.
  - T017: comprobado en parte, sin guardar nada. En la cita local nº 2 se puso la hora 22:00 y se pulsó Intro en «Nombre». El formulario se envió con «Guardar cambios» y mostró el aviso con «Guardar igualmente», que **no** se pulsó. La cita quedó igual (`starts_at` y `updated_at` sin cambios). El valor del botón era `3|2026-10-06|22:00`. El foco automático en el aviso no se pudo verificar: la ventana de Chrome no tenía el foco (`document.hasFocus() = false`) y `activeElement` quedó en `BODY` (véase L6).
  - T016: el desbordamiento del enlace de texto se ha reproducido maquetando el HTML inlineado del correo con una URL de producción realista (véase M1).
  - **Sin comprobar en el navegador:**
    - mover una cita con éxito y el aviso de correo fallido;
    - el recorrido completo con Tab, Shift+Tab y Enter, con la ventana enfocada y un lector de pantalla;
    - el aspecto real de los correos en Gmail (web y móvil) y en Outlook de escritorio.

## Resolución

Todos los hallazgos se han resuelto en la tarea T019 (`.ai/tasks/reservas/T019-resolver-revision-ajustes.md`), en `feature/booking-admin-tweaks`. Suite: 344 tests en verde. Siguen pendientes las comprobaciones manuales que se indican en L5, L6 y L10.

## Resumen

| Severidad | Nº |
| --- | --- |
| Critical | 0 |
| High | 0 |
| Medium | 2 |
| Low | 10 |

### Valoración de la concurrencia de `RescheduleAppointment` (sin hallazgo propio)

El esquema es correcto y no se ha encontrado ninguna forma de sobrepasar la capacidad sin «Guardar igualmente» a través de crear o mover citas:

- **Bloqueo como primera lectura** (`app/Actions/RescheduleAppointment.php:76`). En MySQL/InnoDB REPEATABLE READ, la instantánea de la transacción se toma en la primera lectura no bloqueante, que aquí es `find()` (`:78`) y ocurre ya con el bloqueo concedido. Por eso una petición que esperó el bloqueo ve lo que la anterior acaba de confirmar, ya sea una reserva o un movimiento. Es el mismo razonamiento que el de `CreateAppointment`.
- **Relectura** (`:78-82`). Se basa en `$current`, no en la instancia de la ruta. La duración y la decisión de conservar el servicio (`:88-91`) salen de esa fila fresca.
- **`UPDATE … WHERE status = confirmed`** (`:109-112`). Un UPDATE hace una lectura actual, no de la instantánea, así que una cancelación confirmada entre la relectura y el UPDATE deja 0 filas y el movimiento se rechaza. En el orden inverso, la cancelación espera el bloqueo de fila del UPDATE y luego cancela la cita ya movida. Es un resultado coherente (lo cubre el test `RescheduleAppointmentTest` «a cancellation after a move»).
- **Con `CreateAppointment`.** Las dos acciones toman el mismo bloqueo, así que quedan serializadas, y cada una recalcula la ocupación después del bloqueo. Con `CancelAppointment`: cancelar solo libera capacidad, así que no tomar el bloqueo no permite *overbooking*.
- **Lo que sí puede dejar una franja por encima de la capacidad sin «Guardar igualmente»** es previo a este cambio y queda fuera del alcance: crear un bloqueo de agenda (`ScheduleBlock`) o cambiar el horario o la capacidad después de que haya citas.
- **SQLite frente a MySQL.** Los tests de concurrencia simulan el orden con instancias obsoletas y en secuencia. SQLite ignora `FOR UPDATE` y no reproduce REPEATABLE READ, así que la garantía real en MySQL descansa en el razonamiento anterior y en el test de que el bloqueo va primero. Ese test tiene un límite (véase L9).
- **Margen despreciable.** Si una cancelación espera el bloqueo de fila de un movimiento, el correo de cancelación sale con la hora antigua, porque la instancia de la ruta es obsoleta. La ventana es de milisegundos y no merece un hallazgo.

### Las 4 decisiones del agente

1. **El correo solo se envía si cambia el día, la hora o el servicio.** Es razonable, pero tiene un caso que queda sin cubrir: corregir el email sin mover la cita (véase L3).
2. **Si el servicio no cambia, la cita conserva el nombre y la duración con los que se reservó.** Es coherente con el resto del sistema, que guarda una copia del nombre y la duración del servicio en la cita al reservar. Dos efectos secundarios: el desplegable muestra la duración *actual* del servicio (`edit.blade.php:23`), que puede no coincidir con la que se aplicará, y el salón no tiene forma de aplicar la duración nueva a una cita existente. Lo decide el usuario. No se abre hallazgo.
3. **La cita puede conservar su propio servicio aunque se haya desactivado.** Es correcta y está bien acotada: el `exists` con el *closure* se agrupa entre paréntesis (`DatabasePresenceVerifier::addConditions`) y hay un test que rechaza otro servicio inactivo.
4. **`edit` y `update` sobre una cita que no se puede mover redirigen a la agenda con un aviso en lugar de devolver 404.** Es correcta. Cumple PRF-084 (no se ofrece el enlace y no se guarda nada) y es mejor experiencia que un 404 para alguien que vuelve a una pestaña antigua.

Ninguna de las 4 es un problema por sí misma.

### Otros puntos comprobados sin hallazgo

- **«Guardar igualmente».** La confirmación queda ligada al servicio, la fecha y la hora que se avisaron (`UpdateAdminAppointmentRequest::slotKey()`), y un test cubre el caso de que la hora cambie después del aviso. Una hora pasada se rechaza incluso con `force` (`RescheduleAppointment.php:84-86`, con tests). `store()` ignora `force` (test).
- **Seguridad.**
  - CSRF: `@csrf` + `@method('PUT')`.
  - Autorización: las rutas están dentro del grupo `auth`, y el panel tiene un único rol.
  - *Mass assignment*: la Action construye `$attributes` de forma explícita, `token`, `status` y `source` no se pueden tocar, y `force` no se persiste.
- **Validación.** `UpdateAdminAppointmentRequest` extiende `StoreAdminAppointmentRequest` y solo sustituye `service_id` y añade `force`. Las reglas de la clienta son idénticas a las del alta (PRF-081). Los `maxlength` y el `step=300` del formulario coinciden con el servidor.
- **Exclusión de la propia cita** (`AvailabilityCalculator.php:120,183-191`). `whereKeyNot` sobre las citas confirmadas que se solapan, con test de la unidad y de la Action.
- **Correo de cambio.** M1 sigue cubierto: `withSecuredEncoding` es global y `MailContentEscapingTest` incluye el nuevo Mailable. El correo lleva la marca (`MailBrandingTest`), el mismo token (test) y es *best-effort*: el cambio se mantiene y el panel avisa (test).
- **T015.**
  - `APP_NAME` y `MAIL_FROM_ADDRESS` en `.env.example`;
  - `deploy:check` comprueba `APP_NAME`, con test;
  - `AGENTS.md` está actualizado.

  El remitente tiene una laguna (véase M2).
- **T018.** Correcta y completa según PRF-076, con test por página. Hay una cuestión de producto (véase L10).
- **Tema de correo.** Mantiene las tablas, `role="presentation"` y el CSS inlineado del tema por defecto. Solo cambian colores, logo y pie. El logo usa `asset()` (URL absoluta) y `alt="Peluquería Jenver"`. El pie lleva los datos del salón y no queda ninguna referencia a Laravel en el HTML renderizado. En `header.blade.php:5-6` queda la rama del logo de Laravel, pero es código muerto: nunca se activa con el slot actual.

---

## Medium

### M1. El enlace de texto del correo se sale del recuadro y provoca *scroll* horizontal en el móvil

- **Estado:** resolved
- **Resolución:** la URL escrita en `appointment-confirmed` y `appointment-rescheduled` es ahora un `<a href>`, así que le llega `.inner-body a { word-break: break-all }` (el inliner la copia en el `style`); la parte de texto plano sigue mostrando la URL sola (`strip_tags` del layout de texto). Test: `MailBrandingTest` › «the personal link written out in the customer emails can wrap on a phone» (los dos correos). Navegador: las vistas previas 1 y 5 con una URL de producción de 48 caracteres dan un cuerpo de 570 px a 1024 px y ningún *scroll* horizontal a 375 px (`scrollWidth` igual al ancho).
- **Evidencia:**
  - `resources/views/mail/appointment-confirmed.blade.php:20` y `resources/views/mail/appointment-rescheduled.blade.php:20` escriben la URL como texto plano: `Si el botón no funciona, copia este enlace en el navegador: {{ $appointmentUrl }}`.
  - Como no es un `<a>`, no le alcanza la regla `.inner-body a { word-break: break-all; }` del tema (`themes/default.css:150-152`). En el HTML renderizado (`storage/app/mail-preview/1-appointment-confirmed.html` y `5-…`), el `<p>` no lleva ninguna regla de corte.
  - Reproducido en Chrome con el HTML inlineado y una URL de producción realista (`https://www.peluqueriajenver.com/cita/` + 48 caracteres):
    - con 1024 px de ancho, la URL mide 704 px sin poder cortarse y estira el recuadro de 570 px a 772 px;
    - con 375 px (móvil), el documento mide 741 px de ancho, es decir, hay *scroll* horizontal.
  - Con la misma URL dentro de `<a style="word-break:break-all">`, el recuadro se queda en 570 px y en 369 px, sin *scroll*.
- **Impacto:** el correo de confirmación, que reciben todas las clientas con email, y el de cambio de cita se ven rotos en el móvil, que es el uso mayoritario. En escritorio el recuadro se ensancha más allá del diseño. El problema ya existía antes de T016, pero T016 es la tarea que hace suyo el tema y PRF-072 pide la identidad visual del salón.
- **Recomendación:**
  - Convertir la URL en un enlace, por ejemplo `<a href="{{ $appointmentUrl }}">{{ $appointmentUrl }}</a>` en las dos vistas. Así lo alcanza la regla `.inner-body a { word-break: break-all }`, que el inliner ya copia en el `style`. Otra opción es envolverla en `<span class="break-all">`, como hace la plantilla de notificaciones de Laravel con `<x-mail::subcopy>`.
  - Añadir a `MailBrandingTest` (o a un test nuevo) una aserción de que el `<a>` o el `<span>` con la URL personal lleva `word-break: break-all` en el HTML renderizado.
  - Volver a generar las vistas previas y revisarlas a 375 px.

### M2. PRF-070/071: el nombre del remitente no se comprueba en `deploy:check` ni en ningún test, y la matriz dice que está cubierto

- **Estado:** resolved
- **Resolución:** `deploy:check` falla si `MAIL_FROM_NAME` está vacío o es `Laravel` o `Example` (no se exige que coincida con `APP_NAME`, para permitir p. ej. «Peluquería Jenver · Reservas»); `AGENTS.md` lo documenta. Tests: `DeployCheckCommandTest` (3 casos nuevos y la salida del caso correcto) y `MailSenderTest` (nuevo): los 5 Mailables se envían de verdad con el *mailer* `array` y su `From` es la dirección y el nombre configurados; `.env.example` usa `APP_NAME="Peluquería Jenver"` y `MAIL_FROM_NAME="${APP_NAME}"`. La fila PRF-070 de la matriz cita ahora `MailSenderTest`.
- **Evidencia:**
  - `config/mail.php:117`: `'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel'))`.
  - `DeployCheckCommand::checkMail()` (`app/Console/Commands/DeployCheckCommand.php:170-173`) solo comprueba `mail.from.address`, y la comprobación nueva (`:74-82`) solo mira `app.name`.
  - Un `.env` de servidor con `MAIL_FROM_NAME="Laravel"`, `"Example"` o cualquier valor heredado pasa `deploy:check`.
  - El plan de verificación de PRF-070 («Se envían los 4 correos → el campo "De" muestra "Peluquería Jenver"») no tiene test: ninguno comprueba `$mail->from` ni la cabecera `From`.
  - Aun así, `.ai/tasks/reservas/index.md` marca PRF-070 como `covered` por `DeployCheckCommandTest`, que no lo cubre.
- **Impacto:**
  - PRF-071 exige que el despliegue falle si «el remitente del correo» sigue siendo el de la plantilla, y el nombre del remitente es justo lo que la clienta ve en su bandeja.
  - Además, la matriz da por cubierto con evidencia algo que no lo está. Es el patrón recurrente de evidencia genérica.
- **Recomendación:**
  - En `checkMail()`, fallar también si `config('mail.from.name')` está vacío o es `Laravel` o `Example`. Opcionalmente, exigir que coincida con `config('app.name')`.
  - Añadir dos casos a `DeployCheckCommandTest`.
  - Añadir un test que envíe los 5 Mailables con `Mail::fake()` (o que renderice el mensaje Symfony) y compruebe que el `From` lleva el nombre «Peluquería Jenver».
  - Corregir la fila PRF-070 de la matriz.

---

## Low

### L1. Editar solo los datos de una cita que ya está fuera de horario o por encima de la capacidad obliga a pulsar «Guardar igualmente»

- **Estado:** resolved
- **Resolución:** `RescheduleAppointment` no comprueba la disponibilidad si ni la hora de inicio ni la duración resultante cambian. Tests: `RescheduleAppointmentTest` › «editing only the customer details of an appointment saved over capacity needs no new confirmation» y «keeping the time but taking a longer service is checked again». Mutación: sin la excepción, falla 1 test.
- **Evidencia:** `RescheduleAppointment.php:93-96` llama siempre a `isAvailable()`, aunque el servicio, el día y la hora no cambien. Si la cita se guardó antes forzando, o si el salón ha cambiado después el horario, los cierres o la capacidad, corregir solo el teléfono muestra el aviso «Esa hora cae fuera del horario de apertura o no tiene plaza libre», y el texto dice que «No se ha guardado nada».
- **Impacto:** es confuso: parece que el cambio de teléfono es el problema. Además, obliga a confirmar otra vez una decisión ya tomada.
- **Recomendación:** omitir la comprobación de disponibilidad cuando `starts_at` y la duración resultante no cambian respecto a `$current`. Añadir un test: una cita que se guardó forzando y a la que luego solo se le cambia el teléfono se guarda sin aviso.

### L2. Tras mover una cita cuya confirmación inicial quedó pendiente, el cron le envía además la confirmación

- **Estado:** resolved
- **Resolución:** `AppointmentNotifier::sendChangeNotice()` marca `customer_notified_at` cuando el correo sale bien y estaba pendiente. Tests: `AdminRescheduleAppointmentTest` › «a change notice that is sent counts as the confirmation still pending, so the retry sends nothing more» (confirmación pendiente → mover → `appointments:notify-pending` no envía nada más). Mutación: sin marcarla, fallan 2 tests.
- **Evidencia:**
  - `NotifyPendingAppointmentsCommand` reenvía `AppointmentConfirmedMail` a toda cita futura con email y `customer_notified_at = null`.
  - `sendRescheduleNotice()` (`AppointmentNotifier.php:64-72`) no marca `customer_notified_at`.
  - Hay dos casos:
    - la confirmación falló y el salón mueve la cita: llega «Hemos cambiado tu cita» y, en la siguiente pasada del cron, «cita confirmada» con la hora nueva;
    - una cita creada desde el panel sin email, a la que el salón añade un email al moverla: llegan los mismos dos correos.

    Además, la agenda sigue mostrando la etiqueta «Correo de confirmación no enviado» aunque la clienta ya haya recibido el correo de cambio, con el enlace y todos los datos.
- **Impacto:** bajo. Los dos correos dicen la misma hora (la nueva), así que no hay información errónea, pero el orden («cambiado» y luego «confirmada») resulta extraño y la etiqueta de la agenda engaña. En cambio, cuando el correo de cambio falla, el cron hace de reintento útil.
- **Recomendación:** si `sendRescheduleNotice()` tiene éxito y `customer_notified_at` es `null`, marcarlo, porque el correo de cambio ya contiene el enlace y los datos. Añadir un test: confirmación pendiente → mover → `appointments:notify-pending` no envía nada. Si el usuario prefiere la doble entrega, documentarlo en T017.

### L3. Corregir el email sin mover la cita no envía nada a la nueva dirección (decisión 1)

- **Estado:** resolved (decisión del usuario, PRF-087 nuevo en la especificación)
- **Resolución:** si cambia el email (comparado ya normalizado), la Action genera un token nuevo de 48 caracteres y deja `customer_notified_at` a `null`, bajo el mismo bloqueo. Si además cambian la hora o el servicio, se envía un solo correo, el de cambio, a la dirección nueva y con el enlace nuevo; si solo cambia el email, se envía la confirmación con el enlace nuevo (no hay un Mailable nuevo). *Best-effort* no basta aquí: si el correo falla, la clienta no tiene ningún enlace válido, así que el panel lo avisa explícitamente y, como la confirmación queda pendiente, `appointments:notify-pending` la reintenta cada 10 minutos (diferencia deliberada con PRF-083, recogida en PRF-087). Borrar el email también regenera el token. Tests: `RescheduleAppointmentTest` › «changing the email gives the appointment a new personal link…» y «the same email written differently keeps the personal link»; `AdminRescheduleAppointmentTest` › «changing only the email sends the appointment and its new link…» (el enlace viejo da 404 y el nuevo 200), «moving and changing the email at once sends a single change notice…» y «when the email with the new link fails, the panel says the old link no longer works and the retry sends it».
- **Evidencia:** `AppointmentController.php:786-788`: `$changed` solo depende de la hora y del servicio. Si la confirmación ya se envió (a una dirección equivocada, por ejemplo con una errata), corregir el email no hace que la clienta reciba su enlace. El texto del campo («si lo pones, recibirá el aviso del cambio») tampoco lo aclara. Además, el token no cambia por decisión del usuario, así que quien recibió el correo en la dirección equivocada conserva el enlace, con el que puede ver y cancelar la cita.
- **Impacto:** la clienta puede quedarse sin enlace a su cita, y una tercera persona puede tenerlo.
- **Recomendación:** preguntar al usuario si un cambio de email debe enviar también el correo (de cambio o de confirmación) a la dirección nueva. Si hay riesgo de que la dirección anterior fuera de otra persona, valorar regenerar el token **solo** cuando cambia el email.

### L4. Un formulario abierto antes de otro cambio lo deshace sin avisar, y el aviso por correo se decide fuera del bloqueo

- **Estado:** resolved
- **Resolución:** el formulario envía un campo oculto `version` (el `updated_at` como *timestamp*) y la Action lo compara bajo el bloqueo; si no coincide lanza `AppointmentChangedException` y el panel vuelve al formulario con los datos actuales y un aviso. La resolución es de un segundo (documentado en la Action). La Action devuelve `RescheduleOutcome` con `rescheduled` y `emailChanged` calculados bajo el bloqueo a partir de la fila releída, y el controlador decide el correo con eso; el correo se construye con los valores recién guardados. Tests: `RescheduleAppointmentTest` › «a form opened before another change of the appointment is refused», «a form showing the current version is accepted» y «the outcome says whether the time or the service changed»; `AdminRescheduleAppointmentTest` › «a form opened before someone else changed the appointment saves nothing and shows the current details». Mutación: sin la comprobación, fallan 2 tests.
- **Evidencia:**
  - El formulario reenvía siempre la fecha, la hora y los datos que tenía al abrirse.
  - Si dos personas del salón abren la misma cita, y A la mueve de 10:00 a 12:00 mientras B solo corrige el teléfono, el envío de B la devuelve a las 10:00. Ninguna comprobación detecta que el formulario está desfasado.
  - Además, `$previousStart` y `$previousServiceId` (`AppointmentController.php:772-773`) salen de la instancia de la ruta, leída antes del bloqueo. En la ventana estrecha en que esa lectura precede al commit de A, `$changed` puede dar `false` aunque la cita haya vuelto a moverse, y la clienta se queda con el correo de A (12:00) cuando la cita está a las 10:00.
- **Impacto:** el resultado nunca queda mezclado, así que se cumple la letra de PRF-086. Pero la segunda petición no «ve el estado que dejó la primera y actúa en consecuencia». Con un salón de una o dos personas es poco probable.
- **Recomendación:**
  - Enviar un campo oculto con el `updated_at` (o el `starts_at`) que se mostró y, en la Action y bajo el bloqueo, rechazar el cambio con un aviso («La cita ha cambiado mientras la editabas») si ya no coincide.
  - Que la Action devuelva si cambiaron la hora o el servicio respecto a `$current`, para que el controlador decida el correo con datos leídos bajo el bloqueo.

### L5. Logo y botón del correo en Outlook de escritorio, y peso del logo

- **Estado:** resolved (falta la revisión visual en Outlook)
- **Resolución:** nuevo `public/images/logo-jenver-email.png` de 248×112 (2×), 4,7 KB, generado con ImageMagick a partir de `logo-jenver.png` (la orden está en el comentario de `vendor/mail/html/message.blade.php`), con `width="124" height="56"`; el CSS de `.logo` fija 124×56 en lugar de `max-height`/`width:auto`. El botón lleva `bgcolor` y `mso-padding-alt: 8px 18px` en su celda, así que Outlook de escritorio pinta el fondo y el relleno aunque ignore los bordes del enlace. Tests: `MailBrandingTest` (logo: archivo, dimensiones, peso por debajo de 15 KB y atributos; «the button keeps its shape in Outlook for Windows»). Navegador: el logo sale a 124×56.
- **Evidencia:**
  - `public/images/logo-jenver-optimized-v2.png` mide 400×180 px y pesa 51,8 KB.
  - En `vendor/mail/html/message.blade.php:7` lleva `width="160"`, y el CSS inlineado `max-height: 56px; width: auto; height: auto`.
  - Gmail y Apple Mail respetan `max-height`, así que el logo sale a unos 124×56 px. Outlook de escritorio (motor Word) ignora `max-height` y `width:auto`, usa el atributo `width="160"` y lo mostrará a 160×72 px: un tamaño distinto en cada cliente.
  - El botón «Ver mi cita» es un `<a>` con relleno hecho con `border` (`themes/default.css:242-249`, heredado del tema de Laravel). Outlook de escritorio no aplica bordes ni relleno a un `<a>` en línea y lo muestra como un texto con un fondo dorado ajustado.
- **Impacto:**
  - Se descargan 52 KB para mostrar unos 124 px de ancho.
  - El tamaño del logo es inconsistente entre clientes.
  - El botón queda pobre en Outlook, aunque el enlace funciona.
- **Recomendación:**
  - Generar un PNG específico para el correo a 2× del tamaño que se muestra (por ejemplo 248×112, optimizado, idealmente por debajo de 15 KB).
  - Ponerle `width="124" height="56"` como atributos y quitar `max-height` y `width:auto` del CSS de `.logo`.
  - Opcionalmente, añadir un botón VML o `mso-padding-alt` para Outlook.
  - Comprobarlo en la revisión visual en Outlook que ya está pendiente.

### L6. Accesibilidad del formulario de edición: foco del aviso sin verificar y errores sin enlazar

- **Estado:** resolved (falta el recorrido con teclado y lector de pantalla)
- **Resolución:** el foco al aviso lo pone ahora un script mínimo justo después del aviso (`document.getElementById('slot-warning').focus()`), en lugar de `autofocus`. En `edit` y en `create`, cada campo con error lleva `aria-invalid="true"` y `aria-describedby="<campo>-error"`, y el mensaje tiene ese `id`; en `edit`, servicio, fecha y hora añaden además `slot-warning-text` cuando hay aviso. Tests: `AdminRescheduleAppointmentTest` › «the warning takes the focus, is linked from the time fields and comes after the normal save button» (incluye el orden de los botones de envío dentro del formulario) y «fields with an error are marked invalid and point to their message»; `AdminAppointmentTest` › «fields of a new panel appointment with an error are marked invalid…».
- **Evidencia:**
  - El aviso usa `autofocus` en un `<div tabindex="-1" role="alert">` (`edit.blade.php:75`). En la prueba en Chrome, `activeElement` quedó en `BODY`. No es concluyente, porque la ventana no tenía el foco, pero `autofocus` en elementos que no son controles de formulario no es fiable en todos los navegadores.
  - El orden de los botones es correcto: Intro envía «Guardar cambios». Se ha comprobado en el navegador y no forzó nada.
  - Los errores de validación (`@error` en `:25, 33, 38, 45, 50, 56, 62`) no están enlazados con `aria-describedby` y los campos no llevan `aria-invalid`, como pide la lista de la *skill* `review`. El formulario de alta tiene la misma carencia, pero este archivo es nuevo.
- **Impacto:**
  - Si el foco no llega al aviso, quien usa un lector de pantalla puede no enterarse de que no se ha guardado nada: un `role="alert"` presente al cargar la página no siempre se anuncia.
  - Los errores no se asocian a su campo.
- **Recomendación:**
  - Hacer explícito el foco con un script mínimo (`document.getElementById('slot-warning')?.focus()`), o comprobar a mano, con la ventana enfocada y en Chrome, Firefox y Safari, que `autofocus` funciona.
  - Añadir `aria-invalid="true"` y `aria-describedby="<campo>-error"` cuando haya error, en `edit` y en `create`.
  - Hacer el recorrido con Tab, Shift+Tab e Intro y con un lector de pantalla.

### L7. El aviso no dice si el problema es el horario o la capacidad

- **Estado:** resolved
- **Resolución:** `AvailabilityCalculator::unavailabilityReason()` devuelve el motivo (`UnavailabilityReason`: hora pasada, fuera de horario, reglas de la web, cierre puntual o sin plaza; `isAvailable()` se apoya en él) y `SlotUnavailableException` lo lleva. El aviso del panel muestra el texto del motivo. Se considera «cierre puntual» cuando los cierres por sí solos no dejan plaza en el primer momento sin hueco, y «sin plaza» cuando la ocupan las citas. Tests: `AvailabilityCalculatorTest` › «it says why a time cannot be booked»; `RescheduleAppointmentTest` › «it tells the salon why a time is not available»; `AdminRescheduleAppointmentTest` › «the warning says whether the time is outside opening hours, in a one-off closure or full».
- **Evidencia:** `edit.blade.php:76` muestra siempre «Esa hora cae fuera del horario de apertura o no tiene plaza libre para este servicio». `isAvailable()` devuelve un `bool` y no distingue entre fuera de horario, cierre puntual y capacidad llena.
- **Impacto:** PRF-079 pide «un mensaje que lo explique». Con el mensaje actual, el salón no sabe si basta con cambiar la hora o si el día está cerrado.
- **Recomendación:** que el motor o la Action devuelvan el motivo (fuera de horario, cierre puntual o sin plaza) y que el aviso lo diga. Si no compensa, que el usuario acepte el mensaje genérico y se anote en T017.

### L8. La documentación de las tareas no coincide con la evidencia

- **Estado:** resolved
- **Resolución:** en `.ai/tasks/reservas/index.md`, la fila PRF-072–075 cita `MailBrandingTest` y queda `partial` (falta Gmail/Outlook); PRF-077–087 queda `partial` (falta el recorrido en el navegador); PRF-070 y PRF-071 citan su evidencia real; nueva fila PRF-088 `partial` y tarea T019. T015 cita `tests/Feature/DeployCheckCommandTest.php`.
- **Evidencia:**
  - `.ai/tasks/reservas/index.md`, fila «PRF-072 a PRF-075», cita `MailContentEscapingTest` (que prueba el escape, no la marca) en lugar de `MailBrandingTest`.
  - La misma fila y la de PRF-077 a PRF-086 están en `covered` aunque la revisión visual en Gmail y Outlook y la comprobación en el navegador siguen pendientes, según las propias tareas.
  - El plan de pruebas de T015 cita `tests/Feature/Booking/DeployCheckCommandTest.php`, pero el archivo está en `tests/Feature/DeployCheckCommandTest.php`.
- **Impacto:** la matriz da por cerrados puntos que no lo están.
- **Recomendación:** corregir los nombres de los tests y marcar como `partial` las filas que dependen de comprobaciones manuales pendientes, hasta hacerlas.

### L9. Huecos en los tests de T017

- **Estado:** resolved
- **Resolución:** `tests/Pest.php` añade `sqlWithVisibleLocks()`, que cambia la *grammar* de SQLite por una que escribe el bloqueo como comentario SQL; los tests del bloqueo de `CreateAppointmentTest` y `RescheduleAppointmentTest` comprueban ahora que la primera consulta lleva `for update`, y su comentario dice que eso prueba que se pide el bloqueo y en qué orden, no cómo lo aplica MySQL. Mutación: sin `lockForUpdate()` en `CreateAppointment`, su test falla. Añadidos: cambio solo de servicio que avisa a la clienta, confirmación pendiente seguida de un movimiento (L2), atributos ARIA y orden de los botones (L6).
- **Evidencia:**
  - El test «the row lock on the booking settings is the first query» (`RescheduleAppointmentTest.php`) solo comprueba que la primera consulta contiene `"booking_settings"`. En SQLite la gramática quita `FOR UPDATE`, así que si se borra `lockForUpdate()` el test sigue en verde. `CreateAppointmentTest` tiene la misma limitación.
  - No hay ningún caso que compruebe que cambiar **solo el servicio** (mismo día y hora) envía el correo, aunque forma parte de la decisión 1.
  - El test de fallo de correo («nothing retries it») parte de `customer_notified_at = now()`, así que el cron nunca habría hecho nada. No ejercita la interacción de L2.
  - Ningún test comprueba los atributos de accesibilidad del aviso (`autofocus`/`tabindex`, `aria-describedby`) ni que «Guardar cambios» sea el primer botón de envío del formulario.
- **Impacto:** una regresión en estos puntos no haría fallar la suite.
- **Recomendación:**
  - Comprobar el bloqueo con un *spy* sobre el *builder* (o con `toSql()` en la *grammar* de MySQL), o al menos con una aserción sobre el método `lockForUpdate`.
  - Añadir los casos de cambio de servicio y de confirmación pendiente seguida de un movimiento.
  - Comprobar en el HTML el orden de los botones y los atributos ARIA.

### L10. La sección «Reserva tu cita» de la portada y las FAQ siguen ofreciendo solo teléfono y WhatsApp

- **Estado:** resolved (decisión del usuario, PRF-088 nuevo en la especificación; falta la revisión visual)
- **Resolución:** la sección «Reserva tu cita» de la portada tiene un botón «Reservar online» (dorado) a `route('reservas')`; «Llamar ahora» pasa a botón de contorno y WhatsApp se mantiene. La pregunta frecuente de los datos estructurados de la portada («¿Puedo pedir cita online?») y la de estética («¿Es necesario pedir cita…?») mencionan la reserva online; la de estética la limita a «los servicios que aparecen en nuestra página de reservas», porque el salón decide qué servicios se reservan online. Los textos están en `lang/es/home.php` y `lang/es/servicios.php`. Tests: `BookingLinksAndSeoTest` › «the "Reserva tu cita" section of the home page offers online booking and keeps the phone and WhatsApp» y «the questions about booking mention online booking without promising it for every service». Fuera de alcance: las preguntas frecuentes de la portada solo existen como datos estructurados, sin una sección visible equivalente (ya pasaba antes).
- **Evidencia:**
  - `resources/views/pages/home.blade.php:333-353` («Reserva tu cita en Peluquería Jenver») solo tiene «Llamar ahora» y WhatsApp.
  - `belleza_estetica.blade.php:132` responde «Puedes llamar o escribirnos por WhatsApp» a la pregunta de si hace falta pedir cita.
  - PRF-076 mantiene a propósito los botones de llamar, así que no es un incumplimiento.
- **Impacto:** la sección cuyo título invita a reservar no ofrece la reserva online, y las FAQ no la mencionan.
- **Recomendación:** preguntar al usuario si añadir un «Reservar online» en esa sección y mencionar la reserva online en las FAQ.
