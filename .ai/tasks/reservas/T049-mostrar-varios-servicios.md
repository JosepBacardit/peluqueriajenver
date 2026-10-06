# T049 — Mostrar varios servicios en correos, página de la cita y agenda

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-130 (y PRF-039, PRF-050, PRF-051 y PRF-082 ajustados)
- **Depende de:** T046
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** presentación sobre datos que ya existen; no hay lógica nueva.
- **Estado:** pending
- **PR / rama:** `feature/multi-service-appointments`

## Objetivo

Que la clienta y el salón vean todos los servicios de una cita y la duración total.

## Interfaz de T046 que hay que usar

- `$appointment->items` (`service_name` y `duration_minutes` de cada servicio, en orden), `$appointment->durationMinutes()` y `$appointment->services_label`.
- **Nunca** `price_cents`: ni de los servicios de la cita ni del `Service`.

## Plan

1. **Correos (los 5) y `/cita/{token}`:** «Servicios:» con la lista y la duración de cada uno, más «Duración total». Con un solo servicio, como ahora. El escape y la marca siguen igual (`MailContentEscapingTest`, `MailBrandingTest`).
2. **Aviso al salón** (`new-appointment`, `customer-cancelled-appointment`): la lista completa.
3. **Agenda:**
   - las tarjetas muestran `services_label` y la duración total;
   - los bloques de la rejilla siguen con `services_label` recortado (`truncate`), con `title` y `aria-label` completos, que ya lo llevan;
   - **sin** `with('items')` en las consultas de la agenda: el resumen basta y el número de consultas no cambia.
4. Regenerar las vistas previas de `storage/app/mail-preview/` (con `render-previews.php`) con una cita de 2 servicios.

## Plan de pruebas

- los 5 correos y `/cita` con 2 servicios: los nombres y la duración total, sin precio;
- la tarjeta y el bloque de la rejilla con el resumen;
- `MailContentEscapingTest` con un nombre de servicio que contenga Markdown.