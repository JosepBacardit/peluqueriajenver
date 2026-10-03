# Reservas online y panel de gestión

**Estado:** In progress (aprobada por el usuario el 2026-10-03).

## Flujo de trabajo

ARCHITECTURAL. La web pasa de ser solo informativa a guardar datos de clientes, tener cuentas de acceso, enviar correo y necesitar una tarea periódica. Se entrega en cuatro PRs apilados (base del panel, disponibilidad y agenda, reserva pública, notificaciones) para que cada uno se pueda revisar por separado. No se publica hasta tener los cuatro.

## Resultado para el usuario

- Un **cliente** que entra en la web puede elegir un servicio, ver en un calendario los días y horas libres y reservar una cita sin llamar. Recibe un correo de confirmación con un enlace para cancelarla.
- Una persona del **salón** puede entrar en un panel privado y gestionar los servicios y su duración, el horario, los cierres, la capacidad y la agenda de citas, incluidas las que llegan por teléfono o WhatsApp.

## Comportamiento actual

La única forma de pedir cita es llamar o escribir por WhatsApp. El botón «Reservar cita →» de la cabecera abre una llamada. No hay cuentas, ni datos de clientes, ni correo saliente, ni tareas periódicas. Las páginas públicas no muestran precios por decisión del cliente. La política de privacidad muestra un email de contacto que no es real. Si no se hace nada, cada cita sigue costando una llamada o un mensaje.

## Alcance

- Panel privado con acceso por cuenta (sin registro público) y con espacio para añadir más módulos en el futuro.
- Módulos del panel: agenda de citas, servicios, horario semanal, cierres y bloqueos, y ajustes de la reserva.
- Página pública de reservas con calendario, selección de hora y formulario.
- Página personal de la cita, con cancelación.
- Correos al cliente y al salón, con reintento automático de los avisos de cita nueva.
- Cambios en la cabecera, el mapa del sitio y los datos estructurados para que apunten a la reserva online.
- Política de privacidad reestructurada para cubrir las reservas.

## No-objetivos

- Elegir peluquera o asignar citas a una persona concreta. **Descartado** por decisión del usuario: el salón trabaja con una capacidad común.
- Varios servicios en una misma cita. **Descartado**: los combos se dan de alta como servicios propios.
- Cambiar la fecha u hora de una cita ya hecha. **Pospuesto**: el cliente cancela y vuelve a reservar.
- Recordatorio por correo antes de la cita y borrado automático de citas antiguas. **Pospuesto** al PR 5.
- Calendario sin recargar la página. **Pospuesto** al PR 5.
- Avisos por WhatsApp o SMS. **Descartado**: tienen coste y requieren un proveedor externo.
- Pago o señal al reservar. **Descartado**: no se ha pedido.
- Fichas de cliente, contabilidad, productos o puntos. **Pospuesto** a módulos futuros.
- Permisos distintos entre cuentas del panel. **Descartado**: todas las cuentas pueden hacer lo mismo.
- Recuperar la contraseña desde la web. **Pospuesto**: se cambia desde la consola del servidor.

## Especificación funcional

### Entorno de destino

Servidor propio con una única instancia de la aplicación y la base de datos en el mismo servidor. No hay procesos permanentes en segundo plano: los correos se envían en el momento y solo existe una tarea periódica programada en el servidor. Todas las fechas y horas están en hora peninsular española (Europe/Madrid), con su cambio de horario de verano.

### Acceso al panel

- **PRF-001.** Una persona sin identificar que intenta abrir cualquier pantalla del panel **debe** ver la pantalla de acceso, con los campos «Email» y «Contraseña».
- **PRF-002.** Una persona del salón con una cuenta válida **debe** entrar en el panel al introducir su email y su contraseña, y llegar a la agenda del día.
- **PRF-003.** Si el email o la contraseña no son correctos, la pantalla de acceso **debe** mostrar «Email o contraseña incorrectos.» sin decir cuál de los dos falla, y no da acceso.
- **PRF-004.** Tras 5 intentos fallidos en 1 minuto con el mismo email desde la misma conexión, el acceso **no debe** comprobar más contraseñas hasta que pase el minuto, y debe mostrar «Demasiados intentos. Vuelve a probar en N segundos.».
- **PRF-005.** La web **no debe** ofrecer ninguna forma pública de crear una cuenta del panel.
- **PRF-006.** Las cuentas del panel **deben** crearse desde la consola del servidor, pidiendo nombre, email y contraseña (mínimo 12 caracteres, escrita dos veces y sin mostrarse en pantalla). Si el email ya existe, debe ofrecer cambiar su contraseña y no cambiar nada si se responde que no.
- **PRF-007.** El repositorio **no debe** contener ninguna cuenta ni contraseña de acceso al panel.
- **PRF-008.** Una persona identificada **debe** poder cerrar sesión desde cualquier pantalla del panel. Después, el panel vuelve a pedir acceso.
- **PRF-009.** Todas las pantallas del panel **deben** compartir un menú con los módulos Agenda, Servicios, Horario, Cierres y Ajustes. Añadir un módulo nuevo no debe exigir cambiar las pantallas existentes.

### Servicios

Reglas de los campos de un servicio (conjunto único de valores válidos, válido para el panel y para las comprobaciones del sistema):

| Campo | Obligatorio | Valores válidos |
| --- | --- | --- |
| Nombre | Sí | De 2 a 100 caracteres, acentos incluidos. |
| Duración | Sí | Minutos enteros, múltiplo de 5, de 5 a 600. |
| Precio interno | No | Euros con 2 decimales como máximo, de 0 a 9.999,99. |
| Reservable online | Sí | Sí o no. Por defecto, sí. |
| Activo | Sí | Sí o no. Por defecto, sí. |
| Orden | No | Entero de 0 a 999. Por defecto, 0. |

- **PRF-010.** Una persona del salón **debe** poder dar de alta un servicio con los campos de la tabla. Si algún valor no es válido, ve el error junto al campo y no se guarda nada.
- **PRF-011.** Una persona del salón **debe** poder editar cualquier campo de un servicio existente con las mismas reglas.
- **PRF-012.** La lista de servicios del panel **debe** mostrar todos los servicios ordenados por «Orden» y después por nombre, con su duración, precio interno, si es reservable online y si está activo. Sin servicios, muestra «Todavía no hay servicios. Crea el primero para poder recibir reservas.».
- **PRF-013.** El panel **no debe** permitir borrar servicios. Un servicio que ya no se ofrece se marca como no activo, y sus citas pasadas y futuras se conservan con el nombre y la duración que tenían al reservarse.
- **PRF-014.** El precio interno **no debe** aparecer en ninguna página pública ni en ningún correo al cliente.
- **PRF-015.** Cambiar la duración de un servicio **no debe** cambiar la hora de fin de las citas ya existentes.
- **PRF-016.** Un servicio no activo o no reservable online **no debe** ofrecerse en la página pública de reservas. Un servicio no activo tampoco se ofrece al crear citas desde el panel.

### Horario semanal y ajustes

- **PRF-017.** Una persona del salón **debe** poder definir, para cada día de la semana, hasta 2 tramos de apertura (por ejemplo, mañana y tarde) con hora de inicio y de fin en múltiplos de 5 minutos. Un día sin tramos está cerrado.
- **PRF-018.** El horario **no debe** guardarse si algún tramo termina antes o a la misma hora en que empieza, si los dos tramos de un día se solapan o si solo se rellena una de las dos horas de un tramo. Se muestra el error en el día afectado y no cambia nada de la semana.
- **PRF-019.** El horario inicial, antes de que nadie lo cambie, **debe** ser de martes a sábado de 09:00 a 19:00 en un solo tramo, con domingo y lunes cerrados.
- **PRF-020.** Una persona del salón **debe** poder cambiar los ajustes de la reserva. Valores válidos y valores iniciales:

| Ajuste | Valores válidos | Inicial |
| --- | --- | --- |
| Capacidad (citas a la vez) | Entero de 1 a 10 | 2 |
| Intervalo entre horas ofrecidas | 10, 15, 20, 30 o 60 minutos | 15 |
| Antelación mínima | Minutos enteros de 0 a 10.080 (7 días) | 120 |
| Antelación máxima | Días enteros de 1 a 365 | 60 |
| Plazo para cancelar | Horas enteras de 0 a 168 antes de la cita | 24 |

### Cierres y bloqueos

- **PRF-021.** Una persona del salón **debe** poder crear un cierre con inicio y fin (fecha y hora), un motivo opcional de hasta 150 caracteres y una reducción de capacidad: «cierre total» o un número de 1 a 10 de citas a la vez que se restan a la capacidad durante ese periodo (por ejemplo, 1 cuando una peluquera está de vacaciones). El fin debe ser posterior al inicio.
- **PRF-022.** Si un cierre nuevo coincide con citas confirmadas, el panel **debe** guardarlo, avisar con «Hay N citas confirmadas en ese periodo. No se han cancelado: revísalas en la agenda.» (con una sola cita: «Hay 1 cita confirmada en ese periodo. No se ha cancelado: revísala en la agenda.») y **no debe** cancelar ninguna cita por su cuenta.
- **PRF-023.** La lista de cierres **debe** mostrar los que todavía no han terminado, ordenados por inicio. Sin cierres futuros, muestra «No hay cierres previstos.».
- **PRF-024.** Una persona del salón **debe** poder eliminar un cierre tras confirmarlo. Las horas que liberaba vuelven a estar disponibles.

### Disponibilidad

Una hora de inicio está **disponible** para un servicio cuando se cumple todo esto:

1. La cita entera (de la hora de inicio a la hora de inicio más la duración) cabe dentro de un mismo tramo de apertura de ese día.
2. En ningún momento de la cita, el número de citas confirmadas que se solapan con ella alcanza la capacidad efectiva. La capacidad efectiva en cada momento es la capacidad del ajuste menos las reducciones de los cierres que cubren ese momento; un cierre total la deja en 0.
3. Solo en la reserva pública: la hora coincide con el intervalo de horas ofrecidas contado desde el inicio del tramo, no es anterior a ahora más la antelación mínima y su día no es posterior a hoy más la antelación máxima.

Dos citas se solapan si una empieza antes de que termine la otra. Una cita que termina a las 10:00 no se solapa con una que empieza a las 10:00. Las citas canceladas no ocupan capacidad.

- **PRF-025.** La página pública y el panel **deben** calcular la disponibilidad con las reglas anteriores.

Ejemplos resueltos (capacidad 2, intervalo de 15 minutos, tramo de 09:00 a 19:00, sin antelación mínima):

| Situación | Servicio | Hora | ¿Disponible? |
| --- | --- | --- | --- |
| Sin citas | 60 min | 18:00 | Sí (termina a las 19:00) |
| Sin citas | 60 min | 18:15 | No (termina después del cierre) |
| Citas 10:00–11:00 y 10:30–11:30 | 30 min | 10:30 | No (2 citas a la vez a las 10:30) |
| Citas 10:00–11:00 y 10:30–11:30 | 30 min | 11:00 | Sí (a las 11:00 solo queda 1) |
| Cita 10:00–12:00 y cierre con reducción 1 de 11:00 a 13:00 | 30 min | 11:30 | No (capacidad efectiva 1, ocupada 1) |
| Cierre total de 14:00 a 15:00 | 90 min | 13:00 | No (atraviesa el cierre) |

- **PRF-026.** Dos reservas enviadas a la vez para la última plaza de una hora **no deben** acabar en dos citas. Una se confirma y la otra recibe el aviso de hueco ocupado (PRF-034).

### Reserva pública

- **PRF-027.** La web **debe** tener una página de reservas propia que muestre los servicios reservables online por orden, con su nombre y duración (por ejemplo, «1 h 30 min»), sin precio. Sin servicios reservables, muestra «Ahora mismo no se pueden hacer reservas online. Llámanos al 633 912 050 o escríbenos por WhatsApp.».
- **PRF-028.** Al elegir un servicio, la página **debe** mostrar un calendario mensual en el que solo se pueden elegir los días con al menos una hora disponible. Los días sin horas, pasados o fuera de la antelación máxima, se ven pero no se pueden elegir. El calendario permite pasar de mes, sin ir a meses anteriores al actual ni posteriores al del último día reservable.
- **PRF-029.** Al elegir un día, la página **debe** mostrar todas las horas disponibles de ese día para el servicio, en orden, y el formulario de datos.
- **PRF-030.** El formulario **debe** pedir: nombre (de 2 a 100 caracteres), teléfono (de 9 a 15 dígitos; se admiten espacios, guiones, paréntesis y un «+» inicial), email (válido, hasta 150 caracteres), observaciones opcionales (hasta 500 caracteres) y la casilla obligatoria «He leído la información sobre protección de datos». Junto al formulario debe aparecer la información básica sobre protección de datos: responsable, finalidad, base legal, destinatarios y derechos, con un enlace a la política de privacidad.
- **PRF-031.** Con los datos válidos y la hora todavía disponible, la reserva **debe** quedar confirmada en ese momento, sin aprobación del salón. El cliente llega a la página de su cita con el mensaje «Tu cita está confirmada.».
- **PRF-032.** Con algún dato no válido, la página **debe** volver a mostrar el formulario con el error junto al campo y los datos ya escritos. No se crea ninguna cita.
- **PRF-033.** La página **no debe** aceptar un servicio no reservable, una hora fuera del intervalo ofrecido, una hora pasada o fuera de la antelación mínima o máxima, aunque se envíe manipulando el formulario. Se muestra «Esa hora ya no está disponible. Elige otra.» y no se crea ninguna cita.
- **PRF-034.** Si la hora elegida se ha ocupado mientras el cliente rellenaba el formulario, la página **debe** mostrar «Esa hora ya no está disponible. Elige otra.» con las horas que sigan libres ese día y conservar los datos escritos. No se crea ninguna cita.
- **PRF-035.** Si ya existe una cita confirmada con el mismo email a la misma hora, la página **no debe** crear otra y debe mostrar «Ya tienes una cita confirmada a esa hora.».
- **PRF-036.** Un envío que rellena el campo trampa invisible para personas **no debe** crear ninguna cita ni enviar correos. Se responde como si hubiera ido bien, sin datos de ninguna cita.
- **PRF-037.** Desde una misma conexión, la página **no debe** aceptar más de 5 envíos del formulario en 1 minuto. El siguiente muestra «Demasiados intentos. Espera un minuto y vuelve a probar.».
- **PRF-038.** La página de reservas y la de la cita **no deben** guardarse en la caché del navegador ni de intermediarios, para que nadie vea horas ya ocupadas ni un formulario caducado.

### Página de la cita y cancelación

- **PRF-039.** Cada cita **debe** tener un enlace personal, imposible de adivinar (al menos 40 caracteres aleatorios), que abre su página con el servicio, el día y la hora, el nombre y el estado. El enlace no caduca.
- **PRF-040.** Un enlace que no corresponde a ninguna cita **debe** mostrar la página de «no encontrado» y no revelar ningún dato.
- **PRF-041.** El cliente **debe** poder cancelar su cita confirmada desde su página, tras confirmarlo, si falta al menos el plazo para cancelar del ajuste. La página muestra «Tu cita se ha cancelado.» y la hora vuelve a estar disponible.
- **PRF-042.** Pasado el plazo para cancelar, o con la cita ya empezada, la página **no debe** permitir cancelar y debe mostrar «Ya no se puede cancelar online. Llámanos al 633 912 050.».
- **PRF-043.** Una cita cancelada **no debe** poder volver a confirmarse ni cancelarse otra vez. Su página muestra «Esta cita está cancelada.».
- **PRF-044.** Las páginas de cita **no deben** aparecer en buscadores ni en el mapa del sitio.

### Agenda del panel

- **PRF-045.** La agenda **debe** mostrar las citas de un día (por defecto, hoy) ordenadas por hora, con hora de inicio y fin, servicio, nombre, teléfono, email, observaciones, origen (web o panel) y estado. Se puede ir al día anterior, al siguiente, a hoy o a una fecha concreta. Sin citas ese día, muestra «No hay citas este día.».
- **PRF-046.** Una persona del salón **debe** poder crear una cita desde el panel con servicio activo, fecha, hora (múltiplo de 5 minutos), nombre, teléfono, email opcional y observaciones. Se aplican las reglas 1 y 2 de disponibilidad, pero no la 3. Una cita del panel no puede empezar antes de ahora.
- **PRF-047.** Si la hora no está disponible, el panel **debe** mostrar «Esa hora no está disponible para este servicio.» y no crear la cita.
- **PRF-048.** Una persona del salón **debe** poder cancelar cualquier cita confirmada, tras confirmarlo, sin límite de plazo. La cita queda cancelada y su hora vuelve a estar disponible.
- **PRF-049.** El panel **no debe** permitir borrar citas: las canceladas se conservan en la agenda, marcadas como canceladas.

### Notificaciones

- **PRF-050.** Al confirmarse una cita con email, el cliente **debe** recibir un correo con el servicio, el día, la hora, la dirección del salón, el teléfono y el enlace personal a su cita (PRF-039). No lleva precio.
- **PRF-051.** Al confirmarse una cita reservada desde la web, el salón **debe** recibir un correo con todos los datos de la cita y el enlace a la agenda de ese día.
- **PRF-052.** Si el correo de PRF-050 o PRF-051 no se puede enviar, la cita **debe** quedar confirmada igualmente y el cliente ve la misma página de éxito. El fallo queda registrado para el salón.
- **PRF-053.** Una tarea periódica **debe** reintentar los avisos de PRF-050 y PRF-051 que no se enviaron, de citas confirmadas que todavía no han empezado y creadas hace más de 5 minutos. Cada aviso se envía como máximo una vez con éxito.
- **PRF-054.** Cuando el cliente cancela, **debe** recibir un correo que lo confirma, y el salón otro que lo avisa. Cuando cancela el salón, el cliente con email **debe** recibir un correo que le informa y le invita a reservar otra hora. Si alguno falla, la cancelación se mantiene y el fallo queda registrado, sin reintento.
- **PRF-055.** El despliegue **no debe** completarse si el correo saliente no está configurado para enviar de verdad, si el remitente no está definido o si falta la dirección del salón que recibe los avisos.

### Web pública, buscadores y privacidad

- **PRF-056.** El botón «Reservar cita →» de la cabecera, en escritorio y en móvil, **debe** llevar a la página de reservas. El teléfono de la barra superior y los botones de WhatsApp de las páginas de servicio se mantienen como están.
- **PRF-057.** La página de reservas **debe** ser indexable, con título y descripción propios, y aparecer en el mapa del sitio.
- **PRF-058.** Los datos estructurados del salón **deben** declarar como acción de reserva la página de reservas en lugar del teléfono. El teléfono se mantiene como punto de contacto.
- **PRF-059.** La política de privacidad **debe** explicar el tratamiento de los datos de las reservas: responsable, finalidad, base legal (medidas precontractuales a petición del interesado), encargados (alojamiento y correo), plazo de conservación y derechos. Los datos que el cliente no ha confirmado (titular, NIF, email de contacto y plazo de conservación) aparecen marcados como pendientes, sin valores inventados.
- **PRF-060.** La web **no debe** mostrar ninguna dirección de email de contacto que no sea real.
- **PRF-061.** La página de reservas **no debe** mostrar «€», rangos de precio, valoraciones autodeclaradas ni el texto «enviar mensaje», igual que el resto de páginas públicas.

### Añadidos tras la revisión independiente (2026-10-03, `.ai/reviews/reservas.md`)

- **PRF-062.** Un mismo email, o un mismo teléfono (comparado por sus 9 últimos dígitos, sea cual sea su formato), **no debe** tener más de 2 citas confirmadas futuras reservadas desde la web. La tercera muestra «Ya tienes 2 citas pendientes. Para reservar otra, cancela una o llámanos al 633 912 050.» y no se crea. Las citas del panel no tienen límite. Las canceladas y las pasadas no cuentan.
- **PRF-063.** Desde una misma conexión, la página **no debe** aceptar más de 10 envíos de reserva al día. El siguiente muestra «Se han hecho demasiadas reservas hoy desde esta conexión. Llámanos al 633 912 050.».
- **PRF-064.** La página de la cita **no debe** cargar herramientas de analítica de terceros ni publicar su propia dirección en etiquetas de la página, porque su dirección permite ver y cancelar la cita.
- **PRF-065.** El texto que escribe el cliente (nombre, observaciones) **no debe** convertirse en enlaces ni en formato en ningún correo.
- **PRF-066.** La agenda **debe** avisar con «Correo de confirmación no enviado…» en cada cita confirmada con email cuyo correo de confirmación todavía no se ha podido enviar.
- **PRF-067.** El despliegue **no debe** completarse si la cookie de sesión del panel puede viajar sin cifrar. Además, **no debe** empezar (con la web todavía en marcha) si falta la configuración del correo.
- **PRF-068.** El acceso al panel **no debe** comprobar más de 20 contraseñas por minuto desde una misma conexión, aunque cambie el email. Tampoco debe tardar menos con un email inexistente que con una contraseña errónea, ni ofrecer una sesión persistente («mantener la sesión abierta»).
- **PRF-069.** Si el servidor de correo no responde, cada intento de envío **debe** abandonarse como mucho a los 10 segundos, para que la página de éxito llegue al cliente (PRF-052).

## Datos existentes y transición

No hay citas, servicios ni cuentas previos. Al desplegar se crean el horario inicial (PRF-019) y los ajustes iniciales (PRF-020). No se crean servicios: los da de alta el salón. Hasta que exista al menos un servicio reservable, la página de reservas muestra el mensaje de PRF-027. Antes de publicar hay que crear al menos una cuenta del panel, configurar el correo y la dirección del salón (PRF-055) y completar los datos pendientes de la política de privacidad (PRF-059). Las citas que lleguen por teléfono o WhatsApp deben apuntarse en el panel para que ocupen su hueco.

## Criterios de aceptación

- **CA-1 (PRF-001 a PRF-009).** Sin sesión no se ve ninguna pantalla del panel. Con una cuenta creada desde la consola se entra, se navega por los cinco módulos y se sale. El sexto intento fallido en un minuto se bloquea. No hay registro público ni credenciales en el repositorio.
- **CA-2 (PRF-010 a PRF-016).** Los servicios se crean y se editan con validación, no se pueden borrar, el precio no sale en la web ni en los correos, y los no activos o no reservables no se ofrecen.
- **CA-3 (PRF-017 a PRF-024).** El horario parte de martes a sábado de 9 a 19, admite dos tramos y rechaza los tramos imposibles. Los ajustes se guardan dentro de sus límites. Los cierres reducen la capacidad, avisan de las citas afectadas sin cancelarlas y se pueden eliminar.
- **CA-4 (PRF-025, PRF-026).** Los seis ejemplos resueltos dan el resultado de la tabla, y una hora ocupada entre mostrar el formulario y enviarlo no genera una cita de más.
- **CA-5 (PRF-027 a PRF-038).** Un cliente reserva de principio a fin. Los datos no válidos, las horas manipuladas u ocupadas, los duplicados, el campo trampa y el exceso de envíos se rechazan sin crear citas. Las páginas se sirven sin caché pública.
- **CA-6 (PRF-039 a PRF-044).** El enlace personal muestra la cita, permite cancelar dentro del plazo y lo impide fuera de él. Un enlace falso da «no encontrado». Las páginas de cita no se indexan.
- **CA-7 (PRF-045 a PRF-049).** La agenda muestra y navega por días, crea citas aplicando capacidad y horario y cancela sin borrar.
- **CA-8 (PRF-050 a PRF-055).** Se envían los correos de confirmación, aviso y cancelación. Un fallo de envío no rompe la reserva y la tarea periódica lo reintenta una sola vez con éxito. El despliegue falla sin correo configurado.
- **CA-9 (PRF-056 a PRF-061).** La cabecera, el mapa del sitio y los datos estructurados apuntan a la reserva. La política explica el tratamiento con los datos pendientes marcados. No queda el email inventado ni hay precios en la página de reservas.

## Plan de verificación

| Punto | Escenario |
| --- | --- |
| PRF-001 | Sin sesión, abrir la agenda → se ve la pantalla de acceso. |
| PRF-002 | Cuenta existente, email y contraseña correctos → agenda de hoy. |
| PRF-003 | Contraseña errónea → «Email o contraseña incorrectos.», sin sesión. |
| PRF-004 | 5 intentos fallidos y un sexto con la contraseña buena → mensaje de demasiados intentos, sin sesión. |
| PRF-005 | Buscar una pantalla de registro → no existe. |
| PRF-006 | Crear una cuenta desde la consola con una contraseña de 11 caracteres → rechazada. Con 12 → creada. Con un email existente y respuesta «no» → contraseña sin cambiar. |
| PRF-007 | Buscar en el repositorio cuentas o contraseñas del panel → ninguna. |
| PRF-008 | Cerrar sesión y volver a la agenda → pantalla de acceso. |
| PRF-009 | En cada módulo se ve el mismo menú con los cinco módulos. |
| PRF-010 | Alta con duración 47 → error. Con duración 45 → servicio creado. |
| PRF-011 | Editar el nombre de un servicio → se guarda. Duración 0 → error y sin cambios. |
| PRF-012 | Tres servicios con órdenes 2, 1, 1 → aparecen por orden y nombre. Sin servicios → mensaje de lista vacía. |
| PRF-013 | No existe acción de borrar. Un servicio desactivado conserva sus citas. |
| PRF-014 | Servicio con precio 35,00 → ni la página de reservas ni el correo al cliente contienen «35» ni «€». |
| PRF-015 | Cita de 60 min y servicio cambiado a 90 → la cita sigue terminando a la misma hora. |
| PRF-016 | Servicio no reservable online → no sale en la página pública. Servicio no activo → no sale en el alta de citas del panel. |
| PRF-017 | Martes de 09:00 a 13:00 y de 15:00 a 19:00 → se guarda y a las 14:00 no hay horas. |
| PRF-018 | Tramo de 13:00 a 09:00, o tramos solapados → error y la semana no cambia. |
| PRF-019 | Instalación nueva → martes a sábado de 09:00 a 19:00, lunes y domingo cerrados. |
| PRF-020 | Capacidad 0 o intervalo de 25 → error. Capacidad 3 → guardada. Instalación nueva → valores iniciales. |
| PRF-021 | Cierre con fin anterior al inicio → error. Cierre con reducción 1 → creado. |
| PRF-022 | Cierre que cubre 2 citas → aviso «Hay 2 citas…» y las dos siguen confirmadas. |
| PRF-023 | Un cierre pasado y dos futuros → solo se listan los futuros, por inicio. |
| PRF-024 | Eliminar un cierre total → las horas vuelven a estar disponibles. |
| PRF-025 | Los seis ejemplos resueltos de la tabla. |
| PRF-026 | Ocupar la última plaza después de mostrar el formulario y enviarlo → aviso de hueco ocupado y una sola cita a esa hora. |
| PRF-027 | Dos servicios reservables y uno no → se listan dos, con duración y sin precio. Ninguno → mensaje de PRF-027. |
| PRF-028 | Lunes cerrado → no se puede elegir. No se puede ir a meses anteriores al actual ni más allá del límite. |
| PRF-029 | Día con citas → solo se listan las horas libres. |
| PRF-030 | Teléfono «12» → error. Sin la casilla → error. Se ve la información básica de protección de datos. |
| PRF-031 | Datos válidos → cita confirmada y página de la cita con «Tu cita está confirmada.». |
| PRF-032 | Email no válido → error, datos conservados, sin cita. |
| PRF-033 | Enviar una hora fuera del intervalo, pasada o un servicio no reservable → rechazado sin cita. |
| PRF-034 | Hora ocupada antes de enviar → aviso y sin cita. |
| PRF-035 | Repetir el mismo envío → una sola cita y el aviso de duplicado. |
| PRF-036 | Campo trampa relleno → respuesta de éxito, sin cita ni correo. |
| PRF-037 | Sexto envío en un minuto → mensaje de demasiados intentos. |
| PRF-038 | Las páginas de reserva y de cita no se sirven con caché pública. |
| PRF-039 | Enlace de una cita → muestra sus datos. El enlace tiene al menos 40 caracteres. |
| PRF-040 | Enlace inventado → «no encontrado». |
| PRF-041 | Cita dentro de 3 días con plazo de 24 h → se cancela y la hora queda libre. |
| PRF-042 | Cita dentro de 2 horas con plazo de 24 h → no se puede cancelar y se ve el mensaje. |
| PRF-043 | Cita cancelada → «Esta cita está cancelada.» y no se puede volver a cancelar. |
| PRF-044 | La página de cita indica que no se indexe y no está en el mapa del sitio. |
| PRF-045 | Día con 2 citas → listadas por hora. Día vacío → mensaje. La navegación cambia de día. |
| PRF-046 | Cita del panel sin email a una hora libre → creada con origen «panel». |
| PRF-047 | Hora con la capacidad completa → mensaje y sin cita. |
| PRF-048 | Cancelar desde el panel una cita de dentro de 1 hora → cancelada. |
| PRF-049 | No existe acción de borrar citas. Las canceladas siguen en la agenda. |
| PRF-050 | Reserva web → correo al cliente con el enlace y sin precio. |
| PRF-051 | Reserva web → correo al salón. Cita del panel → sin correo al salón. |
| PRF-052 | Correo que falla → cita confirmada, página de éxito y aviso pendiente. |
| PRF-053 | Aviso pendiente de hace 10 minutos → la tarea lo envía. Una segunda ejecución no lo repite. Uno de hace 2 minutos no se envía todavía. |
| PRF-054 | Cancelación del cliente → correo al cliente y al salón. Cancelación del salón → correo al cliente. |
| PRF-055 | Comprobación de despliegue con el correo en modo registro, sin remitente o sin la dirección del salón → falla. |
| PRF-056 | El botón de la cabecera lleva a la página de reservas. |
| PRF-057 | El mapa del sitio contiene la página de reservas. La página es indexable. |
| PRF-058 | La acción de reserva de los datos estructurados apunta a la página de reservas. |
| PRF-059 | La política de privacidad contiene las secciones de reservas y los datos pendientes marcados. |
| PRF-060 | Ninguna página contiene el email inventado. |
| PRF-061 | La página de reservas pasa la misma comprobación sin precios que el resto. |

## Riesgos y marcha atrás

- **Citas por teléfono o WhatsApp sin apuntar.** La web ofrecería horas que en realidad están ocupadas. Se mitiga con el alta rápida en la agenda (PRF-046) y explicándoselo al salón.
- **Correo sin configurar.** Los clientes no recibirían su enlace. Lo impide PRF-055, y PRF-053 recupera los fallos puntuales.
- **Caché de páginas.** Una página de reservas cacheada mostraría horas ocupadas. Lo impide PRF-038. Hay que confirmar que el servidor web no añade su propia caché.
- **Datos legales pendientes.** No se puede publicar hasta completar PRF-059.
- **Marcha atrás.** Los cambios en la base de datos solo añaden tablas. Para retirar la funcionalidad basta con revertir los PRs: el botón vuelve a abrir una llamada y las tablas nuevas se quedan sin uso, sin afectar al resto de la web.

## Preguntas abiertas

| Pregunta | Responsable | Valor por defecto | Consecuencia |
| --- | --- | --- | --- |
| Titular, NIF y email de contacto para la política de privacidad | Cliente | Marcado como pendiente | Bloquea la publicación |
| Plazo de conservación de las citas | Cliente | Marcado como pendiente | Bloquea la publicación. La purga automática queda para el PR 5 |
| Buzón y servidor de correo de envío y dirección del salón | Usuario y cliente | Sin valor | El despliegue falla hasta configurarlos (PRF-055) |
| Lista real de servicios y duraciones | Cliente | Ninguno sembrado | La página de reservas muestra el mensaje sin servicios |

## Tareas

[Índice de tareas](../tasks/reservas/index.md)
