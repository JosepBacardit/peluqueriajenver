# T068 — Resolver la revisión de las esperas

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-155, PRF-159 y PRF-160 (ajustados)
- **Depende de:** T066
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** resolver los 6 hallazgos de `.ai/reviews/service-wait-times.md` con las decisiones del usuario.
- **Estado:** done
- **PR / rama:** `feature/service-wait-times`.

## Resolución

La resolución de cada hallazgo, con su evidencia, está en el propio registro de revisión. En resumen:

- **M1:**
  - `novalidate` en los formularios de alta y edición de servicio;
  - `ServiceRequest::prepareForValidation()` trata un 0 en cualquier paso salvo «Trabajo 1» como vacío;
  - el error de hueco pasa a «Escribe los minutos de la espera 1, o deja vacíos los pasos de después.».
- **L1:** `after()` solo se detiene si hay errores en los pasos.
- **L2:** «(1/2)» se pone delante del nombre.
- **L3:** la etiqueta de un servicio que la cita ya tenía usa la copia congelada.
- **L4:** el error del total queda enlazado al `<fieldset>` y a cada paso.
- **L5:**
  - `TimeProfile::storedWaits()` lee de forma tolerante, con un `Log::warning`, en `fromServices()` y `fromAppointment()`;
  - `ServiceCatalogSeeder` pone `waits => null` al renombrar.

Tests nuevos: `tests/Feature/Booking/ServiceWaitTimesReviewTest.php`. Además se ajustan `ServiceWaitsFormTest` (mensajes de hueco, 0 en la espera y etiqueta congelada) y `WaitTimesAgendaTest` («(1/2) Ana»). Suite completa en verde y Pint pasado.
