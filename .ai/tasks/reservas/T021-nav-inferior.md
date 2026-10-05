# T021 — Navegación inferior fija del panel en móvil

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-090
- **Depende de:** T001 (layout y los cinco módulos)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** cambia la estructura de navegación del layout compartido por todas las pantallas del panel, pero no toca lógica de negocio ni rutas nuevas.
- **Estado:** done (pendiente comprobar a 375 px y 1024 px en el navegador, la hace el usuario)
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

Por debajo de 768 px, el menú superior partía a dos líneas y no estaba pensado para usarse con el pulgar mientras se trabaja con una mano. Se añade una barra de navegación inferior fija con los cinco módulos (icono + etiqueta), visible solo en móvil; el menú superior se mantiene sin cambios a partir de 768 px (decisión del usuario, 2026-10-05, recomendación de la fase 1).

## Cambios

`resources/views/layouts/admin.blade.php`:
- El array `$modules` gana una clave `'icon'` (`agenda`, `services`, `hours`, `blocks`, `settings`), fuente única de rutas y etiquetas para las dos navegaciones.
- El `<nav>` superior pasa de `flex flex-wrap` a `hidden md:flex`: deja de mostrarse en móvil.
- `<nav>` nueva, `md:hidden fixed inset-x-0 bottom-0`, con los cinco módulos como enlaces `flex-1` de 56 px de alto (`min-h-14`), cada uno con un icono SVG de trazo (`aria-hidden="true"`, coherente con el estilo de iconos que ya usa `partials/header.blade.php`) y su etiqueta visible (sirve de nombre accesible); el módulo activo lleva `aria-current="page"` y color dorado, igual que el menú superior. `padding-bottom: env(safe-area-inset-bottom)` evita que el recorte inferior del iPhone tape los iconos.
- `<main>` gana `pt-8 pb-[calc(4.5rem+env(safe-area-inset-bottom))] md:pb-8` (antes `py-8`), para que la barra inferior no tape el final del contenido en ninguna pantalla, incluida la de acceso (sin sesión no hay barra, pero el relleno extra no molesta).

No hay duplicación de rutas ni etiquetas: las dos navegaciones iteran el mismo array `$modules`. Las dos quedan en el DOM a la vez; la que no corresponde al ancho de pantalla tiene `display:none` (`hidden`/`md:hidden`), así que no crea dos landmarks de navegación redundantes para un lector de pantalla.

## Evidencia

`tests/Feature/Admin/AdminLayoutTest.php`, test nuevo «the panel shows a fixed bottom navigation for phones with the five modules and the active one marked»: comprueba que aparecen `hidden md:flex` y `md:hidden`, una sola barra inferior fija, el relleno de zona segura en dos sitios (el estilo de la barra y la clase arbitraria de `<main>`), los cinco iconos `aria-hidden` y que el módulo activo se marca en las dos navegaciones (`aria-current="page"` ×2).

Un primer intento de compilar `resources/css/app.css` reveló un bug real: un comentario CSS que contenía literalmente `*/` dentro del texto («sus propios valores de `px-*/py-*`») cerraba el comentario antes de tiempo y rompía la hoja de estilos compilada (`npm run build` lo señaló como advertencia). Se corrigió reescribiendo el comentario sin esa secuencia; `npm run build` queda limpio.

## Verificación

`docker compose exec -u www-data app php artisan test --compact --filter="AdminLayoutTest"` (10 tests en verde) · suite completa (347 tests en verde) · `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios) · `docker compose exec node npm run build` (sin avisos; `min-height:calc(var(--spacing) * 11)` y `* 14` y `padding-bottom:calc(4.5rem + env(safe-area-inset-bottom))` presentes en el CSS compilado).

Pendiente a mano (la hace el usuario, necesita la sesión del panel): a 375 px, confirmar que la barra inferior no tapa contenido ni queda tapada por la barra de gestos/el recorte del iPhone, que el icono y la etiqueta del módulo activo se ven en dorado, y que a 1024 px vuelve el menú superior sin barra inferior.
