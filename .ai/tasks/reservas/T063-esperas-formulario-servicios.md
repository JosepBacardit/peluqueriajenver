# T063 — Esperas en el formulario de servicios del panel

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-151, PRF-160
- **Depende de:** T062
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** formulario y validación en Blade sin JS; sin lógica de disponibilidad nueva.
- **Estado:** pending
- **PR / rama:** `feature/service-wait-times`.

## Objetivo

Que el salón configure hasta 2 esperas por servicio y vea cuánta espera incluye cada duración.

## Plan

1. **Tests primero** (`ServiceManagementTest`):
   - guardar 0, 1 y 2 esperas;
   - rechazar con el error junto al campo una espera al principio o al final, fuera de la duración, solapada o pegada con la anterior, no múltiplo de 5, o con solo uno de sus dos campos;
   - si algo no es válido, no se guarda nada.
2. `ServiceRequest`: reglas de `waits.*.start` y `waits.*.minutes`, la validación cruzada con la duración (reutilizando `TimeProfile`) y `serviceAttributes()` con `waits` (o `null`).
3. `services/_form.blade.php`: 2 filas «Espera N: desde el minuto · durante (min)», vacías por defecto y con objetivos táctiles de 44 px.
4. Lista de servicios y alta y edición de citas: «2 h (incl. 45 min de espera)».
5. Comprobación en el navegador a 360 px.
