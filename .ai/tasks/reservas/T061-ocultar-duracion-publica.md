# T061 — Ocultar la duración de los servicios en todo lo público

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-149, PRF-150 (y PRF-027, PRF-039, PRF-050, PRF-051, PRF-082, PRF-127 y PRF-130 ajustados)
- **Depende de:** T049
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** quitar una presentación de un dato que ya existe en varias vistas y correos; sin lógica nueva, pero son varios archivos.
- **Estado:** done
- **PR / rama:** `feature/hide-public-service-duration`.

## Resolución

Petición del dueño del proyecto, transmitida por el salón: la duración de los servicios (y, con ella, la hora de fin de la cita, que la delataría) deja de verse en todo lo que ve la clienta, y sigue siendo un dato interno para calcular huecos (`AvailabilityCalculator`, `DayTimeline`) y para el panel.

- `resources/views/pages/reservas.blade.php`: quitado `duration_label` de cada casilla del paso 1, el atributo `data-minutes` (también del código fuente, no solo de la pantalla), la vista previa en vivo (`#service-total-preview` y su `@include` de `partials/service-total-script.blade.php`) y el total de duración de la cabecera del paso 2/3. El parcial de script sigue intacto para el panel (`admin/appointments/create.blade.php` y `edit.blade.php`, que lo siguen incluyendo).
- `resources/views/pages/cita.blade.php`: cada servicio se lista solo por nombre; quitada la línea de «Duración total».
- `resources/views/mail/partials/appointment-services.blade.php`: nuevo parámetro `showDuration` (por defecto `false`, vía `@php($showDuration ??= false)`) en vez de duplicar el parcial. Con `false` lista solo los nombres; con `true`, el formato de siempre (nombre y duración de cada uno, más el total).
- `resources/views/mail/new-appointment.blade.php` y `resources/views/mail/customer-cancelled-appointment.blade.php` (los 2 correos al salón): pasan `['showDuration' => true]` al incluir el parcial. Sin cambios en su «Hora: H:i–H:i» (hora de fin), que ya era un dato exclusivo de estos dos correos.
- `resources/views/mail/appointment-confirmed.blade.php`, `appointment-cancelled.blade.php` y `appointment-rescheduled.blade.php` (los 3 correos a la clienta): sin cambios en el `@include` — usan el valor por defecto `false`.
- Sin cambios en `App\Models\Service`, `App\Models\Appointment`, `AvailabilityCalculator`, `DayTimeline` ni en ninguna vista de `admin/` (catálogo, alta/edición de cita, agenda): siguen mostrando la duración exactamente igual.

Tests: reescritas las aserciones que exigían ver la duración en público en `PublicBookingTest`, `MultiServiceBookingTest` y `MultiServiceDisplayTest` (el test de los 5 correos se separó en dos: 3 a la clienta sin duración, 2 al salón con duración, sin cambios). Nuevo `tests/Feature/Booking/HidePublicServiceDurationTest.php` con el criterio de regresión explícito: ni la duración ni la hora de fin aparecen en `/reservas`, en `/cita/{token}` ni en los 3 correos a la clienta (incluido el código fuente, sin `data-minutes`), y los 2 correos al salón y el panel las siguen mostrando.

## Objetivo

Que la clienta nunca pueda saber cuánto dura un servicio (por ejemplo, que un balayage son 2 horas) ni deducirlo por la hora de fin de su cita, mientras el salón sigue viéndolo donde lo necesita: para calcular huecos y en su propio panel/correos.

## Plan

1. **Tests primero:** reescribir las aserciones públicas existentes que esperaban ver la duración (`PublicBookingTest`, `MultiServiceBookingTest`, `MultiServiceDisplayTest`) para que esperen su ausencia; separar el test parametrizado de los 5 correos en dos (clienta sin duración / salón con duración). Añadir `HidePublicServiceDurationTest` con el criterio de regresión.
2. **Implementación:** los cambios de vistas y del parcial de correo descritos arriba.
3. Ejecutar Pest completo y Pint.

## Plan de pruebas

- `/reservas` (pasos 1, 2 y 3) y `/cita/{token}` con 2 servicios: ni «min» ni «h» de duración, ni `data-minutes`, en el HTML.
- Los 3 correos a la clienta: solo nombres, sin duración.
- Los 2 correos al salón: duración de cada servicio, el total y la hora de fin, sin cambios.
- El panel (catálogo, alta/edición de cita, agenda): duración visible, sin cambios.
