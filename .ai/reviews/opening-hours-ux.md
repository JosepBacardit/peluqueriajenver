# Revisión independiente: horario del panel, interruptor de reserva online y caché ETag (T052–T055)

- **Alcance:** `git diff feature/agenda-service-filter...feature/opening-hours-ux` (6 commits: `3a4e2cb`, `cbbd22e`, `4581b07`, `39ab30d`, `45f7d36`, `175484d`), contrastado con `.ai/specs/reservas.md` (PRF-133 a PRF-142) y `.ai/tasks/reservas/T052-horario-selectores.md` a `T055-cache-html-etag.md`.
- **Skill:** `review`.
- **Fecha:** 2026-10-06.
- **Revisor:** Claude Sonnet 5, en un contexto limpio. No ha participado en la implementación.
- **Tests:** `docker compose exec -T -u www-data app php artisan test --compact` → **670 passed (2521 assertions)**, 320s, código de salida 0. (Aviso no relacionado: `file_put_contents(.../pest/.temp/test-results)` con permiso denegado, no afecta al resultado.)
- **Estilo:** `vendor/bin/pint --test` sobre los 16 archivos de aplicación y tests tocados → `PASS`.
- **Build:** `docker compose exec -T node npm run build` → compila sin errores.
- **Comprobación real con `curl`** contra `http://localhost:8082/`, `/contacto`, `/reservas`, `/admin`, `/sitemap.xml`, `/api/...` (ver evidencia en M1): confirma `no-cache, private` + `ETag` en las páginas públicas, 304 con `If-None-Match` reutilizando la cookie de sesión, estabilidad del `ETag` entre peticiones aunque la cookie de sesión/CSRF rote, y que `/admin`, `/reservas` siguen con `no-store` sin `ETag`.

## Resumen

| Severidad | Nº | Resueltos |
| --- | --- | --- |
| Critical | 0 | — |
| High | 0 | — |
| Medium | 2 | 2 |
| Low | 3 | 3 (L3 justificado, no corregido) |

**Los 5 hallazgos están resueltos (2026-10-06), ver el estado de cada uno más abajo.**

- **M1.** Las peticiones `HEAD` a una página pública cacheable reciben `no-store` sin `ETag`, no `no-cache, private` + `ETag` como las peticiones `GET` a la misma URL.
- **M2.** Ningún test ejercita el interruptor «Reserva online activa» a través del formulario real de Ajustes (`BookingSettingsController`); el único test que lo cambia lo hace directamente sobre el modelo, y el test que guarda los demás ajustes nunca comprueba que la casilla sobrevive intacta.
- **L1.** La eficacia del `ETag` depende de que ninguna página pública cacheada incluya nunca contenido dependiente de sesión (CSRF, `old()`, avisos); hoy es cierto pero nada lo protege para el futuro.
- **L2.** `Cache-Control: no-cache, private` (no `public`) no está justificado explícitamente en la spec/tarea para esta decisión concreta.
- **L3.** La vista «Mes» de la agenda pasa a hacer una consulta a `booking_settings` que antes no hacía, solo para el aviso del interruptor.

---

## Medium

### M1. Las respuestas `HEAD` a una página pública se saltan la rama de `no-cache` + `ETag`

- **Estado:** resuelto (2026-10-06). `CacheHeaders` admite `HEAD` en la rama de PRF-141 — pero hacerlo así, sin más, hacía que el `ETag` saliera siempre `"d41d8cd98f00b204e9800998ecf8427e"` (el `md5` de una cadena vacía): `Illuminate\Routing\Router::prepareResponse()` llama a `Response::prepare($request)` **antes** de que este middleware (global, el más externo) vea la respuesta, y `prepare()` vacía el cuerpo en una petición `HEAD` (RFC 2616 §14.13) — para entonces ya no hay nada que resumir. La solución cambia la petición a `GET` solo para la llamada a `$next($request)` (así `prepare()` no vacía nada y el cuerpo real llega al middleware), calcula el `ETag` de ese cuerpo real, restaura el método a `HEAD` y vacía el cuerpo él mismo al final (conservando `Content-Length`, igual que habría hecho `prepare()`). `tests/Feature/Booking/BookingPagesCachingTest.php` («a HEAD request gets the same no-cache + ETag as the equivalent GET», «HEAD /reservas keeps the no-store policy») comprueba que `HEAD` y `GET` a la misma URL devuelven exactamente el mismo `Cache-Control` y `ETag`, que el cuerpo de la respuesta `HEAD` sigue vacío, y que `HEAD /reservas` sigue sin `ETag` y con `no-store`.
- **Evidencia:**
  - `app/Http/Middleware/CacheHeaders.php:27` — la rama nueva de PRF-141 exige `$request->getMethod() === 'GET'`:
    ```php
    elseif ($request->getMethod() === 'GET' && ! $request->isJson() && ! $this->isApi($path)) {
        $response->header('Cache-Control', 'no-cache, private');
        if ($response->isSuccessful()) {
            $response->setEtag(md5($response->getContent()));
            $response->isNotModified($request);
        }
    }
    ```
    Una petición `HEAD` (que Laravel enruta igual que `GET`, pero `$request->getMethod()` sigue devolviendo `HEAD`) no entra aquí y cae en la rama `else`, pensada para la API.
  - Reproducido contra el contenedor local (`feature/opening-hours-ux` en el working tree):
    ```
    $ curl -sI -X HEAD http://localhost:8082/
    HTTP/1.1 200 OK
    Cache-Control: must-revalidate, no-cache, no-store, private
    Pragma: no-cache
    Expires: 0
    (sin ETag)
    ```
    frente a la misma URL con `GET`:
    ```
    $ curl -sD - -o /dev/null http://localhost:8082/
    HTTP/1.1 200 OK
    Cache-Control: no-cache, private
    ETag: "7b510bcef11f6da92d37172d38c31018"
    ```
  - `NGINX-CACHE-CONFIG.md` (este mismo PR) documenta la verificación en producción precisamente con `curl -I https://www.peluqueriajenver.com/` y da por hecho que devuelve `Cache-Control: no-cache, private` y un `ETag` — con el código actual, ese comando no reproduce lo que el propio documento dice que debería ver.
- **Impacto:** inconsistencia entre `GET` y `HEAD` sobre la misma URL (PRF-141 no distingue método, solo «páginas HTML públicas»); un monitor de uptime, un *prefetch* o cualquier verificación con `curl -I`/`HEAD` (incluida la que pide el propio `NGINX-CACHE-CONFIG.md` actualizado en este PR) ve `no-store` y ningún `ETag`, contradiciendo la documentación que este mismo cambio añade. No afecta a `deploy.sh` (usa `curl` sin `-I`, método `GET`).
- **Recomendación:** quitar la condición de método (o añadir `|| $request->getMethod() === 'HEAD'`) para que `HEAD` reciba el mismo tratamiento que `GET` en esa rama; añadir un test (`BookingPagesCachingTest`) que compruebe `HEAD /` y `HEAD /contacto`.

### M2. El interruptor «Reserva online activa» no se prueba a través de su propio formulario

- **Estado:** resuelto (2026-10-06). `tests/Feature/Admin/BookingSettingsManagementTest.php` añade tres tests que pasan por `PUT admin.settings.update` (`BookingSettingsController`/`BookingSettingsRequest` reales, nombres de campo del formulario): «saving the other settings through the real form, with "online_booking_enabled" absent, leaves the switch as it was» (sigue `true`), «the hidden "0" (box unchecked) turns online booking off through the real form» y «the checkbox at "1" turns online booking back on through the real form».
- **Evidencia:**
  - `tests/Feature/Booking/OnlineBookingToggleTest.php:18` — la única forma de desactivar la reserva en todo el archivo es `disableOnlineBooking()`, que escribe directamente el modelo: `BookingSetting::current()->update(['online_booking_enabled' => false]);`. Ningún test de ese archivo hace un `PUT` a `route('admin.settings.update')` con el campo `online_booking_enabled`.
  - `tests/Feature/Admin/BookingSettingsManagementTest.php` — es el único archivo que llama a `admin.settings.update` (`grep -rln "admin.settings.update" tests/` → un solo resultado). Su helper `bookingSettingsPayload()` (líneas 13-24) nunca incluye `online_booking_enabled`, y el test `'a salon user can change the booking settings'` (líneas 38-46), que sí guarda ajustes sin tocar la casilla, no comprueba en ningún momento el valor de `online_booking_enabled` después del `PUT`.
  - El código de `BookingSettingsController::update()` (`$request->has('online_booking_enabled') ? $request->boolean(...) : $settings->online_booking_enabled`) y el patrón del campo oculto (`resources/views/admin/settings/edit.blade.php:49`) son correctos por lectura — pero PRF-137 («Guardar los demás ajustes sin tocar la casilla no debe desactivarla por sorpresa») y el propio mecanismo de encendido/apagado de PRF-138 no tienen ninguna prueba que pase realmente por `StoreBookingRequest`/`BookingSettingsRequest`/`BookingSettingsController` con los nombres de campo reales del formulario (`online_booking_enabled=0` con el oculto, `online_booking_enabled=1` con la casilla marcada).
- **Impacto:** si alguien retira el campo oculto de la vista, invierte la condición en el controlador, o rompe la regla `sometimes|boolean`, ningún test actual lo detectaría — toda la cobertura de T054 pasa por delante de ese camino sin ejercitarlo.
- **Recomendación:** añadir a `BookingSettingsManagementTest.php` (o a `OnlineBookingToggleTest.php`) un test que haga `PUT admin.settings.update` con `online_booking_enabled` ausente tras haberlo puesto a `true` (y compruebe que sigue `true`), y otro que lo mande en `0` (oculto solo) y compruebe que pasa a `false`, y de vuelta a `1` que pasa a `true`.

---

## Low

### L1. El `ETag` depende, sin red de seguridad, de que ninguna página pública cacheada lleve contenido de sesión

- **Estado:** resuelto (2026-10-06). `app/Http/Middleware/CacheHeaders.php` documenta la asunción junto a la rama de PRF-141 (y referencia los tests que la protegen). `tests/Feature/Booking/BookingPagesCachingTest.php` añade: «no public cached page embeds a CSRF token or a session id (L1)» (sobre `/`, `/contacto` y las 4 páginas de servicio, busca `name="_token"` y `csrf-token`) y «the same session gets the exact same ETag on repeated visits (L1)» (tres peticiones seguidas a la misma URL, mismo `ETag` las tres veces).
- **Evidencia:** verificado hoy que ninguna de las páginas que entran en la rama de `CacheHeaders` (`/`, `/contacto`, páginas de servicio, legales, `sitemap.xml`) usa `@csrf`, `csrf_token()`, `old()`, `session()` ni `$errors` (`grep -rn "csrf_token\|@csrf" resources/views/pages resources/views/partials resources/views/layouts` solo encuentra `cita.blade.php`, `reservas-form.blade.php` y `layouts/admin.blade.php`, todos ya excluidos por `isApi()`). Confirmado también en caliente: repetir `GET /` con el mismo *cookie jar* devuelve siempre el mismo `ETag` aunque `XSRF-TOKEN`/la cookie de sesión cambien de valor en cada respuesta (cifrado con IV nuevo cada vez).
- **Impacto:** el diseño actual es correcto, pero nada lo hace valer: una página pública futura que añada un formulario, un *flash* `session('status')` o un *nonce* de CSP dejaría de servir nunca un 304 (el `ETag` cambiaría en cada petición) sin que ningún test lo señale — solo se notaría como una pérdida silenciosa de eficacia de caché, no como un fallo.
- **Recomendación:** un comentario en `CacheHeaders` junto a `isApi()` explicando esta asunción, y/o un test que renderice `/`, `/contacto` y las páginas de servicio dos veces en la misma sesión y compruebe que el `ETag` no cambia, para que romper la asunción rompa un test en vez de pasar inadvertido.

### L2. `private` en vez de `public` no está documentado como decisión explícita para esta caché en concreto

- **Estado:** resuelto (2026-10-06). `app/Http/Middleware/CacheHeaders.php` explica junto a la rama de PRF-141 por qué `private`: cada respuesta lleva `Set-Cookie` de sesión, así que una caché compartida nunca debe reutilizar la copia de un visitante para otro; y que, si algún día se pone un CDN delante de nginx, habría que revisar esto a propósito, no dejar que `private` lo bloquee en silencio.
- **Evidencia:** `app/Http/Middleware/CacheHeaders.php:28` pone `Cache-Control: no-cache, private`. PRF-141 y T055 fijan «`no-cache` (sin `max-age` largo) con `ETag`» pero no dicen `public` ni `private`. `private` es una elección razonable (cada respuesta lleva `Set-Cookie` de sesión/CSRF, verificado con `curl`), pero impide que cualquier caché compartida (CDN, proxy de un ISP) revalide con 304 en nombre de varios visitantes — cada visitante repite la petición completa a PHP-FPM.
- **Impacto:** ninguno hoy (AGENTS.md no describe ningún CDN delante de nginx), pero si en el futuro se pone uno, `private` seguiría impidiendo que ese CDN cachee el HTML, sin que quede constancia de que fue una elección y no un descuido.
- **Recomendación:** una frase en el comentario de `CacheHeaders` (o en PRF-141) explicando que es `private` porque la respuesta siempre lleva `Set-Cookie`, para que quien lo lea después no tenga que volver a investigarlo.

### L3. La vista «Mes» de la agenda gana una consulta a `booking_settings` que antes no hacía

- **Estado:** resuelto, justificado (no corregido) (2026-10-06). No es trivial quitarla: el aviso «Reserva online desactivada» está pensado para verse también en Mes (una peluquera que cambia de pestaña no debería dejar de verlo), y mostrarlo ahí necesita esta misma lectura sin importar cómo se organice el código — moverla dentro del `match()` solo trasladaría la misma consulta a otra línea, no la ahorraría. `app/Http/Controllers/Admin/AgendaController.php` documenta esto junto a la lectura de `$settings`.
- **Evidencia:** `app/Http/Controllers/Admin/AgendaController.php:64` — `$settings = BookingSetting::current();` se calcula antes del `match ($vista) { ... }`, incluso para `'mes' => $this->monthData($month)`, que nunca usaba `BookingSetting` antes de este cambio (solo `dayData()`/`weekData()` la leían, y solo cuando se ejecutaban). El test de PRF-102 (`AgendaMonthViewTest.php:92`, «one aggregate query... not one per day») solo cuenta consultas a `from "appointments"`, así que no detecta este añadido.
- **Impacto:** mínimo — una fila, una consulta de más por carga de la vista Mes — y el propio T054 solo prometía no tocar el recuento de PRF-109/PRF-118 (Día/Semana), no el de Mes. No es un incumplimiento de ningún PRF, solo una pequeña ineficiencia no mencionada en la tarea.
- **Recomendación:** ninguna acción obligatoria; si se quiere un recuento de consultas estricto también en Mes, mover la lectura de `$settings` dentro del `match` para los brazos `'semana'`/`default` únicamente y leer `onlineBookingEnabled` por separado solo para la vista Mes (una lectura igual de barata, pero sin fingir que es la «misma» reutilizada).

---

## Lo que no se ha podido comprobar desde aquí (sin tocar MySQL ni crear sesión admin)

- **M2** se podría confirmar en caliente iniciando sesión en `/admin/ajustes` y enviando el formulario real con la casilla desmarcada/marcada, pero este revisor no tiene credenciales del panel y tiene prohibido crear una cuenta o tocar datos de MySQL para obtenerlas; queda como recomendación de test, no como hecho verificado en vivo.
- La `ALTER TABLE` manual en la base local de desarrollo (T054) y el estado de los datos existentes (4 servicios, 5 tramos, 2 usuarios) no se han vuelto a comprobar: no se ha consultado MySQL en esta revisión, siguiendo la instrucción de no tocar sus datos. Si se quiere una verificación independiente de esto, haría falta levantar el permiso para un `SELECT` de solo lectura.

## Para comprobar en Chrome (coordinador)

- `/admin/horario` a 360 px: que los 4 `<select>` de un tramo envuelvan sin *scroll* horizontal, que la casilla «Cerrado» deshabilite visualmente (atenuado, no solo mediante el atributo) los `<select>` de su día al marcarla con el ratón/teclado, y que el foco por Tab recorra los `<select>` en un orden lógico.
- `/admin/ajustes`: la casilla «Reserva online activa» alcanza 44 px de zona táctil, tiene anillo de foco visible y se activa con Espacio además de con el clic.
- `/admin/agenda`: el aviso ámbar «Reserva online desactivada» es legible con buen contraste sobre el fondo oscuro, en escritorio y en móvil, y su enlace a Ajustes es alcanzable por teclado.
- `/reservas` con el interruptor desactivado, a 360 px: los botones «Llamar»/«WhatsApp» no se solapan ni fuerzan *scroll* horizontal, y el emoji de cada botón no es el único indicio de cuál es cuál (ya lleva texto, comprobar que se lee bien con el lector de pantalla de VoiceOver/TalkBack si es posible).
- Cabecera (escritorio y móvil) y las 4 páginas de servicio con la reserva desactivada: que el CTA «Llamar ahora →» quepa sin romper el diseño donde antes decía «Reservar cita →» (texto más largo en algunos idiomas/tamaños de letra podría desbordar en el menú móvil).
- Pestaña Red de las herramientas de desarrollo en `/` y `/contacto`: confirmar visualmente el 304 en una recarga normal (no forzada) y que Chrome no sigue mostrando una copia de hace días por alguna caché de disco previa a este cambio (probar también tras un «Vaciar caché y volver a cargar» seguido de una recarga normal).
- La verificación de nginx en producción (`NGINX-CACHE-CONFIG.md`, `curl -I` contra el dominio real) queda pendiente del propio despliegue; no se puede repetir aquí porque el nginx local no replica la configuración de producción con caché de fastcgi/proxy.
