# Revisión independiente: fonts-applied

**Revisor:** Claude Sonnet 5, en un contexto limpio y sin relación con la implementación.
**Código revisado:** rama `fix/cookie-consent`, commit `7caf4d0` («Fix Playfair Display and Inter never actually rendering»), 4 archivos de código (`resources/css/app.css`, `resources/views/layouts/app.blade.php`, `tests/Feature/SelfHostedFontsTest.php`) más `.ai/reviews/self-hosted-fonts.md` (nota añadida a la revisión anterior).
**Objetivo:** que Playfair Display e Inter se apliquen de verdad (nunca lo habían hecho, ni en local ni en producción), sustituyendo el `<style>` crítico sin capa que fijaba literalmente el respaldo del sistema en `body`/`h1`-`h6`/`.font-serif` por `var(--font-sans)`/`var(--font-serif)`; añadir respaldos locales con métricas ajustadas (`@capsizecss/metrics`) para reducir el salto de layout; eliminar Inter 300 (no usado); sin tocar tamaños, colores ni espaciados.

**Nota añadida tras el cierre de esta revisión (2026-10-05):** esta revisión comprobó (línea de evidencia de abajo) que los dos `<link rel="preload" as="font">` apuntaban a archivos que existían en `public/fonts/` en disco (`ls`), pero no comprobó que la URL que de verdad sirve el navegador bajo `npm run dev` resolviera a ese mismo archivo. En desarrollo, `app.css` se sirve desde el origen propio del servidor de Vite (`http://localhost:5175`), así que la `url('/fonts/…')` del `@font-face` se resolvía contra **ese** origen, no contra el de la app (`:8082`), y daba 404 — las seis caras tipográficas quedaban `error`/`unloaded` en `document.fonts`, con los únicos `loaded` siendo los dos respaldos locales. Claude (el coordinador de la tarea) lo encontró inspeccionando `document.fonts` en Chrome contra Docker, no esta revisión ni la suite de tests (que solo comprueba qué URLs sirve el HTML, no si esas URLs responden 200 ni si coinciden con lo que pide el CSS). Resuelto en la tarea de seguimiento: las fuentes se movieron a `resources/fonts/` y pasan por Vite (con hash en build, por su propio origen en dev), para que el `preload` y el `@font-face` siempre pidan la misma URL en los dos modos.

**Evidencia reproducida:**

- `docker compose exec -T app php artisan test --compact`: 40 passed, 1 skipped, 184 aserciones. Sin fallos.
- `docker compose exec -T node npm run build`: build correcto (`public/build/assets/app-C6WOHvLO.css`, `critical-Ch0nz70C.js`), sin errores. Avisos de Vite sobre `/fonts/*.woff2` no resueltos en build time son esperados (son rutas absolutas servidas por nginx, no por Vite).
- `curl http://localhost:8082/`: 200 OK. Los dos `<link rel="preload" as="font">` (`playfair-display-latin-400-normal.woff2`, `inter-latin-400-normal.woff2`) apuntan a archivos que existen en `public/fonts/` (confirmado con `ls`).
- CSS compilado real servido por el Vite dev server (`curl http://localhost:5175/resources/css/app.css`): confirmado que Tailwind 4 emite `@layer theme, base, components, utilities;` y mete `--font-sans`/`--font-serif` dentro de `@layer theme { :root, :host { ... } }`, y las utilidades `.font-sans { font-family: var(--font-sans); }` / `.font-serif { font-family: var(--font-serif); }` dentro de `@layer utilities`. El `<style>` crítico de `app.blade.php` sigue sin capa.
- Razonado sobre la cascada real (CSS Cascading and Layering spec): para declaraciones normales (no `!important`), **toda** regla fuera de cualquier `@layer` tiene prioridad sobre **cualquier** regla dentro de una capa, con independencia del orden de carga o la especificidad. Confirmado también en la práctica: el `:root` crítico sin capa es el que gana siempre para `--font-sans`/`--font-serif`, nunca el `:root` de `@layer theme`.
- `docker compose exec -T app ./vendor/bin/pint --test`: 3 incidencias de estilo, todas en `app/Http/Middleware/CacheHeaders.php`, `bootstrap/app.php` y `routes/web.php` — ninguno de los tres está en el diff de este commit, así que están fuera de alcance.
- `grep` en `resources/views`, `resources/js`, `resources/css` (excluyendo el `welcome.blade.php` genérico de Laravel, no enrutado, ya señalado como obsoleto en `AGENTS.md`): ningún `font-light`, `font-thin` ni `font-weight: 300`; ningún `h1`-`h6` con clase `font-sans`; ningún `style="font-family:…"` inline; ningún `classList` de JS que module fuentes.

No se ha modificado código de aplicación, tests, configuración ni migraciones; solo este informe.

---

## Resumen de hallazgos

- **Críticos:** 0
- **Altos:** 0
- **Medios:** 1
- **Bajos:** 0
- **No findings:** No

---

## Hallazgos

### 1. (Media) `--font-sans` se define con valores distintos en el `:root` crítico y en `@theme`, y la versión de `@theme` es inalcanzable por la cascada

**Estado:** resolved

**Resolución:** las dos listas de `--font-sans` quedan idénticas, carácter a carácter: `'Inter', 'Inter Fallback', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', sans-serif` en ambas (`layouts/app.blade.php` y `resources/css/app.css`). Se eligió la pila crítica como canónica porque es la que de verdad aplica (sin capa, gana siempre). El comentario de `app.css:70-80` deja de afirmar que la copia de `@theme` «hace algo» para `font-sans`/`font-serif`: ahora explica que es inalcanzable mientras exista el `<style>` crítico sin capa, y que se mantiene solo para no divergir en silencio de lo que de verdad se renderiza. Se añadió el mismo razonamiento como comentario en el `<style>` crítico (`app.blade.php`), apuntando hacia `app.css`. Test nuevo: `the two --font-sans/--font-serif declarations stay identical` (`tests/Feature/SelfHostedFontsTest.php`), que lee ambos archivos fuente directamente (sin HTTP, sin depender del modo de Vite) y compara las dos declaraciones carácter a carácter; fallaba antes del arreglo.

**Archivo/línea original:**
- `resources/views/layouts/app.blade.php:131` (crítico, sin capa): `--font-sans: 'Inter', 'Inter Fallback', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', sans-serif;`
- `resources/css/app.css:69` (dentro de `@theme`, que Tailwind 4 compila a `@layer theme`): `--font-sans: 'Inter', 'Inter Fallback', system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';`

**Evidencia:** son dos listas de respaldo distintas a partir del tercer elemento. `--font-serif` sí es idéntica en ambos sitios (`'Playfair Display', 'Playfair Display Fallback', Georgia, serif;`, `app.blade.php:130` y `app.css:70`), así que el problema es solo de `--font-sans`.

Por el comportamiento de CSS Cascade Layers verificado arriba (declaraciones normales fuera de cualquier capa ganan siempre a las de dentro de una capa), el `:root` crítico sin capa es el que decide el valor real de `--font-sans` en todo el sitio — para `body`, para las utilidades `.font-sans`/`font-sans` y para cualquier elemento que herede la variable. El `--font-sans` de `@theme` nunca puede ganar mientras exista el `:root` crítico, así que es código muerto: cambiar su valor no tiene ningún efecto observable.

Esto contradice el comentario que el propio commit añade en `resources/css/app.css:62-68`:
```
 * 'Inter Fallback'/'Playfair Display Fallback' are the metric-adjusted,
 * local-only @font-face rules declared in layouts/app.blade.php's
 * critical <style> (so they're already available before this
 * stylesheet loads); keeping them here too makes the font-sans/
 * font-serif utility classes shrink the same layout shift.
```
La intención declarada es que ambas copias "cuenten" para las utilidades `font-sans`/`font-serif`; en la práctica, para `font-sans`, solo cuenta la copia crítica, y la de `@theme` es ilusoria.

**Impacto:** bajo en la práctica hoy (ambas listas empiezan por `'Inter', 'Inter Fallback'`, así que mientras Inter cargue o el respaldo local a Arial resuelva, el resultado visual es el mismo), pero es exactamente la misma clase de error de cascada que motivó este commit — una regla que parece tener efecto y no lo tiene — reintroducida de forma más sutil. El riesgo real es de mantenimiento: quien edite en el futuro la lista de `@theme` (p. ej. para ajustar el respaldo de emoji, o al quitar algún día el `<style>` crítico) asumirá, razonablemente por el comentario, que está cambiando el comportamiento real, y no será así mientras ambas sigan coexistiendo con valores distintos. En el peor caso extremo (ni `Inter` ni `Inter Fallback` disponibles — fuente bloqueada en red y sistema sin `Arial`/equivalente), el sitio caería en la pila crítica (`-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', sans-serif`) y nunca en la de `@theme` (que incluye además las variantes de emoji `'Apple Color Emoji'` etc.), sin que eso sea una decisión consciente.

**Recomendación:** unificar las dos listas para que sean idénticas carácter a carácter (copiar literalmente la pila crítica a `@theme`, o viceversa), de modo que el comentario de `app.css` deje de ser engañoso y no haya dos fuentes de verdad divergentes para la misma variable. Alternativa más robusta: generar una sola de las dos listas y que la otra la reutilice (no es trivial en Blade/CSS puro, así que la unificación manual con un comentario que explique por qué deben mantenerse iguales es suficiente).

---

## Verificaciones realizadas sin hallazgos

- **`.font-serif` del `<style>` crítico vs. utilidades Tailwind:** no hay ningún elemento `h1`-`h6` con clase `font-sans`, ni ningún elemento con `font-serif` y `font-sans` a la vez; todos los `h1`-`h6` del proyecto llevan ya `font-serif` explícito (redundante con la regla crítica, pero coherente, no conflictivo).
- **Respaldos locales (`src: local('Georgia')` / `local('Arial')`):** correctos para Windows/macOS. En Linux/Android, donde esos nombres de familia suelen no existir, el `@font-face` de respaldo simplemente no resuelve y el navegador continúa con el siguiente elemento de la pila (`Georgia, serif` / el resto de la pila de sistema), que es el comportamiento anterior a este commit — degradación correcta, no hay ruptura, solo se pierde la mejora de CLS en esos sistemas.
- **Inter 300 / `font-light` / `font-thin`:** no aparece en ninguna vista, CSS o JS; su `@font-face` y el `.woff2` correspondiente se han eliminado del commit de forma coherente con esa comprobación.
- **`preload`:** los dos `<link rel="preload" as="font">` apuntan a archivos que existen en `public/fonts/` y se sirven con 200 OK.
- **Tests:** la suite completa pasa (40/41, 1 skipped preexistente y no relacionado); el nuevo test de regresión (`the critical CSS sets body and headings to the font variables, not a hardcoded fallback`) cubre `home` y `contacto`, suficiente porque solo existe un `layouts/app.blade.php` y el `<style>` crítico no está dentro de ninguna `@section` que una vista pueda omitir — las 9 rutas lo heredan igual.
- **Build:** `npm run build` genera el CSS sin errores; los avisos sobre rutas de fuentes no resueltas en build time son esperados (son absolutas, servidas por nginx).

---

## Estado

| ID | Severidad | Estado |
| --- | --- | --- |
| 1 | Media | resolved |

**Resolución (2026-10-05, agente `programador`, rama `fix/cookie-consent`):** hallazgo 1 resuelto, con su evidencia en su sección. Se corrigió además, en la misma tarea, el 404 de las fuentes bajo `npm run dev` que esta revisión no detectó (ver la nota al principio del informe): las fuentes se movieron de `public/fonts/` a `resources/fonts/` y pasan por Vite, así que el `preload` y el `@font-face` piden siempre la misma URL en desarrollo y en build. Suite completa en verde (`docker compose exec -T app php artisan test --compact`: 41 passed, 1 skipped, 199 aserciones), Pint en verde y `npm run build` sin errores; confirmado con `grep` en `public/build/manifest.json` y en el CSS compilado que las URL de fuente y de `preload` coinciden.
