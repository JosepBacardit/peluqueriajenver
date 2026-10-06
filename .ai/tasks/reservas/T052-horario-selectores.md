# T052 — Horario semanal: selectores de hora/minutos y casilla «Cerrado»

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-133, PRF-134
- **Depende de:** ninguna (no toca el modelo de servicios ni de citas)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** el `<input type="time">` no siempre se puede vaciar en el móvil (la rueda del iPhone no ofrece borrar), así que quitar un tramo o cerrar un día puede ser imposible desde ahí.
- **Estado:** done
- **PR / rama:** `feature/opening-hours-ux`, desde `feature/agenda-service-filter` (`1ce486e`)

## Objetivo

Que `/admin/horario` se pueda editar por completo desde un móvil: cada hora se elige con `<select>`, y una casilla «Cerrado» por día sustituye a dejar sus dos tramos vacíos a mano.

## Decisiones del usuario (2026-10-06)

- Hora: «—» más de 07 a 22 (16 opciones). Minutos: 00, 15, 30, 45.
- Elegir una hora sin minutos ya elegidos deja los minutos en «00» por defecto (preseleccionado en el servidor, sin JavaScript).
- Un tramo se quita poniendo su hora en «—»; el valor de los minutos deja de importar.
- Si solo uno de los dos extremos de un tramo está vacío: «Elige inicio y fin, o deja los dos en —».
- Casilla «Cerrado» por día: desactiva sus dos tramos (JavaScript mínimo, solo visual) y, si está marcada al guardar, no se guarda ningún tramo de ese día, estén rellenos o no (gana siempre en el servidor, nunca solo en el cliente). Un día sin tramos al cargar la muestra ya marcada.

## Implementación

`OpeningHoursRequest`:

- Los campos pasan de `days.{weekday}.{0|1}.{opens|closes}` (`HH:MM`) a `days.{weekday}.{0|1}.{opens|closes}_hour` (`''` o `'07'`–`'22'`) y `..._minute` (`'00'|'15'|'30'|'45'`), más `days.{weekday}.closed` (booleano). `Rule::in()` sobre las 17 horas y las 4 cifras de minutos sustituye al `regex` de múltiplos de 5: ya no hace falta, las únicas opciones posibles ya son múltiplos de 15.
- Un tramo se reconstruye a `HH:MM` solo si su hora no es `''`; si lo es, se descarta entero (el minuto no se mira).
- Si `days.{weekday}.closed` es verdadero, el día entero se ignora en `ranges()`: no se valida ni se guarda ningún tramo suyo, pase lo que pase en los campos de hora/minuto (un intento de manipular la petición no puede reactivar un día marcado cerrado).
- `dayError()` sigue comprobando, por tramo: ambos extremos vacíos o ambos rellenos (si no, el nuevo mensaje), fin posterior a inicio, y el tramo 2 sin solape con el 1 (empieza cuando termina o después) — misma lógica de antes, solo sobre los `HH:MM` ya reconstruidos.

`OpeningHoursController::edit()`: por cada día, además de sus tramos (ya partidos en hora/minuto para preseleccionar cada `<select>`), calcula `closed = count(tramos) === 0`.

`admin/opening-hours/edit.blade.php`: la casilla «Cerrado» primero, con una etiqueta visible; cada tramo con 4 `<select>` (hora/minuto de inicio, hora/minuto de fin) con su propio `aria-label` («{Día}, tramo N, hora de inicio», etc.) y el error del día con `aria-describedby`. Un script mínimo deshabilita visualmente los `<select>` de los dos tramos cuando se marca «Cerrado» (y al cargar, si ya está marcada) — solo para que no se pueda interactuar con lo que no se va a guardar; la regla real está en el servidor. Anchos de columna reducidos (`w-14`/`w-12`) para que los 4 `<select>` de un tramo, en dos columnas por día, no fuercen *scroll* horizontal a 360 px (PRF-096, ya cubierto, se reafirma con un test).

## Plan de pruebas

`tests/Feature/Admin/OpeningHoursTest.php` (nuevo):

- guardar un horario partido (dos tramos) con los `<select>`;
- quitar un tramo poniendo su hora en «—» (el minuto que tuviera no importa);
- «Cerrado» marcada → no se guarda ningún tramo de ese día, aunque sus `<select>` llegaran con horas manipuladas;
- un día sin tramos al cargar → la casilla sale marcada;
- un tramo con solo un extremo → el mensaje nuevo, nada se guarda;
- el tramo 2 solapando o antes de que acabe el tramo 1 → rechazado, nada se guarda;
- una hora o un minuto fuera de las opciones (manipulado) → rechazado;
- 360 px: ninguna fila produce *scroll* horizontal (PRF-096).

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `vendor/bin/pint --test` sobre los archivos tocados · `npm run build`. Sin datos en MySQL: el horario real lo pondrá el usuario después.

## Fuera de alcance

Que la web pública lea este horario (T053).
