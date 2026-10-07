# T067 — Formulario de tiempos por pasos

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-160 (reescrito), PRF-010 y PRF-011 (la duración ya no se escribe)
- **Depende de:** T063
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** el formulario de esperas de T063 («desde el minuto X, durante Y») confundía a la peluquera. Cambio de formulario y de validación, sin tocar el motor ni los datos.
- **Estado:** done
- **PR / rama:** `feature/service-wait-times`.

## Petición

Decisión del usuario (2026-10-07), literal: «Hay que pensar que las peluqueras no tienen mucho conocimiento de estas herramientas y les tiene que ser muy simple y fácil de entender para no liarse y hacerlo complicado y que al final no lo usen». Diseño aprobado: pasos en el orden del servicio, con la duración total calculada.

```
Trabajo 1: [30] min
Espera 1:  [45] min
Trabajo 2: [45] min
Espera 2:  [  ] min   (opcional)
Trabajo 3: [  ] min   (opcional)
Duración total: 2 h (calculada)
```

## Resolución

- **`ServiceRequest`:**
  - campos `work_1`, `wait_1`, `work_2`, `wait_2` y `work_3` (`STEP_FIELDS`); solo `work_1` es obligatorio;
  - cada uno es un entero de 5 a 600 y múltiplo de 5, con `bail` para dar un solo error por campo y mensajes propios en lenguaje sencillo;
  - en `after()`: no puede quedar un hueco antes de un paso rellenado («Rellena primero …»), el último paso tiene que ser un trabajo («Después de una espera…») y el total no puede pasar de 600;
  - ya no se lee `duration_minutes`: `serviceAttributes()` calcula la duración y las esperas con `TimeProfile::fromSteps()`.
- **`TimeProfile`:** `fromSteps()` (de los pasos a la duración y las esperas) y `steps()` (al revés, para rellenar el formulario al editar).
- **`services/_form.blade.php`:**
  - el campo «Duración» y las filas «desde el minuto» de T063 dejan paso a «Tiempos del servicio», con la ayuda, el ejemplo y un paso por fila;
  - campo grande con `inputmode="numeric"` (teclado numérico en el móvil) y «min» al lado;
  - las esperas van con borde discontinuo y la línea «La peluquera queda libre»;
  - debajo, «Duración total», calculada en el servidor y actualizada en vivo con un pequeño script, que es una mejora progresiva;
  - Precio y Orden pasan a 2 columnas.
- Los datos, el motor, la agenda y los servicios existentes no cambian.

## Plan de pruebas

- `ServiceWaitsFormTest`, reescrito en sus tests de formulario:
  - pasos en orden, con la ayuda y el ejemplo, sin `duration_minutes` ni jerga;
  - guardado sin esperas, con 1 y con 2;
  - pasos calculados al editar;
  - quitar las esperas;
  - 11 errores con su texto exacto en el paso correcto;
  - lo escrito se conserva tras un error, con el error enlazado al campo por `aria-describedby`.
- `ServiceManagementTest`: el envío usa `work_1` en vez de `duration_minutes`.
- Suite completa en verde; Pint pasado sobre los archivos PHP tocados. Pendiente: comprobación del coordinador a 360 px.
