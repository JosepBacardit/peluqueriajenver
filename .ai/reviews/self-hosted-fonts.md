# Revisión: Self-hosted fonts (commit 40bfcd7)

**Objetivo:** dejar de cargar Google Fonts desde `fonts.googleapis.com` y `fonts.gstatic.com` (privacidad: IP del visitante enviada a Google) y servir Playfair Display e Inter desde la propia web (`public/fonts/`), sin cambios visuales ni empeoramiento de LCP/CLS.

**Nota añadida tras el cierre de esta revisión (2026-10-05):** esta revisión marcó el cambio como «listo para merge» sin encontrar nada más grave que M1 y B1. Sin embargo, la web **nunca ha mostrado Playfair Display ni Inter**, ni antes ni después de este commit: un `<style>` crítico sin capa en `layouts/app.blade.php` (líneas ~86-106 de entonces) fijaba `body { font-family: -apple-system… }` y `h1…h6, .font-serif { font-family: Georgia, serif }` de forma literal, nunca `var(--font-sans)`/`var(--font-serif)`. Al no estar dentro de ninguna `@layer`, ese CSS ganaba siempre a las utilidades de Tailwind 4 (que sí van en capas), así que las fuentes se descargaban y se precargaban sin aplicarse jamás. Claude (el coordinador de la tarea) lo detectó inspeccionando el sitio en Chrome, en local y en producción — ni esta revisión ni los tests automatizados (que solo comprueban qué se sirve, no qué fuente computa el navegador) lo detectaron. Queda como lección: una revisión de «no cambia nada visualmente» debería incluir, cuando sea razonable, comprobar en un navegador real qué fuente aplica de verdad el elemento, no solo qué URLs sirve el HTML. Resuelto en la tarea de seguimiento (ver hallazgos M1 y B1 actualizados abajo, y el commit que corrige el `<style>` crítico).

**Resumen de hallazgos:**
- **Críticos:** 0
- **Altos:** 0
- **Medios:** 1
- **Bajos:** 1
- **No findings:** No

---

## Hallazgos

### M1. Cobertura incompleta del test

**Estado:** resolved

**Resolución:** `with()` ampliado a las 9 rutas HTML recomendadas (todas menos `sitemap`, que es XML). Test: `the served HTML never references Google Fonts`, ahora parametrizado con `home`, `contacto`, `cookies`, `privacidad`, `color-mechas`, `corte-tratamientos`, `peinados-eventos`, `belleza-estetica`, `avisos-legales`; las 9 pasan.

**Archivo/línea original:** `tests/Feature/SelfHostedFontsTest.php:17`

**Evidencia:** El test parametrizado cubre solo 4 rutas (`['home', 'contacto', 'cookies', 'privacidad']`), pero el proyecto tiene 9 rutas HTML:
- home (cubierta)
- color-mechas (no cubierta)
- corte-tratamientos (no cubierta)
- peinados-eventos (no cubierta)
- belleza-estetica (no cubierta)
- privacidad (cubierta)
- avisos-legales (no cubierta)
- cookies (cubierta)
- contacto (cubierta)
- sitemap (XML, no aplica)

**Impacto:** Medio. Si se añadieran referencias a Google Fonts en una de las rutas no testeadas, el test no lo detectaría. El riesgo es bajo porque todas heredan el layout `app.blade.php` donde se centraliza la carga de fuentes, pero una regresión en esa herencia no sería detectada por este test.

**Recomendación:** Extender el parámetro `with()` del test para incluir todas las 9 rutas HTML (excepto `sitemap`):
```php
})->with([
    'home',
    'contacto',
    'cookies',
    'privacidad',
    'color-mechas',
    'corte-tratamientos',
    'peinados-eventos',
    'belleza-estetica',
    'avisos-legales'
]);
```

---

### B1. Inter weight 300 incluido pero no usado actualmente

**Estado:** resolved

**Resolución:** ahora que el bug del CSS crítico está corregido (las fuentes sí se aplican de verdad, ver la nota al principio de este informe), se ha vuelto a comprobar `font-light`/`font-weight: 300` en vistas, CSS y JS: sigue sin usarse en ningún sitio. Se elimina el `@font-face` de peso 300 de `resources/css/app.css` y el archivo `public/fonts/inter-latin-300-normal.woff2`. Si en el futuro se necesita un peso más ligero, se puede volver a añadir con el mismo procedimiento (`@fontsource/inter`, subconjunto `latin`).

**Archivo/línea original:** `resources/css/app.css:20-26` y `public/fonts/inter-latin-300-normal.woff2`

**Evidencia:** El archivo `inter-latin-300-normal.woff2` está incluido en el commit y se define en `@font-face` con weight 300, pero no aparece ningún uso de `font-light` (que mapea a weight 300 en Tailwind) en las vistas. Búsqueda realizada:
- Ninguna clase `font-light` en `resources/views/`
- Ningún `[font-weight:300]` en views

**Impacto:** Bajo. El archivo se carga pero no consume ancho de banda significativo (23,916 bytes antes de compresión, muy por debajo del presupuesto de fuentes). Es una inclusión defensiva que permite añadir texto más ligero en el futuro sin redeployed de fuentes. El commit lo menciona en el contexto de "pesos realmente usados", pero Inter 300 está presente como previsión.

**Recomendación:** Documentar en el comentario del CSS que Inter 300 está incluido como previsión para uso futuro (ej. subtítulos más ligeros), o removerlo en el próximo rediseño si no se planea usar. No es urgente cambiar ahora, pero es aclarador.

---

## Verificaciones realizadas

✓ **Referencias vivas a Google Fonts:** Ninguna encontrada en código (solo en `prompt.md`, que es histórico y se acepta). Las búsquedas regex sobre `*.php`, `*.blade.php`, `*.css`, `*.js` retornan solo referencias en el test mismo, que son correctas.

✓ **Cobertura de pesos y estilos:** Los @font-face cubre todos los usados en vistas:
- Inter: 300, 400 (default), 500 (font-medium), 600 (font-semibold) — sí, 300 no aparece pero está
- Playfair Display: 400 (default), 700 (font-bold) — sí, ambos presentes
- Playfair italic 400: eliminado correctamente (no se usa en vistas)
- Inter italic: no incluida (los párrafos italic de home.blade.php usan synthetic italic del navegador)

✓ **Unicode-range y caracteres españoles/catalanes:** El comentario en `app.css:16-18` explica que el subset "latin" de @fontsource cubre:
- Español: á/é/í/ó/ú/ü/ñ (U+00E1, U+00E9, U+00ED, U+00F3, U+00FA, U+00FC, U+00F1)
- Catalán: ç (U+00E7), middot "·" (U+00B7)
- Todos en U+0000–00FF (rango "latin" base)
Sin "unicode-range" explícito en @font-face, el navegador asume cobertura completa, que es seguro para archivos de @fontsource certificados.

✓ **Preload con crossorigin:** Presente en `app.blade.php:83-84`:
```html
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/playfair-display-latin-400-normal.woff2') }}" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/inter-latin-400-normal.woff2') }}" crossorigin>
```
Ambos con `crossorigin` (requerido incluso para mismo origen, o el navegador las busca dos veces).

✓ **Configuración nginx:** `docker/nginx/default.conf:33-41` define bloque `/fonts/` con:
- `Cache-Control: public, max-age=31536000, immutable` (1 año)
- `try_files $uri =404;`
- Comentario explica que filenames ya codifican weight/subset/style, seguro para caché inmutable

Coherente con `NGINX-CACHE-CONFIG.md` (si existe). La producción usa nginx dedicado; cambios potenciales en `/etc/nginx/sites-available/peluqueriajenver.com` (o similar) dependerán de si la configuración se sincroniza con este docker/nginx/default.conf en deploy (no especificado en AGENTS.md sobre deploy).

✓ **Licencias OFL:** Presentes en `public/fonts/OFL-inter.txt` y `public/fonts/OFL-playfair-display.txt`, 93 líneas c/u, contienen el texto completo de SIL Open Font License v1.1 con copyright y disclaimer.

✓ **Calidad del test (`SelfHostedFontsTest.php`):**
- Estructura clara y bien documentada (PHPDoc explica el cambio de privacidad)
- Assertions correctas: `not->toContain('fonts.googleapis.com')`, `not->toContain('fonts.gstatic.com')`
- Assertions positivas: `toContain('/fonts/playfair-display-latin-400-normal.woff2')` y `toContain('/fonts/inter-latin-400-normal.woff2')`
- Parametrizado con `->with()` para múltiples rutas (aunque incompleto, ver M1)
- **Ejecución:** `docker compose exec -T app php artisan test tests/Feature/SelfHostedFontsTest.php` pasa en 1.49s, 4 tests, 20 assertions, sin errores

✓ **Tests generales:** `docker compose exec -T app php artisan test --compact` pasa: 1 skipped, 33 passed, 153 assertions, 10.31s. No regresiones.

✓ **Impacto en LCP/CLS:** No se introduce ningún riesgo:
- Las fuentes preloaded (Playfair 400, Inter 400) son las dos necesarias antes del primer paint del hero
- `font-display: swap` en todos los @font-face permite renderizar fallback mientras cargan (sin FOIT)
- Los preload apuntan a mismo origen, sin latencia de DNS/TLS
- No hay cambio en layout (mismas métricas de fuente, como comenta app.blade.php:86-101 con `/* System font stack with metrics... */`)

---

## Recomendaciones de seguimiento

- ~~Resolver M1 en la próxima tarea: extender test a todas las rutas HTML~~ — hecho.
- ~~Documentar B1 si es intencional (o remover Inter 300 si no es plan del usuario)~~ — hecho, removido.
- **Antes de primer deploy a producción:** confirmar que `/etc/nginx/sites-available/peluqueriajenver.com` (o ruta real en VPS) tiene el bloque `/fonts/` con las mismas directivas Cache-Control e immutable (ver AGENTS.md "Production deploys" y "Before the first real deploy"). Sigue pendiente.

---

**Estado (2026-10-05, agente `programador`, rama `fix/cookie-consent`):** M1 y B1 resueltos, con su evidencia en cada sección. Además se corrigió el bug de fondo que ni esta revisión ni los tests detectaron (ver nota al principio del informe): el `<style>` crítico de `layouts/app.blade.php` fijaba literalmente el respaldo (`Georgia`/sistema) como fuente real de `body` y los encabezados, así que Playfair Display e Inter nunca se aplicaban pese a cargarse. Ahora `body`/`h1`-`h6`/`.font-serif` usan `var(--font-sans)`/`var(--font-serif)`, con `@font-face` de respaldo locales (`Georgia`→Playfair, `Arial`→Inter) con `size-adjust`/`ascent-override`/`descent-override`/`line-gap-override` calculados a partir de `@capsizecss/metrics` para minimizar el salto de layout al intercambiar la fuente. Test de regresión nuevo: `the critical CSS sets body and headings to the font variables, not a hardcoded fallback`. Suite completa en verde (`docker compose exec -T app php artisan test --compact`: 40 passed, 1 skipped, 184 aserciones), Pint en verde y `npm run build` sin errores.
