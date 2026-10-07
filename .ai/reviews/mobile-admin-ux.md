# Revisión independiente: adaptación móvil del panel y la reserva pública (T020–T026)

- **Alcance:** `git diff feature/booking-admin-tweaks...feature/mobile-admin-ux` (8 commits: `52a001b` T020, `d7a9ea0` T021, `cb12be2` T022, `93f739e` T023, `506680d` T024, `bbedc4a` T025, `7987519` T026, `8ab9a18` nota de comprobación pendiente), contrastado con `.ai/specs/reservas.md` («Adaptación móvil», PRF-089 a PRF-098, CA-13) y `.ai/tasks/reservas/T020`–`T026`.
- **Skill:** `review`.
- **Fecha:** 2026-10-05.
- **Revisor:** Claude Sonnet 5, en un contexto limpio. No ha participado en la implementación.
- **Tests:** `docker compose exec -T -u www-data app php artisan test --compact` → **355 pasados (1411 aserciones)**, sobre `feature/mobile-admin-ux` (árbol limpio). Único aviso: Pest no puede escribir su caché de resultados (permisos del volumen `vendor/pestphp/pest/.temp`); no afecta al resultado, ya visto en revisiones anteriores de este proyecto.
- **Build:** `docker compose exec -T node npm run build` → sin avisos. Comprobado además, en el CSS compilado (`public/build/assets/app-*.css`):
  - `.btn-gold{min-height:calc(var(--spacing) * 11);...;display:inline-flex}` — una sola definición, sin duplicados ni reglas en conflicto.
  - `min-height:calc(var(--spacing) * 11)` aparece 4 veces (`.btn-gold`, `.btn-outline`, `.btn-danger-outline` y la utilidad `min-h-11` usada directamente en el calendario/horas públicas).
  - `padding-bottom:calc(4.5rem + env(safe-area-inset-bottom))` presente una vez (la clase arbitraria de `<main>` en `layouts/admin.blade.php`).
  - No queda ningún comentario CSS con `*/` dentro del texto (el bug de T021 solo apareció una vez y la corrección es correcta; revisé los dos comentarios nuevos de `app.css` y el resto del diff).
- **Navegador:** no lo he hecho yo (contexto de revisión sin sesión de usuario). La lista concreta de qué comprobar a 375 px/360 px está al final de este informe, para que el agente principal la ejecute con la sesión del usuario.

## Resumen

| Severidad | Nº |
| --- | --- |
| Critical | 0 |
| High | 0 |
| Medium | 1 |
| Low | 3 |

- M1. `customerWhatsappUrl()` no retira el prefijo internacional `00`, así que un teléfono válido con ese formato genera un enlace de WhatsApp roto.
- L1. El enlace de WhatsApp de la agenda usa solo `rel="noopener"`, a diferencia de todos los demás enlaces externos del sitio (`rel="noopener noreferrer"`).
- L2. Falta un test que compruebe que «Mañana» sigue siendo el día siguiente al de **hoy** cuando se está viendo un día distinto del actual.
- L3. Los tests nuevos de zona táctil y navegación comprueban cadenas de clase en el CSS fuente o en el HTML, no la altura/anchura real renderizada; son necesarios pero no bastan por sí solos.

## Comprobaciones sin hallazgos

- **Cascada CSS (lección M del wiki, bug de las fuentes).** `resources/views/layouts/admin.blade.php` no tiene ningún `<style>` sin capa. El único `<style>` sin capa del proyecto, en `resources/views/layouts/app.blade.php:70-85`, solo fija `font-family` de `body` y de `h1-h6`/`.font-serif`; no toca `display`, `min-height` ni padding de botones o enlaces, así que no puede competir con `.btn-gold`/`.btn-outline`/`.btn-danger-outline` ni con las utilidades `min-h-11`/`inline-flex` del calendario y las horas públicas. Confirmado además leyendo la regla compilada completa de `.btn-gold` (una sola declaración, sin duplicados).
- **Páginas que sobrescriben el padding de `.btn-gold`/`.btn-outline`** (home, contacto, páginas de servicio, `whatsapp-cta`, cookie-banner): todas usan `py-3` o ningún `py-*` propio (heredan el `py-3` del componente), nunca `py-1`/`py-2`. Ninguna reduce la altura por debajo de 44 px.
- **`display` sobrescrito por una utilidad del elemento** (`btn-gold block w-full` en `partials/header.blade.php:81`, `btn-outline inline-block` en `whatsapp-cta.blade.php:3` y `home.blade.php:228`): la capa `utilities` de Tailwind 4 va después de `components`, así que estas utilidades ganan a `inline-flex` del componente, pero ninguna toca `min-height`, así que la zona táctil de 44 px se mantiene; solo cambia que el texto ya no se centra verticalmente con flex, cosa que `block`/`inline-block` + el `line-height` normal del texto ya resolvían antes de este cambio. No es una regresión de este diff: esas vistas no están en el `diff --stat`.
- **PRF-090, barra inferior.** `md:hidden` en la barra y en el botón flotante, `hidden md:flex` en el menú superior: a ≥768 px no puede quedar ninguna de las dos piezas nuevas visible. `aria-current="page"` y el color dorado se aplican igual en las dos navegaciones, que iteran el mismo array `$modules`, así que no hay riesgo de que queden desincronizadas. Los iconos llevan `aria-hidden="true"` y la etiqueta visible hace de nombre accesible.
- **PRF-093, FAB vs. barra inferior.** La barra inferior mide `min-h-14` (56 px) + `py-1.5` (12 px) ≈ 68 px más el borde, bastante menos que los `4.5rem` (72 px) que usan tanto el `padding-bottom` de `<main>` como el `bottom` del botón flotante: hay margen de sobra, nunca se solapan (y el `z-50` de la barra por delante del `z-40` del botón tampoco llega a ejercer de desempate real, porque no coinciden en el mismo espacio). No he encontrado ningún otro elemento `fixed` ni ningún aviso (`session('status')`) con posición fija que pueda competir en `z-index` con la barra.
- **PRF-095, codificación del mensaje.** `rawurlencode()` es la función correcta para `wa.me/...?text=`: codifica el espacio como `%20` (no `+`, que es lo que haría `urlencode()` y que WhatsApp no interpreta como espacio) y el test fija exactamente la cadena esperada con tilde y coma incluidas.
- **PRF-097, calendario y horas públicas.** El `<a>`/`<span>` de cada día es hijo directo de `grid grid-cols-7`: al ser un *grid item*, `justify-items`/`align-items: stretch` (por defecto) lo estira para ocupar toda la celda, con independencia de que ahora sea `flex` en vez de bloque implícito, así que la zona táctil cubre toda la celda, no solo el número. En las horas, el `<label>` (bloque, por ser *grid item* de `grid grid-cols-3 sm:grid-cols-6`) envuelve el `<input type="radio" class="sr-only">` y el `<span class="min-h-11 flex ...">`; como el `<input>` no ocupa espacio, la altura del `<label>` (la zona realmente clicable, porque el `click` en cualquier punto de un `<label>` activa su control) coincide con la del `<span>`, 44 px.
- **Timezone de «Mañana».** `config/app.php` fija `'timezone' => 'Europe/Madrid'`, y `LoadConfiguration::bootstrap()` (Laravel) aplica `date_default_timezone_set()` con ese valor antes de que se ejecute cualquier controlador. `CarbonImmutable::today()` (usado tanto por `AgendaController::dayFromQuery()` para el valor por defecto como por el enlace «Mañana») resuelve siempre en esa zona, igual que el resto del sistema de reservas. No hay discrepancia de zona horaria entre «Hoy», «Mañana» y el día mostrado.
- **Días cerrados.** El enlace «Mañana» es pura navegación de fecha (`route('admin.agenda', ['fecha' => ...])`), igual que «Hoy»/«Día siguiente»; no depende de `opening_hours` ni de `ScheduleBlock`. Si el día siguiente está cerrado, la agenda simplemente lo muestra sin citas («No hay citas este día.»), igual que hoy ya hace con cualquier otro día cerrado; no hay ninguna ruta de error.
- **Botones dentro de formularios.** `btn-danger-outline`/`btn-gold` dentro de `<form>` (cancelar cita, eliminar cierre, confirmar) son siempre `type="submit"`, sin `onclick` que reemplace el envío; los `confirm()` de JS inline no cambian con este diff.
- **Contraste de `.btn-danger-outline`.** `text-red-300` (#fca5a5) sobre el fondo `#111111` del panel da un contraste calculado ≈ 10:1, muy por encima del mínimo AA (4,5:1); no es un token de etiqueta reutilizado sin comprobar (lección del wiki sobre `tag-red-*`), es un color pensado para texto.
- **`customer_phone` nunca está vacío en producción.** La columna es `string(20)` no nula y tanto `StoreBookingRequest` como `StoreAdminAppointmentRequest` exigen `required` + `PhoneNumber` (9-15 dígitos) para crear o mover una cita. El caso "teléfono vacío" de `customerWhatsappUrl()` es defensivo (no lanza excepción si algún día se relaja esa regla), pero no es alcanzable hoy; no abro hallazgo por eso.
- **`PublicBookingTest`/`ServiceManagementTest`/`OpeningHoursManagementTest`/`CustomerAppointmentTest`.** Cada test nuevo nombra el PRF que cubre y usa datos realistas (no son tautológicos: comprueban contenido y ausencia de patrones concretos, `<table`, `overflow-x-auto`, el HTML exacto del botón).

---

## Medium

### M1. `customerWhatsappUrl()` no retira el prefijo internacional `00`: un teléfono válido con ese formato genera un enlace de WhatsApp roto

- **Estado:** resolved
- **Resolución:** `customerWhatsappUrl()` ahora detecta `00` o `+` al principio del teléfono ya recortado (`trim()`) y los retira del valor completo (no solo de los dígitos) antes de extraer los dígitos; solo si no había ningún prefijo reconocido y quedan 9 dígitos o menos se antepone `34`. `app/Models/Appointment.php:95-113`. Tests: `tests/Feature/Admin/AgendaTest.php` › «customerWhatsappUrl strips the "00" international dialing prefix, with or without spaces» (3 casos: `0034633912050`, `00 34 633 912 050`, `00-34-633-912-050`), y la batería existente «...never breaks on odd input» sigue cubriendo `+34...`, `34...` sin prefijo y un número extranjero. `.ai/tasks/reservas/index.md`, fila PRF-095, corregida a `covered` solo tras esta ampliación.
- **Evidencia:**
  - `app/Models/Appointment.php:95-104`:
    ```php
    public function customerWhatsappUrl(): string
    {
        $digits = (string) preg_replace('/\D/', '', $this->customer_phone);

        if ($digits !== '' && ! str_starts_with(trim($this->customer_phone), '+') && strlen($digits) <= 9) {
            $digits = '34'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode(...);
    }
    ```
    Solo normaliza el caso "9 dígitos o menos, sin `+`" (asumido español sin prefijo). Cualquier número con más de 9 dígitos que no empiece por `+` se deja tal cual, incluido uno que empiece por el prefijo internacional `00` (equivalente a `+` en marcación, y la forma en que parte de la clientela todavía escribe su número).
  - `app/Rules/PhoneNumber.php:18` acepta explícitamente ese formato: la regex es `/^\+?[0-9\s\-()]+$/` con 9 a 15 dígitos, y `00 34 633 912 050`/`0034633912050` (13 dígitos, sin `+`) la cumple. No hay ninguna otra validación que lo rechace, ni en `StoreBookingRequest` ni en `StoreAdminAppointmentRequest`.
  - Reproducido con `tinker` sobre `feature/mobile-admin-ux`:
    ```
    customer_phone = "0034 633 912 050"  →  https://wa.me/0034633912050?text=...
    customer_phone = "00 34 633 912 050" →  https://wa.me/0034633912050?text=...
    ```
    `wa.me/0034633912050` no es un enlace de WhatsApp válido (WhatsApp espera el número sin el prefijo de marcación internacional, es decir `wa.me/34633912050`); al abrirlo, WhatsApp no reconoce el país `003` y no encuentra el contacto.
  - Ningún test cubre este formato: `tests/Feature/Admin/AgendaTest.php`, test «customerWhatsappUrl keeps an already-international phone as is and never breaks on odd input», prueba `+34 633 912 050`, `34633912050` y un número extranjero con `+`, pero no `00...`.
  - La matriz de cobertura (`.ai/tasks/reservas/index.md`) marca PRF-095 como `covered` citando justo ese test, sin la salvedad de este caso.
- **Impacto:** PRF-095 exige que el teléfono se normalice a formato internacional y «sin romperse con un teléfono mal formado»; un `00...` no está mal formado (lo acepta la validación del alta/edición de citas), pero el enlace de un toque que es el punto central de esta tarea queda roto, en silencio, para cualquier clienta cuyo teléfono se escribió con ese prefijo. El salón solo lo descubre cuando el enlace no abre el chat correcto; la vía de escape (llamar por teléfono) sigue intacta, de ahí que no sea `alto`.
- **Recomendación:**
  - Añadir, antes o junto a la comprobación del `+`, un caso que detecte el prefijo `00` inicial (sobre el valor ya recortado de espacios, antes de quitar los no-dígitos) y lo sustituya igual que `+`: si `trim($this->customer_phone)` empieza por `00`, quitar esos dos caracteres del resultado (no solo de los dígitos, para no arrastrar un `00` que forme parte real del número) antes de devolverlo, sin volver a anteponer `34`.
  - Añadir un caso a `customerWhatsappUrl keeps an already-international phone as is and never breaks on odd input` (o uno nuevo) con `'00 34 633 912 050'` y `'0034633912050'`, esperando `https://wa.me/34633912050?text=...`.
  - Revisar si conviene corregir también la fila PRF-095 de `.ai/tasks/reservas/index.md` a `partial` hasta que este caso esté cubierto (patrón recurrente de evidencia que da por cubierto más de lo que el test comprueba, `syntheses/lecciones-revisiones.md`, patrón A).

---

## Low

### L1. El enlace de WhatsApp de la agenda usa solo `rel="noopener"`, a diferencia de cualquier otro enlace externo del sitio

- **Estado:** resolved
- **Resolución:** `rel="noopener noreferrer"`, igual que el resto de enlaces de WhatsApp del sitio. `resources/views/admin/agenda/index.blade.php:85` (ahora en la fila de botones de acción, ver hallazgo N1 del coordinador).
- **Evidencia:** `resources/views/admin/agenda/index.blade.php:65`: `<a href="{{ $appointment->customerWhatsappUrl() }}" target="_blank" rel="noopener" ...>`. El resto de enlaces `target="_blank"` a WhatsApp del proyecto (`resources/views/partials/whatsapp-cta.blade.php:3`, `resources/views/pages/home.blade.php:130,353`, `resources/views/pages/contacto.blade.php:59`, y los de cada página de servicio) llevan `rel="noopener noreferrer"`.
- **Impacto:** bajo. `noopener` ya evita el riesgo principal (que la pestaña abierta controle `window.opener`); `noreferrer` añade que WhatsApp no reciba la URL interna del panel (`/admin/agenda?fecha=...`) como *referrer*, que no es sensible pero tampoco hay motivo para enviarla. Es una inconsistencia frente al resto del código, no una vulnerabilidad nueva.
- **Recomendación:** añadir `noreferrer` para que los cuatro use los mismos atributos: `rel="noopener noreferrer"`.

### L2. Falta un test de que «Mañana» sigue siendo el día siguiente al de **hoy** cuando se está viendo otro día

- **Estado:** resolved
- **Resolución:** `tests/Feature/Admin/AgendaTest.php` › «"Mañana" still points to the day after today when viewing a different day»: visita `?fecha=2030-01-20` y comprueba, con una expresión regular sobre el `href` que precede exactamente al texto «Mañana», que sigue apuntando a `2030-01-09` (el día siguiente a **hoy**, 2030-01-08), no a `2030-01-21` (que es a donde sí lleva «Día siguiente →» desde ese mismo día). El test pasó sin tocar la implementación: confirma que el comportamiento ya era correcto, como indicaba el informe.
- **Evidencia:** `resources/views/admin/agenda/index.blade.php:32`: `href="{{ route('admin.agenda', ['fecha' => \Carbon\CarbonImmutable::today()->addDay()->toDateString()]) }}"`, independiente de `$day`, a propósito (documentado en T023: «no depende del día que se esté viendo»). El único test, `tests/Feature/Admin/AgendaTest.php` › «the agenda offers a shortcut to tomorrow», llama a `route('admin.agenda')` sin `fecha`, es decir, exactamente el caso en que `$day` y «hoy» ya coinciden; no distingue "mañana absoluto" de "`$day` + 1 día" (que es justo lo que hace el enlace «Día siguiente →», ya existente, en la misma barra).
- **Impacto:** bajo. El comportamiento actual es correcto y coincide con la decisión registrada en la especificación (PRF-092), pero un cambio futuro que convirtiera por error «Mañana» en `$day->addDay()` (una confusión fácil, al estar la línea justo entre «Hoy» y «Día siguiente →», que sí usan `$day`) pasaría los tests igualmente.
- **Recomendación:** añadir un caso con `?fecha=` apuntando a un día distinto de hoy (por ejemplo, pasado mañana) y comprobar que «Mañana» sigue enlazando al día siguiente al de **hoy** (el de `CarbonImmutable::today()`), no al día siguiente del que se está viendo.

### L3. Los tests nuevos de zona táctil y navegación verifican clases de texto, no la altura o anchura real renderizada

- **Estado:** anotado, sin cambio de código (decisión del coordinador: la comprobación real en el navegador la hace él/el usuario, con su sesión). Ver la sección «Comprobación en el navegador pendiente» al final de este informe, ampliada con los hallazgos N1-N4 del coordinador y sus propios puntos nuevos que comprobar.
- **Evidencia:**
  - `tests/Feature/Admin/AdminLayoutTest.php` › «the shared button styles meet the 44px minimum touch target» y «the danger button style meets the 44px minimum touch target» leen `resources/css/app.css` (el **código fuente** sin procesar por Tailwind) con una expresión regular y comprueban que el texto `min-h-11`/`inline-flex` aparece dentro del bloque `@apply` de cada clase.
  - `tests/Feature/Booking/PublicBookingTest.php` › «the calendar days and the time slots meet the 44px minimum touch target» y `tests/Feature/Admin/OpeningHoursManagementTest.php` › «every opening-hours range row can wrap...» cuentan apariciones de una cadena de clases (`min-h-11 flex items-center justify-center border`, `flex flex-wrap items-center gap-2 text-sm`) en el HTML renderizado.
  - Ninguno de los dos tipos de test compila CSS ni mide un valor calculado (`getBoundingClientRect()`/`getComputedStyle()`); todos son, en el mejor de los casos, pruebas de regresión de marcado.
  - Las propias tareas (T020, T021, T022, T024, T025) lo reconocen explícitamente con un «Pendiente a mano» idéntico: la medición real a 360-375 px queda para el navegador.
  - `syntheses/lecciones-revisiones.md` (patrón M, actualización 2026-10-05) documenta, en este mismo proyecto, dos casos reales en que Pest estaba en verde y el navegador mostraba un fallo real (Playfair/Inter sin aplicarse por CSS sin capa, fuentes con 404 en Vite dev): la causa en ambos casos era invisible para un test que solo lee HTML o texto fuente.
- **Impacto:** bajo, no por que los tests estén mal escritos (son una guarda de regresión legítima y barata: si alguien quita `min-h-11` del `@apply`, el test lo detecta de inmediato), sino porque, por sí solos, no demuestran que PRF-089, PRF-090, PRF-096 y PRF-097 se cumplan de verdad. En esta revisión he comprobado a mano, leyendo el CSS **compilado**, que la cascada no tiene ninguna regla sin capa que gane a estas utilidades (ver «Comprobaciones sin hallazgos»), pero esa comprobación no queda protegida por ningún test: una regresión futura (por ejemplo, un `<style>` nuevo sin capa en `layouts/admin.blade.php`, o una utilidad `block`/`h-auto` añadida sobre `.btn-gold` en una vista nueva) pasaría la suite igual.
- **Recomendación:** no es necesario reescribir los tests existentes, pero antes de cerrar T020-T026:
  - completar la comprobación manual a 360-375 px que las propias tareas dejan pendiente (lista al final de este informe);
  - si el proyecto incorpora alguna vez un test de navegador real (Dusk/Playwright, aunque sea uno solo, "smoke"), que sea este el primer candidato: medir `getBoundingClientRect().height` de un botón del panel y de una celda del calendario a un ancho de 375 px, precisamente porque aquí ya ha fallado antes algo que los tests de marcado no veían.

---

## Hallazgos del coordinador en el navegador (N1-N4, 2026-10-05)

El coordinador comprobó el panel y la web pública a 375 px en Chrome, con la sesión del usuario (lo que esta revisión, sin sesión, no pudo hacer — ver L3). El usuario aprobó los cuatro hallazgos antes de resolverlos.

### N1. En la tarjeta de la agenda, llamar y WhatsApp eran enlaces de texto de 21 px, dentro de la línea de datos

- **Estado:** resolved
- **Resolución:** «Llamar» y «WhatsApp» son ahora botones (`btn-outline`, ≥44 px) en la misma fila que «Editar»/«Cancelar cita» (`grid grid-cols-2 sm:grid-cols-4 gap-2`, dos filas de dos a 375 px). El teléfono sigue visible como texto plano en la línea de datos (ya no es un enlace `tel:`). Una cita cancelada conserva «Llamar»/«WhatsApp» (el salón puede necesitar seguir contactando) pero no «Editar»/«Cancelar cita». «Cancelar cita» sigue en rojo (`btn-danger-outline`) y dentro de la misma rejilla, pero con su propio estilo, así que no se confunde con las demás. `resources/views/admin/agenda/index.blade.php:66-95`. Tests: `tests/Feature/Admin/AgendaTest.php` › «an appointment card offers Llamar and WhatsApp as buttons, with the phone still visible as text» y «a cancelled appointment still offers Llamar and WhatsApp, but not Editar/Cancelar».

### N2. La navegación de días ocupaba dos líneas a 375 px

- **Estado:** resolved
- **Resolución:** «← Día anterior»/«Día siguiente →» pasan a botones de solo icono («←»/«→», `w-11 px-0`) con `aria-label="Día anterior"`/`"Día siguiente"`; junto con «Hoy» y «Mañana» quedan en una sola fila (`flex items-center gap-2`, sin `flex-wrap`) que cabe de sobra a 375 px. El selector de fecha con «Ir» pasa a una fila propia debajo (`space-y-3` en el `<nav>`). `resources/views/admin/agenda/index.blade.php:30-45`. Test: `tests/Feature/Admin/AgendaTest.php` › «the day switcher uses icon-only prev/next buttons with an accessible label».

### N3. Los `input type=date`/`type=time`/`type=datetime-local` medían 34/31 px

- **Estado:** resolved
- **Resolución:** en todos los sitios señalados, `py-1`/`py-1.5`/`py-2` pasan a `py-3` (≈44 px con el `line-height` habitual): el selector de fecha de la agenda, los dos `input type="time"` de cada tramo de Horario, y el `$inputClass` compartido de «Nueva cita», «Editar cita» y «Cierres» — este último afecta también a los campos de texto/email/select de esos tres formularios, no solo a los de fecha/hora, porque los comparten; se optó por subir la clase común entera en vez de añadir una utilidad `py-3` aparte sobre cada campo de fecha/hora, que en Tailwind no tiene precedencia garantizada frente a la `py-2` del `$inputClass` (dos utilidades en conflicto en el mismo elemento no tienen un orden de cascada fiable). Es un efecto más amplio de lo pedido, pero en la misma dirección (más cómodo de tocar) y sin ningún campo por debajo de 44 px. Tests: `tests/Feature/Admin/AgendaTest.php` › «the "ir a la fecha" date picker meets the 44px touch target», `tests/Feature/Admin/OpeningHoursManagementTest.php` › «every time input meets the 44px touch target», `tests/Feature/Admin/AdminAppointmentTest.php` › «the new-appointment and edit-appointment fields meet the 44px touch target», `tests/Feature/Admin/ScheduleBlockManagementTest.php` › «the closure form fields meet the 44px touch target».

### N4. En `/cita/{token}`, la casilla «Sí, quiero cancelar esta cita» medía 20 px

- **Estado:** resolved
- **Resolución:** toda la fila (`<label>`) pasa a `min-h-11 py-2 cursor-pointer`, con la casilla a `w-5 h-5` (antes el tamaño nativo del navegador, ~16 px) y `accent-gold` para que combine con la marca. `resources/views/pages/cita.blade.php:57-60`. Test: `tests/Feature/Booking/CustomerAppointmentTest.php` › «the cancel-confirmation row meets the 44px touch target, with a bigger checkbox».

---

## Comprobación en el navegador pendiente (la hace el agente principal, con la sesión del usuario)

Esta lista es anterior a la comprobación del coordinador (N1-N4 arriba). Al repetirla tras la resolución, el punto 2 incluye además «Llamar»/«WhatsApp» (ahora botones en la misma fila que «Editar»/«Cancelar cita», antes enlaces de 21 px), el punto 4 puede repetirse para confirmar que «Mañana» sigue sin depender del día que se esté viendo tras el cambio de N2 (el enlace a «Mañana» no se tocó, solo su envoltorio visual), y el punto 5 puede repetirse con un teléfono escrito como `00 34 633 912 050` para confirmar que M1 queda resuelto (antes abría `wa.me/0034...`, roto).

### Panel, a 375 px (y 360 px para Horario)

1. **Barra inferior (PRF-090).** Entrar en Agenda, Servicios, Horario, Cierres y Ajustes: la barra inferior fija aparece en los cinco, con 5 iconos + etiqueta, el módulo activo en dorado y `aria-current`. Confirmar con el inspector que el menú superior **no** está en el árbol de accesibilidad activo (está `display:none` vía `hidden`). Pasar a ≥1024 px (o abrir en escritorio): la barra inferior desaparece y vuelve el menú superior, sin huecos extra.
2. **Zona táctil (PRF-089).** Medir con el inspector la caja real (no solo el CSS declarado) de: «Editar» y «Cancelar cita» en una cita de la agenda, «Eliminar» en Cierres, «Guardar horario»/«Guardar ajustes»/«Guardar cita», el botón flotante «+» y un módulo de la barra inferior, y «Cerrar sesión». Los seis deben medir ≥44×44 px de caja real (incluyendo `border`/`padding`, no solo `min-height`).
3. **Botón flotante vs. barra inferior (PRF-093).** En la Agenda, con varias citas, hacer *scroll* hasta el final de la lista: el botón flotante sigue visible, no tapa la última cita ni queda oculto o solapado por la barra inferior (debería verse un pequeño hueco entre ambos, no un solape).
4. **«Mañana» (PRF-092).** Desde Agenda sin fecha en la URL, pulsar «Mañana»: debe ir al día siguiente al de **hoy** de verdad. Después, navegar varios días hacia delante con «Día siguiente →» y pulsar «Mañana» otra vez desde ahí: debe volver al mismo día que la primera vez (el día siguiente a hoy), no al día siguiente del que se estaba viendo.
5. **WhatsApp desde la tarjeta (PRF-095).** En una cita con teléfono, pulsar «WhatsApp»: se abre WhatsApp Web o la app en una pestaña nueva, con el chat de ese número y el mensaje prellenado y legible (sin `%XX` visibles). Si es posible, crear o editar una cita con un teléfono escrito como `00 34 633 912 050` y confirmar que el enlace generado (`wa.me/0034633912050`, visible al pasar el ratón o en el código fuente) no abre el chat correcto — para corroborar M1 antes de que se corrija.
6. **Editar vs. Cancelar (PRF-094).** Confirmar a simple vista, sin leer el texto, que «Editar» (borde dorado) y «Cancelar cita» (borde/texto rojo) se distinguen y no están tan juntos como para pulsar uno por error en lugar del otro.
7. **Foco con teclado.** Recorrer la barra inferior y el botón flotante con Tab: debe verse un anillo de foco visible en cada parada (el diff no quita ningún `outline`, pero conviene confirmarlo en el navegador real, no solo leyendo el CSS).
8. **Servicios en tarjetas (PRF-091).** Con 2-3 servicios, confirmar que se ven como tarjetas, sin ninguna `<table>` ni *scroll* horizontal, y que «Editar» es cómodo de pulsar.
9. **Horario a 360 px (PRF-096).** Abrir Horario a 360 px exactos: ninguna fila de tramo debe producir *scroll* horizontal de la página ni recortar una hora; si una fila no cabe en una línea, la hora de cierre debe pasar a la línea siguiente de forma legible, no solaparse ni cortarse a la mitad.
10. **Zona segura del iPhone,** si hay un dispositivo o el emulador de Chrome con *notch* a mano: la barra inferior no debe quedar tapada por la zona de gestos, ni dejar una franja negra de más esperando.

### Web pública, a 375 px

11. **Calendario y horas (PRF-097).** En `/reservas`, medir con el inspector una celda de día disponible y un botón de hora: ambos ≥44 px de alto, y confirmar que tocar en cualquier punto de la celda/etiqueta (no solo el número o la hora) selecciona el día/la hora.
12. **Cancelar cita a ancho completo (PRF-098).** Abrir `/cita/{token}` de una cita todavía cancelable: el botón «Cancelar mi cita» ocupa todo el ancho disponible.
13. **Regresión de los botones existentes tras el cambio de `.btn-gold`/`.btn-outline`.** Revisar visualmente, a 375 px: el botón «Reservar online»/menú móvil de la cabecera (`btn-gold block w-full`), los botones de la portada y de contacto (`btn-gold`/`btn-outline` con `px-6`/`px-8 py-3` propios) y los dos botones del aviso de cookies (`btn-outline`/`btn-gold flex-1 sm:flex-none`): el texto debe seguir centrado y ningún botón debe verse más alto/ancho de lo esperado o con el texto descentrado por el nuevo `inline-flex`/`min-h-11`.
