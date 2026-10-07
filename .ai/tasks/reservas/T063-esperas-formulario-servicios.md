# T063 — Esperas en el formulario de servicios del panel

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-151, PRF-160
- **Depende de:** T062
- **Modelo:** Claude Opus 5.5 (continuación de T062 en el mismo agente) · **Esfuerzo:** `medium`
- **Motivo:** formulario y validación en Blade sin JS; sin lógica de disponibilidad nueva.
- **Estado:** done
- **PR / rama:** `feature/service-wait-times`.

## Objetivo

Que el salón configure hasta 2 esperas por servicio y vea cuánta espera incluye cada duración.

> **Sustituido en el formulario por T067 (2026-10-07):** la peluquera se liaba con «desde el minuto X, durante Y». El formulario pasa a pasos en orden (Trabajo · Espera · Trabajo…). El resto de esta tarea sigue igual: la lista, el alta y la edición de citas con «incl. N min de espera», la no revelación pública y los datos guardados.

## Resolución

- `ServiceRequest`:
  - reglas `waits` (`array:0,1`), `waits.*.start` (5-595) y `waits.*.minutes` (5-590), múltiplos de 5, con `required_with` entre los dos campos de la misma fila;
  - nombres de los campos en español en `attributes()`;
  - en `after()`, una vez válido cada campo por sí solo: cada espera termina antes del final del servicio (error en `waits.N.minutes`) y la segunda empieza después de que termine la primera (error en `waits.1.start`);
  - `serviceAttributes()` guarda las filas rellenas con `TimeProfile::waitsForStorage()`, o `null`.
- `services/_form.blade.php`: grupo «Esperas (opcional)» con una explicación breve (exposición mínima, entre dos tramos de trabajo) y 2 filas con campos numéricos y `step="5"`. Cada error va junto a su campo con `aria-invalid` y `aria-describedby`. No usa JavaScript.
- `Service`: `formatDurationWithWait()` («2 h, incl. 45 min de espera», o solo «45 min» sin espera) y el atributo `duration_with_wait_label`. `TimeProfile`: `waitMinutes()`.
- Lista de servicios (`services/index`) y alta y edición de citas (`appointments/create`, `edit`):
  - cada servicio y la duración total muestran la espera incluida;
  - en la edición, los servicios que la cita ya tenía cuentan sus esperas congeladas;
  - las casillas llevan `data-wait-minutes` (solo en el panel), y `partials/service-total-script` añade la espera al total en vivo.
- Lo público no cambia: `/reservas` no contiene «espera», `data-wait-minutes` ni la duración (test).

Tests: `tests/Feature/Admin/ServiceWaitsFormTest.php` (22). Suite completa: 823 en verde. Pint pasado en los archivos PHP tocados. Pendiente: comprobar el formulario a 360 px en el navegador.

## Plan original

1. **Tests primero** (`ServiceManagementTest`):
   - guardar 0, 1 y 2 esperas;
   - rechazar con el error junto al campo una espera al principio o al final, fuera de la duración, solapada o pegada con la anterior, no múltiplo de 5, o con solo uno de sus dos campos;
   - si algo no es válido, no se guarda nada.
2. `ServiceRequest`: reglas de `waits.*.start` y `waits.*.minutes`, la validación cruzada con la duración (reutilizando `TimeProfile`) y `serviceAttributes()` con `waits` (o `null`).
3. `services/_form.blade.php`: 2 filas «Espera N: desde el minuto · durante (min)», vacías por defecto y con objetivos táctiles de 44 px.
4. Lista de servicios y alta y edición de citas: «2 h (incl. 45 min de espera)».
5. Comprobación en el navegador a 360 px.
