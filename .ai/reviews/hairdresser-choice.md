# Revisión independiente: hairdresser-choice

**Rama**: `feature/hairdresser-choice` frente a `main` (9 commits, T069–T076)
**Fecha**: 2026-10-13
**Revisor**: Claude Opus 5.5 (contexto limpio; no implementó el cambio)
**Alcance**: spec `.ai/specs/reservas.md` (PRF-161 a PRF-173, CA-23), `.ai/tasks/reservas/T069–T077`, diff completo, tests nuevos y modificados. Reproducciones en memoria con `php artisan tinker` (sin escribir en la base de datos) y suite completa en el contenedor.

## Resumen

| Severidad | Nº |
|---|---|
| Alta | 1 |
| Media | 3 |
| Baja | 4 |

Las migraciones son aditivas y correctas, el POST público no se puede manipular para saltarse la disponibilidad, el panel exige autenticación, y no se filtran la duración ni las esperas a la clienta. El motor no hace *overbooking* puntual: en cada momento hay tantas peluqueras disponibles como citas. Pero la decisión de comprobar momento a momento, sin reparto global, **sí** genera en el flujo normal de reservas online citas «cualquiera» que solo pueden atenderse con un relevo a mitad de tramo y que no se pueden asignar a nadie desde el panel sin «Guardar igualmente». Esto incumple el último punto de PRF-165 y contradice el apartado de riesgos de la spec, que lo describe como «combinaciones raras».

---

## H1 (Alta): una cita «cualquiera» ya reservada puede quedar sin ninguna peluquera libre durante todo su tramo por reservas posteriores con peluquera, con solo 2 peluqueras y en el flujo público normal

**Estado**: resuelto (882e085)

**Evidencia**
- `app/Booking/AvailabilityCalculator.php:377-450` (`capacityProblem`): para una cita **con** peluquera solo se hace el recuento momento a momento (`:441`). La comprobación de «alguien libre durante todo el tramo» (`:446`, `eachStretchHasOneFreeHairdresser`, `:460`) se aplica solo a la cita **nueva** sin peluquera y nunca a las citas sin peluquera que ya existen.
- Reproducido en memoria (tinker, `fittingStartMinutes`, que según su docblock y su test coincide con `isAvailable`). Peluqueras Jennifer (J) e Isabel (I):
  1. U1 «cualquiera», 10:00–11:00 (reservada primero).
  2. Reserva con Isabel 09:30–10:30: **aceptada** (`true`).
  3. Reserva con Jennifer 10:30–11:30: **aceptada** (`true`).
  
  Resultado: U1 solo puede hacerla J de 10:00 a 10:30 e I de 10:30 a 11:00, así que hay un relevo a mitad de un servicio continuo. `AppointmentLaneAssigner` la coloca en la columna 2, con `overCapacity: true` («Sobre capacidad»).
- Con 3 peluqueras, una reserva **nueva sin peluquera** también puede romper una existente. Sea U1 «cualquiera» 10:00–11:00, J 10:30–11:00 y K 09:00–10:15: la reserva U2 «cualquiera» 10:00–10:45 se acepta, aunque U1 y U2 solo pueden hacerse con Isabel durante todo su tramo. `eachStretchHasOneFreeHairdresser` ve a Isabel libre para U2 sin tener en cuenta que U1 la necesita.
- `.ai/specs/reservas.md:406` (PRF-165, último punto): «una cita sin peluquera debe tener, en cada uno de sus tramos activos, al menos una peluquera sin cierre ni cita propia durante todo el tramo, para que nadie tenga que relevarse a mitad de un tramo por culpa de las citas con peluquera». Así redactado es un invariante de todas las citas sin peluquera, no solo de la nueva.
- `.ai/specs/reservas.md:617` (Riesgos) dice que pasa «en combinaciones raras» y que «el salón lo resuelve asignando peluqueras a mano». En el caso reproducido no se puede: asignar U1 a Jennifer o a Isabel da `HairdresserBusy` (cada una tiene su propia cita en parte del tramo), así que solo cabe «Guardar igualmente» o llamar a alguna clienta.
- El test aleatorio `tests/Feature/Booking/HairdresserAvailabilityTest.php:266` («agrees minute by minute with the rules written out directly») vuelve a escribir las mismas reglas que el motor. Por eso no puede detectar esto: no comprueba que exista un reparto sin relevos.

**Impacto**: antes de esta rama no pasaba nunca. Con peluqueras anónimas, el recuento momento a momento basta para repartir sin relevos, porque los grafos de intervalos son perfectos. Ahora basta un patrón muy normal (una «cualquiera» de 1 h y dos clientas que piden a una peluquera concreta justo antes y justo después) para que la web acepte reservas que obligan a interrumpir un servicio. La agenda lo marca como «Sobre capacidad», aunque el recuento no se supera. Para el salón, el resultado práctico es el mismo que un *overbooking*: alguien tiene que dejar a una clienta a medias.

**Recomendación**: que el usuario decida entre:
- **(a) Opción recomendada:** sustituir `eachStretchHasOneFreeHairdresser` por una comprobación de que existe un reparto sin relevos de **todas** las citas sin peluquera que se solapan con la nueva, más la nueva, sea cual sea su tipo (con o sin peluquera). Por cada tramo activo de las citas sin peluquera, las candidatas son las peluqueras disponibles sin cierre ni cita propia durante todo el tramo. Después se busca un emparejamiento con *backtracking*, porque los tramos de una misma peluquera no pueden solaparse. Con 2 o 3 peluqueras y pocas citas al día el coste es trivial, y puede limitarse a la componente conexa de tramos solapados.
- **(b)** Aceptarlo de forma explícita y corregir el apartado de riesgos de la spec («rara» y «se resuelve asignando» no son ciertos) y el texto de PRF-165.

En los dos casos, añadir los dos escenarios de arriba como tests y un test aleatorio que compare con un comprobador de reparto por fuerza bruta, no con las mismas reglas.


**Resolución** (882e085): Corregido con la opción (a), por decisión del usuario. `AvailabilityCalculator::sharesOut()` sustituye a `eachStretchHasOneFreeHairdresser`: antes de aceptar una cita (web, alta del panel o cambio de cita, también al asignar una peluquera) comprueba que todo el día se puede repartir sin relevos a mitad de tramo. Las citas con peluquera las hace ella, las que no tienen las hace cualquiera disponible durante todo el tramo, cada unidad de un «Reducir N» antiguo ocupa a alguien, y el relevo solo cabe en una espera. La búsqueda es exacta (`App\Booking\StaffAssignment`, *backtracking* por grupos de tramos sin peluquera que se solapan, con poda de peluqueras equivalentes y un tope de 20 000 pasos que, si se alcanza, rechaza la cita y nunca la acepta). `unavailabilityReason` carga el día entero. Si un grupo ya no se podía repartir antes (tras un «Guardar igualmente»), la cita no se rechaza por él y se mantiene el recuento por momentos, como antes. Tests: los dos casos del revisor, la asignación desde el panel que dejaría a otra cita sin nadie, el grupo ya roto, `StaffAssignmentTest` y una fuerza bruta independiente que enumera todos los repartos (40 días con semilla, 25 comprobaciones por día), en sustitución del test que copiaba las reglas. Coste medido (31 días con 10 citas al día, calendario de 60 min con y sin peluquera): 0,33 s con citas y cierres de peluquera, 0,19 s sin peluqueras asignadas, frente a 2,5 s y 0,5 s antes de cortar el contexto por día, que se hizo en este mismo commit. PRF-165 y los riesgos de la spec, actualizados.

---

## M1 (Media): la agenda marca «Sobre capacidad» días válidos sin relevos, por la colocación voraz de las citas sin peluquera

**Estado**: resuelto (a98a1b2)

**Evidencia**
- `app/Booking/AppointmentLaneAssigner.php:90-99`: los tramos sin peluquera se colocan por orden de inicio en la primera columna libre durante todo el tramo, sin volver atrás. Antes, con columnas anónimas, el voraz era óptimo. Con columnas ya ocupadas de antemano por las citas con peluquera y los cierres, deja de serlo.
- Reproducido en memoria: U1 «cualquiera» 10:00–10:30, cita con Isabel 10:40–11:00 y U2 «cualquiera» 10:15–11:00. El motor acepta U2 (`true`). Existe un reparto válido sin relevos (U1 con Isabel, U2 con Jennifer), pero el asignador pone U1 en la columna de Jennifer y manda U2 a la columna 2 con `overCapacity: true`.

**Impacto**: falsas alarmas de «Sobre capacidad» en días que no tienen ningún problema. Además, la marca deja de servir para detectar los casos reales de H1.

**Recomendación**: usar en el asignador el mismo emparejamiento con *backtracking* de H1(a). Si no hay solución, recurrir al voraz actual y mandar a «Sobre capacidad» solo lo que no quepa. Añadir este escenario como test en `AgendaHairdresserTest`.


**Resolución** (a98a1b2): `AppointmentLaneAssigner` trabaja por grupos de tramos sin peluquera que se solapan. Mantiene la colocación voraz cuando cabe (las agendas de antes se ven igual) y, si no cabe, usa el mismo `StaffAssignment`, prefiriendo la columna del tramo anterior y la de la que destaca. Solo lo que no tiene reparto va a «Sobre capacidad». Tests: el caso del revisor y un test aleatorio de 25 días formados solo por reservas aceptadas por el motor, en los que nunca aparece «Sobre capacidad». Los «Reducir N» antiguos no se colocan como tramos en la agenda: siguen sombreando las últimas columnas, como antes.

---

## M2 (Media): un cierre «No está {peluquera}» deja citas «cualquiera» por encima de la capacidad sin avisar

**Estado**: resuelto (70a32eb)

**Evidencia**
- `app/Http/Controllers/Admin/ScheduleBlockController.php:55-56`: el aviso de citas afectadas cuenta solo las citas `whereIn('hairdresser_id', $hairdresserIds)`.
- El propio test `tests/Feature/Admin/ScheduleBlockManagementTest.php:136-149` lo muestra. Hay una cita de Isabel, una de Jennifer y una «cualquiera» a las 10:00. Al crear «No está Isabel» de 09:00 a 12:00 el aviso dice «Hay 1 cita», aunque queda 1 peluquera para 3 citas: la «cualquiera» queda sin nadie y no se menciona.
- Antes, un «Reducir N» avisaba de todas las citas confirmadas del periodo.

**Impacto**: el caso típico es «Isabel está enferma mañana». El salón apunta el cierre y no se entera de que una clienta «cualquiera» ya no tiene quien la atienda. PRF-170 dice «contar solo las suyas», pero la intención del aviso (que el salón revise lo que queda colgando) se pierde con las citas sin asignar.

**Recomendación**: proponer al usuario ampliar PRF-170. Además de las citas de la peluquera, el aviso contaría las citas sin peluquera del periodo que dejan de caber tras el cierre, comprobando cada una con `unavailabilityReason(..., excludeAppointmentId: $cita->id)` o con el recuento momento a momento. Añadir un test con la cita «cualquiera» del ejemplo.


**Resolución** (70a32eb): Decisión del usuario: lo recomendado. El aviso de un cierre «No está {peluquera}» cuenta sus citas y, además, las citas futuras sin peluquera del periodo que ya no puede hacer nadie (cada una comprobada como si se reservara otra vez sin ella misma). Test con la cita «cualquiera» del ejemplo y otra que sí cabe. PRF-170 actualizado.

---

## M3 (Media): desactivar una peluquera no comprueba las citas sin peluquera, que pueden quedar por encima de la capacidad

**Estado**: resuelto (954a015)

**Evidencia**: `app/Http/Requests/Admin/HairdresserRequest.php:61-87` solo bloquea la desactivación si la peluquera tiene citas futuras **asignadas** (PRF-163). Con 2 citas «cualquiera» a la vez mañana, desactivar a Isabel se acepta en silencio y deja 2 citas para 1 peluquera. La validación y el `update` (`HairdresserController.php:57`) tampoco toman el bloqueo de `BookingSetting` que usan `CreateAppointment` y `RescheduleAppointment`. Una reserva con Isabel que llegue a la vez que la desactivación puede confirmarse después de que la validación haya visto «0 citas».

**Impacto**: es parecido a bajar antes el campo «Capacidad», que tampoco avisaba, pero ahora la desactivación es la única forma de cambiar la capacidad y el salón no ve el efecto. La carrera es poco probable con un solo salón.

**Recomendación**: en la validación de desactivación, avisar también (o bloquear, según decida el usuario) cuando alguna cita futura sin peluquera deja de caber sin ella. Hacer la comprobación y el `update` dentro de una transacción con `BookingSetting::query()->lockForUpdate()`, como en las acciones de reserva.


**Resolución** (954a015): Decisión del usuario: lo recomendado (bloquear). La nueva acción `UpdateHairdresser` toma el bloqueo de `booking_settings` como primera consulta, guarda y comprueba dentro de la transacción. Rechaza la desactivación si tiene citas futuras asignadas (mismo mensaje que antes) o si alguna cita futura sin peluquera se quedaría sin nadie. Tests: dos citas «cualquiera» a la vez y el bloqueo como primera consulta. PRF-163 actualizado.

---

## L1 (Baja): el presupuesto de consultas de `/reservas` sube de <16 a <23 por llamadas repetidas a `onlineBookingAvailable()`

**Estado**: resuelto (f1240cc)

**Evidencia**: `app/Models/BookingSetting.php:64-69` hace ahora hasta 3 consultas y, según el comentario de `tests/Feature/Booking/PublicBookingTest.php:284-290`, se llama 3 veces por página (controlador y dos enlaces de la cabecera). Son 6 de las 7 consultas añadidas, y repetidas. El límite del test se relajó para que cupieran.

**Impacto**: pequeño en tiempo, porque son consultas `exists` indexadas. Aun así, el test de presupuesto ya no detecta una regresión de hasta 7 consultas más.

**Recomendación**: memoizar `onlineBookingAvailable()` por petición (`once()` de Laravel) y volver a ajustar el límite del test al valor real más 1. El calendario (`daysWithAvailability`) solo lee `activeStaff()` una vez, así que la carga de disponibilidad sigue siendo fija. La agenda añade 3 consultas fijas (peluqueras, «destaca en» y `appointment_services` de las citas sin asignar), sin N+1.


**Resolución** (f1240cc): `BookingController` pasa a la vista si la reserva online está disponible y la cabecera la lee una sola vez (`@php($onlineBookingAvailable ??= …)`). No se usó `once()`: su memoria dura todo el proceso de un test y habría servido valores viejos tras cambiar el interruptor entre dos peticiones del mismo test. `/reservas` con calendario pasa de 22 a 16 consultas y el límite del test vuelve a `< 17` (el real más 1).

---

## L2 (Baja): `range(0, $maxLanes - 1)` con 0 peluqueras activas devuelve `[0, -1]`

**Estado**: resuelto (5daf4d0)

**Evidencia**: `app/Booking/DayTimeline.php:394-400`. Reproducido: `DayTimeline::build(..., capacity 0, hairdressers [])` devuelve `laneNames: ["Sobre capacidad", "Plaza 0"]` con `lanes: 0`. Las vistas iteran por `lanes` y tienen valor por defecto, así que hoy no se ve. Es un dato incorrecto que puede romper cualquier uso futuro de `laneNames`.

**Recomendación**: `$maxLanes > 0 ? range(0, $maxLanes - 1) : []`, y un test de la agenda con todas las peluqueras desactivadas (con y sin citas existentes).


**Resolución** (5daf4d0): `range()` solo cuando hay columnas; con 0 peluqueras `laneNames` y `laneLabels` están vacíos. Test de la agenda con todas desactivadas, sin citas y con una cita (que va a «columna extra», sobre capacidad).

---

## L3 (Baja): la capacidad de producción distinta de 2 depende de un paso manual

**Estado**: resuelto (ba552b9)

**Evidencia**: la migración `2026_10_13_100000_add_hairdressers.php` crea siempre 2 peluqueras activas. `AGENTS.md` («Deploying the hairdressers») pide comprobar `booking_settings.capacity` antes de `./deploy.sh`. Si en producción fuera 1, la capacidad se duplica al desplegar y la web ofrece huecos dobles hasta que el salón desactive a una.

**Recomendación**: hacer la comprobación ahora, antes de fusionar, y apuntar el valor en la tarea T077 o en el PR. La migración se ajusta a la decisión del usuario («sea cual sea la capacidad guardada»): no se propone cambiarla. Lo demás de las migraciones está bien: solo añaden, las FK `restrictOnDelete` encajan con «nunca se borran», los `down()` están en el orden correcto y los datos existentes quedan con `NULL` y `false`.


**Resolución** (ba552b9): No hay nada que tocar en producción. El paso de «Deploying the hairdressers» en `AGENTS.md` queda como lista: leer la capacidad en el VPS antes de `./deploy.sh` (todavía sin leer) y qué hacer si no es 2. La lectura queda pendiente para el usuario en el VPS.

---

## L4 (Baja): huecos de cobertura en los tests del motor

**Estado**: resuelto (ba552b9)

**Evidencia**
- El test aleatorio que compara `fittingStartMinutes` con `isAvailable` (`tests/Feature/Booking/AvailabilityFitForServiceTest.php`, último bloque) solo genera citas sin peluquera y no pasa `hairdresserId`. Que la agenda y el motor coincidan con peluqueras no se comprueba de forma aleatoria; solo hay un caso fijo en `AgendaHairdresserTest:134`.
- El test aleatorio de `HairdresserAvailabilityTest` comprueba el motor contra una copia de sus propias reglas (ver H1).
- No hay ningún test de reservas con peluquera que coincidan en el tiempo. El bloqueo de `BookingSetting` cubre el caso porque `activeStaff()` y las citas se leen dentro de la transacción (comprobado en `CreateAppointment.php:108-118`), pero no hay un test que lo fije.

**Recomendación**: añadir `hairdresser_id` aleatorio a las citas y `hairdresserId` aleatorio al test de concordancia de `fittingStartMinutes`, y el comprobador por fuerza bruta de H1.


**Resolución** (ba552b9): El test de concordancia de `fittingStartMinutes` con `isAvailable` asigna peluquera al azar a citas y cierres y compara para «cualquiera» y para cada peluquera. Nuevo test de reservas con peluquera: el bloqueo es la primera consulta y una segunda reserva para ella a la misma hora se rechaza. La fuerza bruta independiente está en H1.

---

## Comprobaciones sin hallazgos

- **POST público**: `hairdresser_id` con un valor que no es un id, de una peluquera inactiva o de una ocupada se rechaza (`BookingController::store`, `StoreBookingRequest::hairdresserId()`, y `capacityProblem` vuelve a mirar `staff` dentro del bloqueo). Lo cubre `PublicHairdresserChoiceTest:103`. Un `hairdresser_id` añadido a mano cuando la clienta eligió «cualquiera» solo crea una reserva con esa peluquera, que se valida igual.
- **Autorización**: las rutas `admin/peluqueras` están dentro del grupo `auth` + `auth.session` (`routes/web.php`). Lo cubre `HairdresserManagementTest:30`.
- **Fuga de la duración y las esperas (PRF-149/158)**: `/reservas`, `/cita/{token}` y los correos a la clienta solo añaden el nombre de la peluquera elegida (`requestedHairdresser()`). «Destaca en» filtra `bookableOnline()`. Ni la duración ni las esperas se muestran.
- **`hairdresser_requested` y el correo de cambio (PRF-164/169)**: la lógica de `RescheduleAppointment.php:191` y la de `rescheduled` cumplen la spec. «Guardar igualmente» va ligado a la peluquera mediante `slotKey()`.
- **Sin peluqueras con nombre ni cierres por peluquera**: el resultado es idéntico al de antes. Lo cubren los tests aleatorios anteriores, adaptados con `setStaffCount()`.
- **Tests modificados**: los cambios son de etiqueta («plaza N» pasa a «columna de X») o de mecanismo (`capacity` pasa a `setStaffCount`). Las validaciones de `capacity` se eliminaron porque el campo ya no existe y se sustituyeron por «se ignora». Los únicos límites relajados son los presupuestos de consultas (L1, y `services` de 1 a 2 en la agenda), los dos justificados.

## Resolución (2026-10-13)

Todos los hallazgos están resueltos con las decisiones del usuario («las recomendadas, corrige todo»). Suite tras la resolución: 1009 tests en verde y 1 omitido; Pint sin cambios pendientes (T078).

## Ejecución

- Suite completa: `docker compose exec app php artisan test`. Resultado: **961 pasan, 1 omitido** (6305 aserciones, 548 s).
- Reproducciones de H1, M1 y L2: scripts de tinker en memoria (modelos sin guardar y `fittingStartMinutes`/`AppointmentLaneAssigner::assign`/`DayTimeline::build`), sin escribir en la base de datos.
