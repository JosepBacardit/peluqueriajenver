# Revisión independiente: cookie-consent

**Revisor:** Claude Sonnet 5, en un contexto limpio y sin relación con la implementación.
**Código revisado:** `main...fix/cookie-consent`, commit `1aaf0d7` («Gate GTM, GA4 and Ahrefs behind cookie consent»), 6 archivos (`lang/es/navigation.php`, `resources/views/layouts/app.blade.php`, `resources/views/pages/cookies.blade.php`, `resources/views/partials/cookie-banner.blade.php`, `resources/views/partials/footer.blade.php`, `tests/Feature/CookieConsentTest.php`).
**Objetivo:** bloquear GTM (`GTM-NP6KXF9K`), GA4 y Ahrefs Analytics hasta que `localStorage.cookieConsent === 'accepted'`, sin Consent Mode, con decisión reversible.

**Evidencia reproducida:**

- `docker compose exec -T app php artisan test --compact`: 21 tests en verde, 1 omitido (pre-existente, no relacionado). 95 aserciones.
- `curl http://localhost:8082/` sobre la app en Docker: confirmado que el HTML servido no contiene `googletagmanager.com/ns.html` (noscript eliminado) y que los tres scripts (GTM, GA4, Ahrefs) están envueltos en el chequeo `localStorage.getItem('cookieConsent') === 'accepted'`.
- Trazado manual de los cuatro flujos (sin decisión, aceptar, rechazar sin haber aceptado antes, rechazar tras haber aceptado, reabrir desde pie/página de cookies): el orden de carga de scripts (`<head>` registra y potencialmente dispara el loader de GTM antes de que exista el banner; el bloque al final de `<body>` registra el de GA/Ahrefs antes de `DOMContentLoaded`) garantiza que ambos *loaders* ya están en `window.__analyticsConsentLoaders` cuando el usuario puede pulsar un botón, y cada *loader* tiene su propio flag `loaded` que lo hace idempotente ante clics repetidos o reaperturas del banner.
- Comprobado en `app/Http/Middleware/CacheHeaders.php:22-27`: el HTML de `/` y `/cookies` se cachea igual que antes (`max-age` de 1 o 7 días); la decisión de consentimiento vive solo en `localStorage` del cliente, nunca en el HTML generado por el servidor, así que el cacheo no interfiere.
- Verificado con búsqueda web que el comportamiento por defecto de `gtag.js` sin `cookie_domain` explícito es `cookie_domain: 'auto'`, que fija la cookie `_ga`/`_ga_<id>` en el dominio raíz (p. ej. `peluqueriajenver.com`) y no en el subdominio completo (`www.peluqueriajenver.com`), para que sea compartida entre subdominios (fuente: documentación de Google, "Configure and customize cookies").
- Verificado con búsqueda web que Ahrefs Web Analytics se anuncia como «cookie-less»: no fija cookies ni almacena identificadores persistentes (fuente: Ahrefs Help Center, "About Ahrefs Web Analytics").

No se ha modificado código de aplicación, tests, configuración ni migraciones; solo este informe.

---

## Hallazgos

### 1. (Alta) La política de cookies no divulga Ahrefs como servicio independiente y lo clasifica, junto con GTM, como «cookies de publicidad» de forma inexacta

**Estado:** resolved

**Resolución:** el usuario confirma que el contenedor `GTM-NP6KXF9K` solo tiene configurada la etiqueta de Google Analytics (sin tags de publicidad). Se elimina la categoría «Cookies de Publicidad» de `cookies.blade.php` y del banner; Google Tag Manager se describe ahora como la herramienta que carga Google Analytics (`cookies.blade.php:67-70`, «Google Tag Manager es la herramienta que carga Google Analytics en nuestro sitio. El contenedor que usamos (GTM-NP6KXF9K) solo tiene configurada esta etiqueta de analítica, no etiquetas de publicidad.»). Ahrefs Analytics pasa a tener su propio apartado en «Tipos de Cookies» («Herramientas de Análisis sin Cookies», `cookies.blade.php:32-35`) y en «Servicios Terceros» (`cookies.blade.php:72-75`, con enlace a `https://ahrefs.com/privacy-policy`), siempre diciendo que **no instala cookies** y que se activa solo con consentimiento porque envía los datos de la visita a un tercero — nunca que «instala» nada. El párrafo de «Tu Consentimiento» (`cookies.blade.php:85-86`) y el texto del banner (`cookie-banner.blade.php:8`) se reescriben con la misma distinción. Tests: `the cookies policy drops the advertising category and never claims Ahrefs installs a cookie` y `the cookie banner no longer mentions an advertising cookie category` (`tests/Feature/CookieConsentTest.php`), ambos fallaban antes del arreglo.

**Evidencia original:**

- `resources/views/pages/cookies.blade.php:27-35` («Tipos de Cookies que Utilizamos») solo tiene párrafos para «Cookies de Análisis» (Google Analytics) y «Cookies de Publicidad» (Google Tag Manager). Ahrefs no aparece en ninguno de los dos, ni tiene su propio párrafo.
- `resources/views/pages/cookies.blade.php:60-75` («Servicios Terceros») solo lista Google Analytics, Google Tag Manager y Google Maps. Ahrefs tampoco aparece ahí, pese a ser uno de los tres servicios que el propio commit empieza a cargar de forma condicionada.
- La única mención a Ahrefs en todo el archivo está en el párrafo nuevo de «Tu Consentimiento» (`cookies.blade.php:81`): *"Las cookies de análisis (Google Analytics) y de publicidad (Google Tag Manager, Ahrefs Analytics) solo se instalan si pulsas «Aceptar»"* — lo cataloga como cookie de publicidad y afirma que "se instala".
- Pero Ahrefs Web Analytics es, por diseño, *cookieless*: no fija cookies, no usa identificadores persistentes y no rastrea entre sitios (confirmado arriba). Afirmar que es una "cookie" que "se instala" al aceptar es una descripción inexacta de lo que realmente pasa — justo el criterio que pide esta revisión.
- Además, es un servicio de analítica de tráfico, no de publicidad; agruparlo bajo «cookies de publicidad» junto a GTM no es correcto aunque sí se gatee por la misma razón práctica (cautela regulatoria, no porque instale una cookie publicitaria).
- La clasificación de GTM como «publicidad» (`cookies.blade.php:32-35`, texto preexistente no tocado por este commit salvo la frase añadida) tampoco se puede verificar desde este repositorio: GTM-NP6KXF9K solo se carga como contenedor vacío aquí — GA4 y Ahrefs se cargan directamente, no a través de tags de GTM — así que lo que realmente hace ese contenedor (y qué cookies fija) depende de la configuración en el panel de Google Tag Manager, invisible desde el código.

**Impacto:** la AEPD exige identificar cada servicio de terceros, su finalidad y qué instala realmente. Un visitante que lea la política no se entera de que Ahrefs se usa en el sitio (salvo esa única frase de refilón), y se le dice que instala una cookie de publicidad cuando en realidad no instala ninguna cookie. Es un texto legal que no describe fielmente lo que ocurre, en el propio documento que este commit se propuso corregir.

**Recomendación:** añadir un párrafo propio para Ahrefs en «Tipos de Cookies» (o crear una categoría «Cookies/herramientas de analítica sin cookies» si se prefiere ser precisos) y en «Servicios Terceros» con enlace a su política de privacidad; no llamarlo «cookie» si no fija ninguna, y aclarar que se gatea por cautela aunque no requiera técnicamente consentimiento de cookies. Confirmar con el usuario qué tags concretos corren dentro de GTM-NP6KXF9K antes de seguir llamándolo «publicidad» sin más.

---

### 2. (Media) El borrado de `_ga*` al rechazar tras haber aceptado probablemente no borra la cookie real, porque no cubre el dominio donde `gtag.js` la fija por defecto

**Estado:** resolved

**Resolución:** `deleteGoogleAnalyticsCookies()` (`cookie-banner.blade.php:62-80`) ahora calcula el dominio raíz a partir de `location.hostname.replace(/^www\./, '')` (sin escribir ningún dominio a mano) e intenta el borrado sin atributo `domain`, con `host`, `.host`, y además con `rootHost`/`.rootHost` cuando `rootHost` difiere de `host` (es decir, cuando el host empezaba por `www.`). En `localhost` (sin `www.`), `rootHost === host` y solo se prueban las tres variantes de siempre, sin romper nada. Test: `clearing analytics cookies computes the root domain instead of hardcoding it` (`tests/Feature/CookieConsentTest.php`), que comprueba que el script usa `replace(/^www\.` y que no contiene el dominio de producción escrito a mano; fallaba antes del arreglo.

**Evidencia original:**

- `resources/views/partials/cookie-banner.blade.php:40-51`:
  ```js
  function deleteGoogleAnalyticsCookies() {
      document.cookie.split(';').forEach(function (entry) {
          const name = entry.split('=')[0].trim();
          if (name.indexOf('_ga') !== 0) {
              return;
          }
          const expired = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
          document.cookie = expired;
          document.cookie = expired + '; domain=' + location.hostname;
          document.cookie = expired + '; domain=.' + location.hostname;
      });
  }
  ```
  Los tres intentos de borrado usan `location.hostname` (p. ej. `www.peluqueriajenver.com`) tal cual, con o sin punto inicial. Nunca se intenta con el dominio raíz sin `www.` (`.peluqueriajenver.com`).
- `resources/views/layouts/app.blade.php:146,152` configura `gtag('config', 'G-EX4HPXH0WV')` sin pasar `cookie_domain`, así que usa el valor por defecto `'auto'`. Por diseño de Google (confirmado arriba), eso hace que `_ga`/`_ga_<id>` se fijen en el dominio raíz (`peluqueriajenver.com`), no en `www.peluqueriajenver.com`, precisamente para compartir la cookie entre subdominios.
- `document.cookie = 'nombre=; domain=.www.peluqueriajenver.com; ...'` no borra una cookie fijada con `Domain=.peluqueriajenver.com`: el navegador solo permite fijar/borrar una cookie para el dominio exacto del documento o un dominio padre de este, y `.peluqueriajenver.com` no es ninguno de los tres dominios que el código prueba.

**Impacto:** el requisito explícito del usuario era "al rechazar tras aceptar, borrar `_ga*` y recargar". Si la cookie real vive en el dominio raíz (el caso más probable con la configuración actual), el código no la borra: tras rechazar, el seguimiento deja de ejecutarse (correcto), pero la cookie `_ga`/`_ga_<id>` sigue en el navegador del visitante, contradiciendo tanto el requisito como lo que la política de cookies promete ("al rechazar... eliminamos las cookies de Google Analytics ya instaladas", `cookies.blade.php:84`).

**Recomendación:** añadir también el dominio raíz sin `www.` a la lista de intentos de borrado, p. ej. derivándolo de `location.hostname.replace(/^www\./, '')` con un punto inicial, o replicar el propio algoritmo "auto" de Google probando progresivamente desde el hostname completo hacia arriba. Confirmarlo abriendo el sitio real con las herramientas de desarrollador (Application → Cookies) tras aceptar y comprobar en qué dominio exacto aparece `_ga`.

---

### 3. (Baja) Ningún acceso a `localStorage` está protegido; si el navegador lo bloquea, los botones Aceptar/Rechazar quedan inertes

**Estado:** resolved

**Resolución:** todos los accesos a `localStorage` quedan envueltos en `try/catch`. En `cookie-banner.blade.php`, `getConsent()`/`setConsent()` (líneas 37-52) devuelven `null`/`false` si `localStorage` lanza, en vez de propagar la excepción; el banner siempre se oculta tras pulsar un botón (`cookieBanner.classList.add('hidden')` ya no depende de que `setConsent` tenga éxito) y los *loaders* de analítica solo se ejecutan si `setConsent` devolvió `true` (consentimiento confirmado de verdad). En `layouts/app.blade.php` (líneas 23-30 y el bloque dentro del listener de `load`), la misma guarda decide `consentAccepted = false` ante cualquier excepción, así que un `localStorage` bloqueado nunca carga GTM, GA4 ni Ahrefs. Test: `every localStorage access in the consent banner and layout is guarded with try/catch` (`tests/Feature/CookieConsentTest.php`), que cuenta al menos 4 bloques `try`/`catch (e)` en el HTML servido; fallaba antes del arreglo (0 ocurrencias).

**Evidencia original:** `resources/views/partials/cookie-banner.blade.php:35,55,65` y `resources/views/layouts/app.blade.php:23,178` llaman a `localStorage.getItem`/`setItem` sin `try/catch`. Si el acceso a `localStorage` lanza (algunos navegadores lo bloquean por completo cuando el usuario desactiva todas las cookies/datos de sitio en la configuración, no solo en modos privados antiguos), la excepción no capturada corta la ejecución del resto del *handler*: en `acceptBtn`/`rejectBtn` eso significa que `localStorage.setItem` falla antes de ocultar el banner o de llamar a los *loaders*, y en el `DOMContentLoaded` de `cookie-banner.blade.php` significa que ni siquiera se habrían registrado los *listeners* de `data-cookie-settings` si el fallo ocurriera antes (no es el caso aquí porque el `getItem` de la línea 35 ya lanzaría primero).

**Impacto:** en el peor caso, el efecto es seguro desde el punto de vista de privacidad (no se activa ningún rastreador sin consentimiento), pero el banner queda visualmente bloqueado para siempre y el visitante no puede cerrarlo ni aceptar ni rechazar — una regresión de usabilidad, no de cumplimiento. No hay test que cubra este caso porque Pest no ejecuta JS.

**Recomendación:** envolver las llamadas a `localStorage` en `try/catch`, tratando una excepción como "sin decisión" (no cargar nada) pero permitiendo igualmente ocultar el banner o mostrar un aviso, para no dejarlo atascado.

---

### 4. (Baja) No se puede verificar desde el repositorio si el contenedor GTM fija cookies adicionales no cubiertas por el borrado de `_ga*`

**Estado:** resolved

**Resolución:** el usuario confirma que el contenedor `GTM-NP6KXF9K` solo tiene configurada la etiqueta de Google Analytics (sin remarketing, Google Ads ni otras herramientas), así que no hay otras cookies de GTM que `deleteGoogleAnalyticsCookies()` deba cubrir aparte de `_ga*`. Queda documentado en `cookies.blade.php:69` («El contenedor que usamos (GTM-NP6KXF9K) solo tiene configurada esta etiqueta de analítica, no etiquetas de publicidad»), que también resuelve el hallazgo 1. Sin cambio de código adicional para este hallazgo.

**Evidencia original:** `GTM-NP6KXF9K` se carga como contenedor vacío desde este código (`resources/views/layouts/app.blade.php:4-27`); qué *tags* corren dentro (p. ej. remarketing, conversiones de Google Ads, u otra herramienta) se configura en el panel de Google Tag Manager y no es visible aquí. Si alguno de esos *tags* fija cookies con un prefijo distinto de `_ga` (p. ej. `_gcl_au`, `IDE`, `test_cookie`), `deleteGoogleAnalyticsCookies()` (`cookie-banner.blade.php:40-51`) no las tocaría.

**Impacto:** posible cumplimiento incompleto del borrado prometido si GTM tiene configurados *tags* que fijan cookies propias; no se puede confirmar ni descartar sin acceso al panel de GTM o sin inspeccionar el sitio real con el inspector de cookies tras aceptar.

**Recomendación:** antes de dar por cerrado este cambio, abrir el sitio real (o el contenedor de pruebas de GTM), aceptar cookies y revisar en las herramientas de desarrollador qué cookies aparecen además de `_ga*`; ampliar `deleteGoogleAnalyticsCookies()` si aparece alguna.

---

## Comprobado sin hallazgos

- **Orden de carga `<head>`/banner:** los dos bloques que registran *loaders* en `window.__analyticsConsentLoaders` se ejecutan como scripts síncronos durante el parseo (uno en `<head>`, otro justo antes de `</body>`), ambos antes de que pueda dispararse cualquier clic del usuario; no hay ventana en la que un clic en "Aceptar" encuentre el array vacío.
- **Idempotencia:** tanto `loadGoogleTagManager` como `loadDeferredAnalytics` usan un flag `loaded` local a su clausura; reabrir el banner y volver a pulsar "Aceptar", o que "load" dispare después de un "Aceptar" manual, no duplica la inyección de scripts.
- **Ningún rastreador se ejecuta sin consentimiento almacenado:** confirmado leyendo las tres rutas de carga (carga inicial condicionada por `localStorage` en `<head>` y en el listener de `load`, y carga inmediata solo desde los manejadores de clic) y confirmado en el HTML real servido por `curl` en Docker.
- **El noscript de GTM se eliminó** y no reaparece en el HTML servido (grep sobre `curl` real y sobre los tests).
- **Aceptar no recarga; rechazar tras haber aceptado sí recarga; rechazar sin haber aceptado antes no recarga** — las tres ramas del código en `cookie-banner.blade.php:53-73` se corresponden exactamente con las decisiones del usuario.
- **Carga diferida (LCP) intacta:** GA4/Ahrefs siguen esperando al evento `load` cuando no hay decisión previa o cuando ya estaba aceptado; solo se adelantan al clic cuando el usuario interactúa explícitamente.
- **Reapertura del banner:** tanto el enlace nuevo del pie (`footer.blade.php:95`, con `event.preventDefault()` para no navegar) como el botón de `cookies.blade.php:86-88` usan el mismo atributo `data-cookie-settings` y el mismo listener delegado; ambos reabren el banner existente sin duplicar marcado.
- **Dificultad de rechazar vs. aceptar:** ambos botones son `<button>` de igual tamaño (`px-5 py-2`), uno relleno y otro con borde (`.btn-gold`/`.btn-outline`, `resources/css/app.css:16-22`); no hay truco visual que haga "Rechazar" más difícil de encontrar o pulsar.
- **Caché de 7 días y decisión en cliente:** confirmado que `CacheHeaders` no participa en la decisión; todo vive en `localStorage`, por lo que el cacheo del HTML no puede servir una decisión equivocada a otro visitante.
- **Suite de tests:** `docker compose exec -T app php artisan test --compact` → 21 passed, 1 skipped (preexistente, no relacionado), 95 aserciones. Los 4 tests nuevos de `tests/Feature/CookieConsentTest.php` pasan y comprueban algo real y específico (no solo que la página cargue): que el bloque `<script>` de cada rastreador contiene el *guard* de consentimiento, que el noscript ha desaparecido, que el marcado de reapertura existe en home y en `/cookies`, y que la frase de consentimiento implícito ya no aparece. Son tests de estructura HTML, coherente con la limitación documentada de que Pest no ejecuta JS; una mejora opcional (no bloqueante) sería acotar con una expresión regular que capture el propio `if (...) { ... }` en vez de solo comprobar que la subcadena del *guard* aparece en el mismo bloque `<script>`.
- **Coherencia con `privacidad.blade.php`:** no contradice lo nuevo; ya mencionaba de forma genérica "Datos de cookies y análisis de navegación" sin entrar en detalle, y no se tocó en este commit.

## Estado

| ID | Severidad | Estado |
| --- | --- | --- |
| 1 | Alta | resolved |
| 2 | Media | resolved |
| 3 | Baja | resolved |
| 4 | Baja | resolved |

**Resolución (2026-10-04, agente `programador`, rama `fix/cookie-consent`):** los 4 hallazgos resueltos, con su evidencia en cada sección. Suite completa en verde (`docker compose exec -T app php artisan test --compact`: 25 passed, 1 skipped, 112 aserciones) y Pint en verde en los archivos tocados.
