# T028 — Menú del panel: hamburguesa en vez de barra inferior

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-090 (reescrito), PRF-093 (ajustado: ya no menciona la navegación inferior)
- **Depende de:** T021 (layout con `$modules` e iconos)
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** rehace la navegación móvil del layout compartido por todas las pantallas del panel, con una interacción nueva (abrir/cerrar, teclado) que T021 no tenía.
- **Estado:** done (pendiente comprobar a 375 px y 1024 px en el navegador, lo hace el coordinador)
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

Decisión del usuario, 2026-10-05: el panel va a crecer con más apartados, y una barra inferior fija (T021) no escala bien pasados 5-6 módulos. El menú de PRF-090 pasa a ser un botón hamburguesa en la cabecera que despliega una lista vertical de módulos, desplazable, para que quepan 10-12 apartados sin salirse de la pantalla.

## Cambios

`resources/views/layouts/admin.blade.php`:
- Se quita la barra inferior fija de T021 y el relleno de `<main>` que reservaba su hueco (`pb-[calc(4.5rem+env(safe-area-inset-bottom))] md:pb-8` vuelve a ser `py-8`, como antes de T021).
- Botón hamburguesa nuevo (`#admin-menu-btn`), `md:hidden`, `w-11 h-11` (44×44 px), con `aria-expanded`, `aria-controls="admin-menu"` y `aria-label` que alterna «Abrir menú»/«Cerrar menú»; dos iconos SVG (líneas / aspa) que se alternan con la clase `hidden`.
- Menú desplegable nuevo (`<nav id="admin-menu">`), `md:hidden`, con los cinco módulos (icono + etiqueta, reutiliza los mismos iconos de T021) como enlaces de `min-h-11`, el activo con `aria-current` y en dorado; «Cerrar sesión» va en su propio `<form>`, al final, separado con un borde superior. `max-h-[80vh] overflow-y-auto` para que el menú se desplace internamente si crece a 10-12 apartados.
- **Sin JavaScript:** el `<nav id="admin-menu">` no lleva la clase `hidden` en el marcado y el botón empieza con `aria-expanded="true"`/`aria-label="Cerrar menú"` — es decir, el estado inicial servido por Laravel ya es el menú abierto y visible, sin que ningún botón tenga que revelarlo. Es la opción que pide la condición «sin JS, el menú tiene que seguir siendo accesible»: no depende de que el usuario interactúe con nada, todos los módulos y «Cerrar sesión» están ahí desde el primer HTML.
- Un `<script>` vanilla, igual de pequeño que el de `resources/js/critical.js` (patrón reutilizado: botón + contenedor, `classList.toggle('hidden')`, cerrar al pulsar un enlace), se ejecuta justo después del menú: colapsa el menú al cargar (`setOpen(false)`), conecta el botón para alternarlo, cierra el menú al pulsar cualquier enlace, y escucha `Escape` a nivel de documento para cerrarlo y devolver el foco al botón (`btn.focus()`). No se añadió como entrada nueva de Vite (el panel no tiene ninguna hoy): es un `<script>` inline, igual que el que ya existía en `admin/appointments/edit.blade.php` para el foco del aviso de hueco.
- La única fuente de módulos sigue siendo el array `$modules`: el menú superior de escritorio (sin cambios) y el desplegable móvil iteran el mismo array.

`resources/views/admin/agenda/index.blade.php`:
- El botón flotante «+» ya no reserva el hueco de la barra inferior: `bottom: calc(4.5rem + env(safe-area-inset-bottom))` pasa a `calc(1.5rem + env(safe-area-inset-bottom))`.
- La lista de citas (bloques + citas) se envuelve en un `<div class="pb-24 md:pb-0">` para que el botón flotante no tape la última tarjeta al hacer *scroll* hasta el final, ahora que esa reserva ya no la da el relleno global de `<main>`.

## Evidencia

`tests/Feature/Admin/AdminLayoutTest.php`:
- El test de la barra inferior se sustituye por «the panel shows a hamburger menu for phones with the five modules and the active one marked»: botón de 44×44 con `aria-expanded`/`aria-controls`/`aria-label`, menú con `overflow-y-auto`, los 5 módulos con `min-h-11`, el activo marcado en las dos navegaciones (`aria-current="page"` ×2), «Cerrar sesión» dentro del `<form class="mt-2 border-t...">` al final del menú, los iconos decorativos `aria-hidden`.
- Test nuevo «the dropdown menu is visible by default, so it still works without JavaScript»: el `<nav id="admin-menu">` no lleva `hidden` en su lista de clases (se distingue de `md:hidden`, que sí debe estar) y el botón arranca con `aria-expanded="true"`.

`tests/Feature/Admin/AgendaTest.php`: el test del botón flotante («the agenda has a floating "new appointment" button for phones») sigue en verde sin tocarlo, porque solo comprobaba la clase `md:hidden fixed right-4`, no el valor de `bottom`.

## Verificación

`docker compose exec -u www-data app php artisan test --compact --filter="AdminLayoutTest|AgendaTest"` en verde · suite completa: **368 tests en verde** (1453 aserciones; partía de 367) · `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` → sin cambios · `docker compose exec node npm run build` → sin avisos; comprobado en el CSS compilado: `.w-11{width:calc(var(--spacing) * 11)}`, `.h-11{height:calc(var(--spacing) * 11)}`, `overflow-y:auto` y `max-height:80vh` presentes.

**No se ha reiniciado el contenedor `node`** (pedido explícito del coordinador: la comprobación en el navegador la hace él). `npm run build` se ejecutó como proceso aparte dentro del contenedor (`docker compose exec`), que no reinicia el servidor de desarrollo (`npm run dev`, proceso principal del contenedor).

Pendiente a mano (la hace el coordinador): a 375 px, abrir y cerrar el menú con el botón, con el teclado (Tab hasta el botón, Intro para abrir, Escape para cerrar y comprobar que el foco vuelve al botón), pulsar un enlace y confirmar que el menú se cierra; desactivar JavaScript y confirmar que el menú sigue visible y usable; simular 10-12 módulos (o reducir la altura de la ventana) y comprobar que el menú se desplaza en vez de salirse de la pantalla; a 1024 px, confirmar que vuelve el menú horizontal sin hamburguesa. En la Agenda, confirmar que el botón flotante no tapa la última cita ni queda bajo la zona segura del iPhone.
