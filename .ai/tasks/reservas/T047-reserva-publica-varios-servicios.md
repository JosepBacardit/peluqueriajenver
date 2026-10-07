# T047 — Reserva pública con varios servicios

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-127, PRF-128, PRF-131 (y PRF-027 a PRF-029 y PRF-033 ajustados)
- **Depende de:** T046
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `high`
- **Motivo:** funcionalidad visible en la página pública, sobre la interfaz de T046 y sin tocar el modelo.
- **Estado:** done
- **PR / rama:** `feature/multi-service-appointments`, commit `590dea3`.

## Resolución

Paso 1 pasa de enlaces a casillas (`servicio[]`, de 1 a `Appointment::MAX_SERVICES`), con un formulario `GET` sin JavaScript obligatorio y un total en vivo con JS vanilla mínimo (oculto sin JS; el total se ve en el paso 2 de todas formas). `?servicio=3` (escalar) sigue funcionando; una selección repetida, con más del máximo o con un servicio no reservable online se rechaza entera, de vuelta al paso 1 con un aviso. `StoreBookingRequest` cambia `service_id` por `service_ids` (array, `distinct`, hasta el máximo); `BookingController::store()` rechaza la reserva entera si algún id no resuelve a un servicio reservable online, con el mismo mensaje de «hora no disponible» (PRF-128). El calendario, las horas y el total usan la suma de duraciones. La vuelta tras un error conserva toda la selección en la URL.

Tests: `tests/Feature/Booking/MultiServiceBookingTest.php` (nuevo, 12 casos) y ajustes de `service_id`→`service_ids` en `PublicBookingTest`, `AppointmentNotificationsTest`, `BookingAbuseLimitsTest`.

## Objetivo

En `/reservas`, elegir de 1 a 5 servicios con casillas, ver la duración total y reservar con ellos, todo sin JavaScript.

## Interfaz de T046 que hay que usar

- `CreateAppointment::handle(Collection $services, …)`, pasando los `Service` elegidos. La Action los ordena (`ServiceList::ordered()`) y comprueba la suma.
- `Appointment::MAX_SERVICES` para el máximo: nunca un `5` escrito a mano.
- `AvailabilityCalculator::daysWithAvailability()` y `availableStartTimes()` con la **suma** de `duration_minutes`.

## Plan

1. **Paso 1** (`pages/reservas.blade.php`): un formulario `GET` con una casilla por servicio reservable online (nombre y duración; la fila entera es la `label`, de al menos 44 px de alto) y un botón «Ver días y horas». La dirección queda como `?servicio[]=3&servicio[]=4`.
   - El texto «Hasta 5 servicios por cita» se genera con `MAX_SERVICES`.
   - Total en vivo con JS vanilla mínimo, como mejora progresiva. Sin JS, el total se ve en el paso 2.
2. **`BookingController@index`:**
   - normaliza `servicio` (escalar, para la compatibilidad con `?servicio=3`, o array);
   - valida de 1 a `MAX_SERVICES` ids **distintos**, todos reservables online;
   - si la lista no es válida, se queda en el paso 1 con un aviso, sin error;
   - la duración del calendario y de las horas es la suma.
3. **Paso 2:** una cabecera con los servicios elegidos (en el orden del salón), el total y «Cambiar». Los enlaces del calendario (`reservas-calendar.blade.php`) arrastran `servicio[]`.
4. **Formulario** (`reservas-form.blade.php`) y **`StoreBookingRequest`:**
   - `service_ids` es un array de 1 a `MAX_SERVICES` elementos, `distinct` y enteros;
   - si alguno no es reservable online o no existe, se rechaza la reserva entera con el mensaje de hora no disponible, como ahora (PRF-128);
   - la redirección de vuelta conserva `servicio[]`.
5. Los límites de abuso no cambian: cuentan citas (PRF-131, ya cubierto en la Action).

## Plan de pruebas

En `PublicBookingTest` y un archivo nuevo si hace falta:

- dos servicios: la dirección, el calendario y las horas calculados con la suma;
- se ve el total;
- `?servicio=3` sigue funcionando;
- se rechazan: un servicio repetido, 6 servicios, un servicio no reservable mezclado con otros, y una petición vacía o manipulada;
- `POST` con dos servicios crea una cita con los dos;
- la vuelta tras un error conserva la selección;
- ningún precio en la página.

Revisión en el navegador a 375 px.