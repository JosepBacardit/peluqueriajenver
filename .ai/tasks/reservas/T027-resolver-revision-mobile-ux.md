# T027 — Resolver la revisión de la adaptación móvil

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-095 (reforzado); sin puntos nuevos, los hallazgos N1-N4 del coordinador son mejoras de UX dentro del alcance ya cubierto por PRF-089, PRF-092, PRF-096 y PRF-098
- **Depende de:** T020–T026 y su revisión (`.ai/reviews/mobile-admin-ux.md`)
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** son correcciones puntuales sobre código ya revisado, en varios archivos, con la *skill* `fix-review`.
- **Estado:** done (pendiente la comprobación en el navegador, la hace el coordinador)
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

Resolver los 4 hallazgos de `.ai/reviews/mobile-admin-ux.md` (M1, L1, L2, L3) y los 4 hallazgos que el coordinador encontró comprobando el panel y la web pública a 375 px en el navegador, con la sesión del usuario (N1-N4), aprobados por el usuario.

## Hallazgos de la revisión independiente

- **M1.** `customerWhatsappUrl()` no retiraba el prefijo internacional `00` (`0034...` generaba `wa.me/0034...`, un enlace roto). Ahora detecta `00` o `+` al principio del teléfono ya recortado y los retira del valor completo antes de extraer los dígitos; solo si no había ningún prefijo reconocido y quedan 9 dígitos o menos se antepone `34`. `app/Models/Appointment.php`.
- **L1.** El enlace de WhatsApp de la agenda llevaba solo `rel="noopener"`, a diferencia del resto del sitio. Ahora `rel="noopener noreferrer"`.
- **L2.** Faltaba un test de que «Mañana» sigue siendo el día siguiente a **hoy** al ver un día distinto. Añadido; pasó sin tocar la implementación (ya era correcta).
- **L3.** Los tests de zona táctil comprueban clases, no píxeles reales. Anotado en el informe de revisión, sin cambio de código: la comprobación real en el navegador la hace el coordinador.

## Hallazgos del coordinador en el navegador (N1-N4), aprobados por el usuario

- **N1.** Llamar y WhatsApp eran enlaces de texto de 21 px dentro de la línea de datos de cada cita. Ahora son botones (`btn-outline`, ≥44 px) en la misma fila que «Editar»/«Cancelar cita» (rejilla de 2×2 a 375 px, hasta 4 en una fila en pantallas más anchas). El teléfono sigue visible como texto. Una cita cancelada conserva Llamar/WhatsApp pero no Editar/Cancelar. «Cancelar cita» sigue en rojo y no se confunde con las demás.
- **N2.** La navegación de días ocupaba dos líneas a 375 px. «← Día anterior»/«Día siguiente →» pasan a botones de solo icono («←»/«→») con `aria-label`, junto con «Hoy»/«Mañana» en una sola fila; el selector de fecha con «Ir» pasa a su propia fila debajo.
- **N3.** Los `input type="date"`/`"time"`/`"datetime-local"` medían 34-31 px. Suben a `py-3` (≈44 px): el selector de fecha de la agenda, los `time` de Horario, y el `$inputClass` compartido de Nueva cita, Editar cita y Cierres (afecta también a los campos de texto de esos formularios, por compartir la misma clase — ver justificación en `.ai/reviews/mobile-admin-ux.md`, sección N3).
- **N4.** La casilla «Sí, quiero cancelar esta cita» medía 20 px. Toda la fila pasa a `min-h-11`, con la casilla a `w-5 h-5` y `accent-gold`.

## Cambios

- `app/Models/Appointment.php`: `customerWhatsappUrl()` reescrito (M1).
- `resources/views/admin/agenda/index.blade.php`: fila de acciones con Llamar/WhatsApp/Editar/Cancelar en rejilla (N1, incluye L1); navegación de días compacta con iconos y `aria-label` (N2); `py-3` en el selector de fecha (N3).
- `resources/views/admin/opening-hours/edit.blade.php`: `py-3` en los `input type="time"` (N3).
- `resources/views/admin/appointments/create.blade.php`, `edit.blade.php`, `admin/blocks/index.blade.php`: `$inputClass` con `py-3` (N3).
- `resources/views/pages/cita.blade.php`: fila de la casilla de confirmación con `min-h-11` y casilla más grande (N4).

## Evidencia

Tests nuevos: `tests/Feature/Admin/AgendaTest.php` (prefijo `00`, 3 casos; «Mañana» con `?fecha=` distinto de hoy; Llamar/WhatsApp como botones con el teléfono visible; cita cancelada sin Editar/Cancelar; navegación de días con iconos; selector de fecha a 44 px), `tests/Feature/Admin/OpeningHoursManagementTest.php` (`time` a 44 px), `tests/Feature/Admin/AdminAppointmentTest.php` (campos de Nueva/Editar cita a 44 px), `tests/Feature/Admin/ScheduleBlockManagementTest.php` (campos de Cierres a 44 px), `tests/Feature/Booking/CustomerAppointmentTest.php` (fila de confirmación a 44 px con casilla más grande). 12 tests nuevos.

## Verificación

`docker compose exec -u www-data app php artisan test --compact` → **367 tests en verde** (1439 aserciones; partía de 355). `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` → sin cambios. `docker compose exec node npm run build` → sin avisos; comprobado en el CSS compilado: `.accent-gold{accent-color:var(--color-gold)}`, `.w-11{width:calc(var(--spacing) * 11)}` y la rejilla de 4 columnas (`grid-template-columns:repeat(4,minmax(0,1fr))`) presentes. `docker compose restart node` ejecutado para que el servidor de Vite en desarrollo sirva el CSS nuevo (en Windows no detecta los cambios solo); confirmado que el contenedor arrancó limpio (`VITE ready`, sin error).

Pendiente: la comprobación en el navegador la hace el coordinador, como en el resto de T020-T026.
