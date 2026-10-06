# Revisión independiente: Google Maps gated behind click consent

**Revisor:** Claude Haiku 4.5, en un contexto limpio.
**Código revisado:** `fix/cookie-consent`, commit `c51fdeb` («Gate Google Maps embeds behind a "Ver mapa" click»), 7 archivos (`resources/views/layouts/app.blade.php`, `resources/views/pages/contacto.blade.php`, `resources/views/pages/cookies.blade.php`, `resources/views/pages/home.blade.php`, `resources/views/partials/footer.blade.php`, `resources/views/partials/google-map-embed.blade.php`, `tests/Feature/GoogleMapsEmbedTest.php`).
**Objetivo aprobado:** que los iframes de Google Maps no se carguen ni conecten con Google hasta que el visitante pulse «Ver mapa»; un solo componente y un solo script; `data-embed-src` solo sale del servidor, no entrada de usuario.

**Evidencia reproducida:**

- `docker compose exec -T app php artisan test --compact`: 28 tests en verde, 1 omitido (pre-existente). 130 aserciones. Incluye los 3 nuevos tests en `tests/Feature/GoogleMapsEmbedTest.php` (home, contacto, cookies) y los 8 tests de `CookieConsentTest.php`, sin regresiones.
- `docker compose exec -T app php artisan test tests/Feature/GoogleMapsEmbedTest.php --compact`: confirmado que los tres tests pasan (0.54s, 0.27s, 0.33s respectivamente).
- Búsqueda de `<iframe ... src="https://www.google.com/maps/embed"` en el HTML servido: solo `data-embed-src=` encontrado (sin `src=` directo en un `<iframe>`). No hay preconnect ni dns-prefetch a `google.com/maps`.
- Búsqueda de `data-google-map`: confirmadas 3 inclusiones del partial (home, contacto, footer) y el listener delegado en `app.blade.php:209-237` que cubre todas ellas.
- XSS: `data-embed-src` viene de Blade, con URLs hardcodeadas en los parámetros de la inclusión del partial, nunca de entrada del usuario.
- Idempotencia: `container.querySelector('iframe')` retorna en línea 216 si ya existe uno, previniendo duplicados en clics múltiples.
- CLS: contenedor con `style="height: {{ $height }}px;"` fijo en líneas 20-21 de `google-map-embed.blade.php`.

No se ha modificado código de aplicación, tests, configuración ni migraciones; solo este informe.

---

## Hallazgos

### 1. (Media) El iframe carece de atributo `title` requerido para accesibilidad WCAG 2.1

**Estado:** resolved

**Resolución:** `iframe.title = 'Mapa de ubicación de Peluquería Jenver';` añadido antes de `container.appendChild(iframe)` (`layouts/app.blade.php`), igual para los tres mapas (misma dirección en los tres). Además, para que el foco no quede perdido cuando el botón «Ver mapa» desaparece, se añade `iframe.addEventListener('load', function () { iframe.focus(); });` antes de insertarlo: el foco pasa al iframe en cuanto termina de cargar. Test: `the dynamically created map iframe gets an accessible title and drops the dead loading attribute` (`tests/Feature/GoogleMapsEmbedTest.php`), que comprueba que el script contiene `iframe.title = 'Mapa de ubicación de Peluquería Jenver'`; fallaba antes del arreglo.

**Impacto original:** WCAG 2.1, criterio 2.4.1 (Bypass Blocks), requiere que todo `<iframe>` tenga un `title` o `aria-label` descriptivo para que los lectores de pantalla comuniquen su propósito. Sin él, el iframe es invisible para usuarios de asistencia (lectores de pantalla, navegación por teclado, etc.). Cuando el usuario pulsa «Ver mapa», aparece un iframe anónimo sin nombre, sin contexto.

**Lugar:** `resources/views/layouts/app.blade.php:220-231`.

**Recomendación:** añadir `iframe.title = 'Mapa de ubicación de Peluquería Jenver';` o similar (en español, lo que sea coherente con el resto del sitio) antes de `container.appendChild(iframe)` en línea 231. Alternativamente, pasar el título como parámetro a través de `data-embed-title` en el contenedor y leerlo en el script.

---

### 2. (Baja) El atributo `loading='lazy'` en un iframe creado dinámicamente no tiene efecto práctico

**Estado:** resolved

**Resolución:** eliminada la línea `iframe.loading = 'lazy';` en `layouts/app.blade.php`; el resto de atributos del iframe (`referrerPolicy`, `allowfullscreen`, estilos) se mantienen sin cambios. Test: el mismo de arriba comprueba también que el script ya no contiene `iframe.loading = 'lazy'`.

**Impacto original:** ninguno: el iframe se inserta en el DOM solo cuando el usuario hace clic, momento en el cual los navegadores lo cargarán inmediatamente de todas formas, independientemente de `loading='lazy'`. El atributo no causa roturas, solo no hace nada.

**Lugar:** `resources/views/layouts/app.blade.php:228`.

**Recomendación:** remover `iframe.loading = 'lazy';` para evitar código muerto. O dejar como está; es inofensivo y la línea es legible.

---

## Resumen

- ✓ Ningún iframe renderizado en el servidor HTML servido.
- ✓ Script seguro contra XSS, idempotente con clics múltiples, sin duplicados.
- ✓ No hay regresiones en consentimiento de analítica (8/8 tests en CookieConsentTest pasan).
- ✓ CLS evitado con altura fija en el placeholder.
- ✓ Política de cookies actualizada correctamente.
- ✓ **Hallazgo media (1) resuelto:** iframe con `title` y foco movido al iframe tras cargar.
- ✓ **Hallazgo baja (1) resuelto:** `loading='lazy'` eliminado del iframe dinámico.

**Resolución (2026-10-05, agente `programador`, rama `fix/cookie-consent`):** los 2 hallazgos resueltos, con su evidencia en cada sección. Suite completa en verde (`docker compose exec -T app php artisan test --compact`: 29 passed, 1 skipped, 133 aserciones) y Pint en verde en los archivos tocados.
