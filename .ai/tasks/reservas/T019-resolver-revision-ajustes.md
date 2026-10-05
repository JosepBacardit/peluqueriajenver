# T019 — Resolver la revisión de los ajustes del panel y los correos

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-087 y PRF-088 (nuevos), además de PRF-070 a PRF-075 y PRF-077 a PRF-086 (que se refuerzan)
- **Depende de:** T015, T016, T017, T018 y su revisión (`.ai/reviews/booking-admin-tweaks.md`)
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** son correcciones sobre código ya revisado; L3 y L4 tocan la concurrencia y el enlace personal de la clienta.
- **Estado:** done (pendientes las comprobaciones manuales de la matriz, filas `partial`)
- **PR / rama:** `feature/booking-admin-tweaks`

## Objetivo

Resolver los 12 hallazgos de `.ai/reviews/booking-admin-tweaks.md` (M1, M2, L1–L10) con la *skill* `fix-review`. Las decisiones del usuario (2026-10-05): las cuatro decisiones de T017 se mantienen; L3 se resuelve con un token nuevo al cambiar el email (PRF-087); L10 añade la reserva online a la portada y a las preguntas frecuentes (PRF-088). El resto sigue la recomendación del informe.

## Evidencia

Cada hallazgo indica en el registro de la revisión su resolución y los tests que la demuestran. Tests nuevos o ampliados: `MailSenderTest` (nuevo), `MailBrandingTest`, `DeployCheckCommandTest`, `AvailabilityCalculatorTest`, `CreateAppointmentTest` y `RescheduleAppointmentTest` (bloqueo visible con `sqlWithVisibleLocks()` de `tests/Pest.php`), `AdminRescheduleAppointmentTest`, `AdminAppointmentTest` y `BookingLinksAndSeoTest`.

Mutaciones comprobadas a mano (y deshechas): sin `lockForUpdate()` en `CreateAppointment` falla su test del bloqueo; sin la excepción de L1, sin la comprobación de versión (L4) o sin marcar la confirmación (L2) fallan 1, 2 y 2 tests respectivamente.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (344 tests en verde) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios). Navegador (Chrome, `localhost:8082`): los correos 1 y 5 renderizados con una URL de producción de 48 caracteres no desbordan (cuerpo de 570 px a 1024 px de ancho; sin *scroll* horizontal a 375 px) y el logo sale a 124×56. Vistas previas regeneradas en `storage/app/mail-preview/` (no comprometidas; `render-previews.php` las vuelve a generar). El cambio de email no tiene correo propio: reutiliza la confirmación (vista previa 1) con el enlace nuevo.

Pendiente a mano: los correos en Gmail (web y móvil) y Outlook de escritorio (logo y botón), y en el panel el recorrido completo (guardar, aviso con su foco, Tab/Shift+Tab/Intro y lector de pantalla), la sección «Reserva tu cita» de la portada y el aviso de formulario desfasado.
