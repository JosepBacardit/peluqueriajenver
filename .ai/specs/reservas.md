# Reservas online y panel de gestión

**Estado:** In progress (aprobada por el usuario el 2026-10-03).

## Flujo de trabajo

ARCHITECTURAL. La web pasa de ser solo informativa a guardar datos de clientes, tener cuentas de acceso, enviar correo y necesitar una tarea periódica. Se entrega en cuatro PRs apilados (base del panel, disponibilidad y agenda, reserva pública, notificaciones) para que cada uno se pueda revisar por separado. No se publica hasta tener los cuatro.

## Resultado para el usuario

- Un **cliente** que entra en la web puede elegir uno o varios servicios, ver en un calendario los días y horas libres y reservar una cita sin llamar. Recibe un correo de confirmación con un enlace para cancelarla.
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
- Remitente y marca de los correos salientes con la identidad del salón (2026-10-05).
- Edición de una cita confirmada desde el panel: día, hora, servicio y datos de la clienta (2026-10-05).
- Botones «Reservar cita» de la portada y las páginas de servicio enlazando a la reserva online (2026-10-05).
- Adaptación del panel y de la reserva pública al móvil, donde el salón trabaja la mayor parte del tiempo (2026-10-05).
- Vistas Día, Semana y Mes en la agenda del panel, con selector y navegación compartible por URL (2026-10-05).
- Rejilla horaria de Día y Semana al estilo Google Calendar, con carriles por capacidad y creación de cita tocando un hueco libre (2026-10-05).
- Varios servicios en una misma cita (de 1 a 5), en la reserva online, el panel, la agenda y los correos (2026-10-06).

## No-objetivos

- Elegir peluquera o asignar citas a una persona concreta. **Descartado** por decisión del usuario: el salón trabaja con una capacidad común.
- Varios servicios en una misma cita. **Retomado el 2026-10-06** a petición del usuario (por ejemplo, «corte de pelo hombre» y «arreglo de barba» en la misma cita): ver PRF-125 y siguientes.
- Cambiar la fecha u hora de una cita ya hecha **desde la página de la clienta**. **Pospuesto**: el cliente sigue cancelando y reservando otra vez. Revisado el 2026-10-05: el salón sí puede moverla desde el panel (PRF-077 y siguientes); solo queda pospuesto el autoservicio de la clienta.
- Recordatorio por correo antes de la cita y borrado automático de citas antiguas. **Pospuesto** al PR 5.
- Calendario sin recargar la página. **Pospuesto** al PR 5.
- Avisos por WhatsApp o SMS. **Descartado**: tienen coste y requieren un proveedor externo.
- Pago o señal al reservar. **Descartado**: no se ha pedido.
- Fichas de cliente, contabilidad, productos o puntos. **Pospuesto** a módulos futuros.
- Permisos distintos entre cuentas del panel. **Descartado**: todas las cuentas pueden hacer lo mismo.
- Recuperar la contraseña desde la web. **Pospuesto**: se cambia desde la consola del servidor.
- Crear una cita del panel a partir de un hueco libre mostrado directamente en la agenda (preseleccionando día y hora). **Retomado el 2026-10-05**: con la rejilla horaria de Día y Semana (PRF-108 y siguientes) resultaba natural, y el usuario lo aprobó entonces (ver PRF-114).
- Selector de servicio en la rejilla horaria que resalte los huecos donde cabe su duración. **Retomado el 2026-10-05** en `feature/agenda-service-filter` (T042–T045, ver PRF-120 a PRF-124).

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
- **PRF-013.** El panel **no debe** permitir borrar servicios. Un servicio que ya no se ofrece se marca como no activo, y sus citas pasadas y futuras se conservan con el nombre y la duración que tenían al reservarse. Con varios servicios en una cita (PRF-125), cada uno conserva el nombre y la duración que tenía al reservarse (PRF-126).
- **PRF-014.** El precio interno **no debe** aparecer en ninguna página pública ni en ningún correo al cliente.
- **PRF-015.** Cambiar la duración de un servicio **no debe** cambiar la hora de fin de las citas ya existentes. Lo mismo con cada servicio de una cita con varios (PRF-126).
- **PRF-016.** Un servicio no activo o no reservable online **no debe** ofrecerse en la página pública de reservas. Un servicio no activo tampoco se ofrece al crear citas desde el panel. En una cita con varios servicios, basta con que uno no lo cumpla para rechazarla entera (PRF-128).

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

Una hora de inicio está **disponible** para un servicio (o para varios en la misma cita, con su duración total: PRF-125) cuando se cumple todo esto:

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

- **PRF-027.** La web **debe** tener una página de reservas propia que muestre los servicios reservables online por orden, con su nombre y duración (por ejemplo, «1 h 30 min»), sin precio. Sin servicios reservables, muestra «Ahora mismo no se pueden hacer reservas online. Llámanos al 633 912 050 o escríbenos por WhatsApp.». Se puede elegir más de un servicio para la misma cita (PRF-127).
- **PRF-028.** Al elegir un servicio, la página **debe** mostrar un calendario mensual en el que solo se pueden elegir los días con al menos una hora disponible. Los días sin horas, pasados o fuera de la antelación máxima, se ven pero no se pueden elegir. El calendario permite pasar de mes, sin ir a meses anteriores al actual ni posteriores al del último día reservable. Con varios servicios elegidos, cuenta la duración total (PRF-125).
- **PRF-029.** Al elegir un día, la página **debe** mostrar todas las horas disponibles de ese día para el servicio, en orden, y el formulario de datos. Con varios servicios, las horas son las de la duración total (PRF-125).
- **PRF-030.** El formulario **debe** pedir: nombre (de 2 a 100 caracteres), teléfono (de 9 a 15 dígitos; se admiten espacios, guiones, paréntesis y un «+» inicial), email (válido, hasta 150 caracteres), observaciones opcionales (hasta 500 caracteres) y la casilla obligatoria «He leído la información sobre protección de datos». Junto al formulario debe aparecer la información básica sobre protección de datos: responsable, finalidad, base legal, destinatarios y derechos, con un enlace a la política de privacidad.
- **PRF-031.** Con los datos válidos y la hora todavía disponible, la reserva **debe** quedar confirmada en ese momento, sin aprobación del salón. El cliente llega a la página de su cita con el mensaje «Tu cita está confirmada.».
- **PRF-032.** Con algún dato no válido, la página **debe** volver a mostrar el formulario con el error junto al campo y los datos ya escritos. No se crea ninguna cita.
- **PRF-033.** La página **no debe** aceptar un servicio no reservable, una hora fuera del intervalo ofrecido, una hora pasada o fuera de la antelación mínima o máxima, aunque se envíe manipulando el formulario. Se muestra «Esa hora ya no está disponible. Elige otra.» y no se crea ninguna cita. Tampoco más servicios de los permitidos (PRF-125) ni un servicio repetido (PRF-127).
- **PRF-034.** Si la hora elegida se ha ocupado mientras el cliente rellenaba el formulario, la página **debe** mostrar «Esa hora ya no está disponible. Elige otra.» con las horas que sigan libres ese día y conservar los datos escritos. No se crea ninguna cita.
- **PRF-035.** Si ya existe una cita confirmada con el mismo email a la misma hora, la página **no debe** crear otra y debe mostrar «Ya tienes una cita confirmada a esa hora.».
- **PRF-036.** Un envío que rellena el campo trampa invisible para personas **no debe** crear ninguna cita ni enviar correos. Se responde como si hubiera ido bien, sin datos de ninguna cita.
- **PRF-037.** Desde una misma conexión, la página **no debe** aceptar más de 5 envíos del formulario en 1 minuto. El siguiente muestra «Demasiados intentos. Espera un minuto y vuelve a probar.».
- **PRF-038.** La página de reservas y la de la cita **no deben** guardarse en la caché del navegador ni de intermediarios, para que nadie vea horas ya ocupadas ni un formulario caducado.

### Página de la cita y cancelación

- **PRF-039.** Cada cita **debe** tener un enlace personal, imposible de adivinar (al menos 40 caracteres aleatorios), que abre su página con el servicio, el día y la hora, el nombre y el estado. El enlace no caduca; solo se sustituye por otro si el salón cambia el email de la cita (PRF-087). Con varios servicios, la página los lista todos con la duración total (PRF-130).
- **PRF-040.** Un enlace que no corresponde a ninguna cita **debe** mostrar la página de «no encontrado» y no revelar ningún dato.
- **PRF-041.** El cliente **debe** poder cancelar su cita confirmada desde su página, tras confirmarlo, si falta al menos el plazo para cancelar del ajuste. La página muestra «Tu cita se ha cancelado.» y la hora vuelve a estar disponible.
- **PRF-042.** Pasado el plazo para cancelar, o con la cita ya empezada, la página **no debe** permitir cancelar y debe mostrar «Ya no se puede cancelar online. Llámanos al 633 912 050.».
- **PRF-043.** Una cita cancelada **no debe** poder volver a confirmarse ni cancelarse otra vez. Su página muestra «Esta cita está cancelada.».
- **PRF-044.** Las páginas de cita **no deben** aparecer en buscadores ni en el mapa del sitio.

### Agenda del panel

- **PRF-045.** La agenda **debe** mostrar las citas de un día (por defecto, hoy) ordenadas por hora, con hora de inicio y fin, servicio, nombre, teléfono, email, observaciones, origen (web o panel) y estado. Se puede ir al día anterior, al siguiente, a hoy o a una fecha concreta. Sin citas ese día, muestra «No hay citas este día.».
- **PRF-046.** Una persona del salón **debe** poder crear una cita desde el panel con servicio activo, fecha, hora (múltiplo de 5 minutos), nombre, teléfono, email opcional y observaciones. Se aplican las reglas 1 y 2 de disponibilidad, pero no la 3. Una cita del panel no puede empezar antes de ahora. La cita del panel puede tener de 1 a 5 servicios activos (PRF-129).
- **PRF-047.** Si la hora no está disponible, el panel **debe** mostrar «Esa hora no está disponible para este servicio.» y no crear la cita.
- **PRF-048.** Una persona del salón **debe** poder cancelar cualquier cita confirmada, tras confirmarlo, sin límite de plazo. La cita queda cancelada y su hora vuelve a estar disponible.
- **PRF-049.** El panel **no debe** permitir borrar citas: las canceladas se conservan en la agenda, marcadas como canceladas.

### Notificaciones

- **PRF-050.** Al confirmarse una cita con email, el cliente **debe** recibir un correo con el servicio, el día, la hora, la dirección del salón, el teléfono y el enlace personal a su cita (PRF-039). No lleva precio. Con varios servicios, el correo los lista todos con la duración total (PRF-130).
- **PRF-051.** Al confirmarse una cita reservada desde la web, el salón **debe** recibir un correo con todos los datos de la cita y el enlace a la agenda de ese día. Con varios servicios, el aviso al salón los lista todos con la duración total (PRF-130).
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

### Remitente y marca de los correos (2026-10-05)

- **PRF-070.** Todo correo que envía la aplicación (confirmación, aviso al salón, cancelación) **debe** mostrar como remitente «Peluquería Jenver», nunca el nombre de una plantilla genérica.
- **PRF-071.** El despliegue **no debe** completarse si el nombre de la aplicación o el remitente del correo siguen siendo los de la plantilla por defecto del framework (amplía PRF-055).
- **PRF-072.** Los correos al cliente y al salón **deben** mostrar la identidad visual del salón: los colores negro y dorado, y el logo o el nombre «Peluquería Jenver», en vez del tema genérico por defecto.
- **PRF-073.** El pie de cada correo **debe** mostrar los datos de contacto del salón, no un aviso de derechos de autor de la plantilla.
- **PRF-074.** Los correos **no deben** enlazar ni mostrar ninguna marca ajena al salón (por ejemplo, un logotipo o un enlace a la página de un tercero).
- **PRF-075.** Cuando el correo use una imagen del logo, **debe** cargarse desde una dirección pública, no desde el propio mensaje, y llevar un texto alternativo con el nombre del salón, para que el correo siga siendo comprensible en los clientes que bloquean las imágenes por defecto (Outlook y Gmail, entre otros).

La imagen del logo de los correos es una excepción a la regla general de servir toda imagen en WebP (decisión del usuario, 2026-10-05): se sirve en PNG porque el soporte de WebP en los clientes de correo, sobre todo Outlook de escritorio, es insuficiente.

### Edición de una cita desde el panel (2026-10-05)

- **PRF-077.** Una persona del salón **debe** poder cambiar el día, la hora y, si hace falta, el servicio de una cita confirmada que todavía no ha empezado, desde la agenda del panel. Con varios servicios, se puede cambiar la lista entera (PRF-129).
- **PRF-078.** Al guardar el cambio, se aplican las mismas reglas de disponibilidad que al crear una cita desde el panel (reglas 1 y 2 de la sección «Disponibilidad», sin la regla 3), sin contar la propia cita en el hueco que deja libre. Con varios servicios, la duración que se comprueba es la total (PRF-125).
- **PRF-079.** Si el hueco elegido no tiene capacidad suficiente o cae fuera del horario de apertura, el panel **debe** avisarlo con un mensaje que lo explique antes de guardar nada.
- **PRF-080.** Tras ese aviso, una persona del salón **debe** poder confirmar que quiere guardar el cambio igualmente. El panel **no debe** guardar un cambio con un hueco sin capacidad o fuera de horario sin esa confirmación explícita.
- **PRF-081.** Una persona del salón **debe** poder editar también el nombre, el teléfono, el email y las observaciones de la clienta al mover la cita, con las mismas reglas de los campos que al crear una cita del panel (PRF-046).
- **PRF-082.** Si la cita tiene email, la clienta **debe** recibir un correo que informe del cambio con la nueva fecha y hora y el mismo enlace personal a su cita (PRF-039), salvo que en el mismo cambio se haya cambiado su email (PRF-087). Con varios servicios, el correo los lista todos con la duración total (PRF-130).
- **PRF-083.** Si el correo de PRF-082 no se puede enviar, el cambio **debe** mantenerse igualmente y el fallo queda registrado para el salón, sin reintento automático (igual que PRF-054).
- **PRF-084.** Una cita cancelada, o una cuya hora ya ha pasado, **no debe** poder moverse: el panel no ofrece la opción de editar en esos casos.
- **PRF-085.** Mover una cita **no debe** dejar ningún rastro del cambio en ningún campo visible para la clienta (por ejemplo, las observaciones). No se guarda un historial de los cambios de hora.
- **PRF-086.** Dos cambios a la vez sobre la misma cita, o un cambio y una cancelación a la vez, **no deben** dejar un resultado mezclado: el segundo en completarse ve el estado que dejó el primero y actúa en consecuencia, igual que al crear una cita (PRF-026). Si una persona del salón guarda un formulario de edición abierto antes de que otra cambiara la cita, el panel **no debe** guardarlo: avisa y muestra los datos actuales.
- **PRF-087.** Si al editar una cita cambia el email de la clienta, la cita **debe** recibir un enlace personal nuevo y el anterior **debe** dejar de funcionar, para que quien recibió el enlace en la dirección anterior (por ejemplo, una dirección equivocada) ya no pueda ver ni cancelar la cita. La clienta **debe** recibir en la dirección nueva un correo con su cita y el enlace nuevo; si en el mismo cambio también se mueve la cita, recibe un solo correo, el de cambio de PRF-082, con el enlace nuevo. Si ese correo no se puede enviar, el panel **debe** avisar al salón de que la clienta se ha quedado sin enlace válido, y la tarea periódica de PRF-053 lo reintenta (a diferencia de PRF-083, porque sin ese correo la clienta no tiene ningún enlace que funcione). Decisión del usuario, 2026-10-05, tras la revisión independiente (`.ai/reviews/booking-admin-tweaks.md`, L3).

### Botones «Reservar cita» de la portada y las páginas de servicio (2026-10-05)

- **PRF-076.** El botón «Reservar cita →» del inicio de la portada y el de cada página de servicio **deben** llevar a la página de reservas, igual que el de la cabecera (PRF-056). Los botones que invitan explícitamente a llamar (por ejemplo, «Llamar ahora») y los de WhatsApp se mantienen como están.
- **PRF-088.** La sección «Reserva tu cita» de la portada **debe** ofrecer también un botón «Reservar online» que lleve a la página de reservas, sin quitar el teléfono ni WhatsApp, y las preguntas frecuentes sobre cómo pedir cita **deben** mencionar la reserva online sin prometer nada que el sistema no haga (por ejemplo, que se pueda reservar online cualquier servicio). Decisión del usuario, 2026-10-05 (revisión `booking-admin-tweaks`, L10).

### Adaptación móvil del panel y la reserva pública (2026-10-05)

El panel lo usan sobre todo las peluqueras desde el móvil, con una mano, mientras atienden citas que llegan por teléfono o WhatsApp. La reserva pública la usan sobre todo las clientas desde el móvil. Decisión del usuario, 2026-10-05, tras la auditoría móvil de fase 1 del agente `programador`.

- **PRF-089.** Los botones y los enlaces de acción frecuente del panel (Editar, Cancelar cita, Eliminar, Guardar, Nueva cita, los módulos del menú, Cerrar sesión) **deben** tener una zona táctil de al menos 44×44 px.
- **PRF-090.** En anchos de pantalla menores de 768 px, el panel **debe** mostrar un botón de menú (hamburguesa) de al menos 44×44 px en la cabecera que, al abrirse, despliega una lista vertical con los módulos, cada uno con una zona táctil de al menos 44 px y la página activa marcada; «Cerrar sesión» va dentro de ese menú, al final y separado de los módulos. El menú **debe** poder desplazarse internamente sin salirse de la pantalla cuando crece más allá de los módulos actuales. Sin JavaScript, el menú **no debe** depender del botón para verse: queda visible de forma permanente. En pantallas de 768 px o más se mantiene el menú superior horizontal actual, sin cambios. Decisión del usuario, 2026-10-05: sustituye a la navegación inferior fija de la primera versión de este punto, porque el panel va a crecer con más apartados y una barra inferior no escala tan bien como un menú desplegable con *scroll*.
- **PRF-091.** La lista de servicios **debe** mostrarse como tarjetas, en todos los anchos de pantalla, sin tabla con *scroll* horizontal.
- **PRF-092.** La agenda **debe** ofrecer un acceso directo a «Mañana», además de «Hoy» y de ir a un día anterior, siguiente o concreto.
- **PRF-093.** La agenda **debe** tener, en anchos de pantalla menores de 768 px, un botón flotante para crear una cita nueva, siempre visible sin desplazarse por la lista de citas, que no tape la última cita ni quede oculto tras la zona segura del dispositivo (recorte inferior del iPhone).
- **PRF-094.** En la tarjeta de cada cita, «Editar» y «Cancelar cita» **deben** tener zonas táctiles independientes y visualmente distintas entre sí (PRF-089), para no confundir una acción destructiva con una que no lo es.
- **PRF-095.** Cuando la cita tiene teléfono, su tarjeta en la agenda **debe** ofrecer, además de llamar, abrir WhatsApp con la clienta en un toque, con el teléfono normalizado a formato internacional (prefijo `+34` cuando no lleve ninguno) y sin romperse con un teléfono mal formado.
- **PRF-096.** El horario semanal **no debe** producir *scroll* horizontal ni recortar los campos de hora en anchos de pantalla de 360 px.
- **PRF-097.** El calendario y las horas de la página de reservas **deben** tener una zona táctil de al menos 44 px de alto.
- **PRF-098.** El botón para cancelar la cita en la página personal de la clienta **debe** ocupar todo el ancho disponible en el móvil, para que sea fácil de tocar.

### Vistas Día, Semana y Mes de la agenda (2026-10-05)

Decisión del usuario, 2026-10-05, tras el informe de fase 1 del agente `programador`: la agenda pasa de mostrar solo el día a ofrecer también una vista semanal y una mensual, hechas a mano con Blade (sin librería de calendario ni framework de JS, igual que el resto del panel). La vista Día no cambia: sigue siendo la lista de tarjetas actual.

- **PRF-099.** La agenda **debe** ofrecer un selector Día/Semana/Mes, con zona táctil de al menos 44×44 px, cuya vista activa se refleje en el parámetro `vista` de la URL (junto con `fecha`), para que el enlace se pueda compartir y el botón Atrás del navegador funcione. Un valor de `vista` que no sea «dia», «semana» ni «mes» **debe** tratarse como «dia». La vista por defecto, sin `vista` en la URL, **debe** ser Día; la agenda **no debe** recordar la última vista usada entre visitas.
- **PRF-100.** En anchos de pantalla de 768 px o más, la vista Semana **debe** mostrar una rejilla de 7 columnas (lunes a domingo) con las citas de cada día. **Reescrito el 2026-10-05** (ver PRF-118): la rejilla de columnas con listas de texto se sustituye por la rejilla horaria de PRF-108 y siguientes, repetida en 7 columnas.
- **PRF-101.** En anchos de pantalla menores de 768 px, la vista Semana **debe** mostrar una tira de 7 días (zona táctil de al menos 44 px cada uno) para elegir el día, con la agenda de ese día —igual que la vista Día— mostrada debajo. **Ajustado el 2026-10-05**: la agenda del día elegido es ahora la rejilla horaria de PRF-108 y siguientes (ver PRF-118), no la lista de tarjetas; sus acciones (Llamar, WhatsApp, Editar, Cancelar) se mantienen en la lista de tarjetas debajo de la rejilla.
- **PRF-102.** La vista Mes **debe** mostrar, en todos los anchos de pantalla, una rejilla mensual con el número de citas confirmadas de cada día.
- **PRF-103.** Tocar cualquier día de la vista Mes **debe** abrir la vista Día de esa fecha, tenga o no citas.
- **PRF-104.** Los controles Anterior, Siguiente y Hoy de la agenda **deben** adaptarse a la vista activa: un día en Día (igual que ahora, con el atajo «Mañana»), una semana en Semana y un mes en Mes. El formulario «Ir a la fecha» **debe** conservar la vista activa al cambiar de fecha.
- **PRF-105.** Un día sin horario semanal (PRF-017) o cubierto por un cierre total (PRF-021) **debe** marcarse como «Cerrado» con texto, no solo con color, en las vistas Semana y Mes. Un cierre de capacidad reducida (sin cerrar el día) también **debe** señalarse, igual que ya hace la vista Día. Decisión del usuario, 2026-10-05, tras la revisión independiente (`.ai/reviews/agenda-calendar-views.md`, M2/L2): las celdas de día de Semana y Mes pueden medir entre 39 y 43 px de ancho a 360 px, manteniendo los 44 px de alto que sí exige PRF-099.
- **PRF-106.** El día de hoy **debe** distinguirse en las vistas Semana y Mes con un borde u otro indicador que no sea solo el color.
- **PRF-107.** Las rejillas de la vista Semana (768 px o más) y de la vista Mes **deben** tener una semántica de rejilla accesible (roles `grid`/`columnheader`) y navegarse con el teclado, igual que el calendario de `/reservas` (PRF-028). **Ajustado el 2026-10-05** (ver PRF-118): la rejilla horaria de Semana en escritorio ya no es tabular en su cuerpo (es una línea de tiempo, como Día), así que solo conserva `role="row"`/`"columnheader"` en su fila de cabecera; Mes y el calendario público mantienen la semántica de rejilla completa.

### Rejilla horaria de Día y Semana, al estilo Google Calendar (2026-10-05)

Decisión del usuario, 2026-10-05: para encontrar huecos libres al apuntar una cita por teléfono, la vista Día pasa de la lista de tarjetas a una rejilla horaria (eje de horas + citas como bloques), igual que Google Calendar pero en negro y dorado. Semana en escritorio usa la misma rejilla en 7 columnas; Semana en móvil sigue mostrando la tira de 7 días con la rejilla del día elegido debajo. Mes no cambia. La lista de tarjetas de cita (Llamar, WhatsApp, Editar, Cancelar) se mantiene tal cual, debajo de la rejilla.

- **PRF-108.** La vista Día **debe** mostrar una rejilla horaria con el eje de horas a la izquierda, desde la apertura más temprana al cierre más tardío de toda la semana (redondeado a la hora), con líneas de hora y de media hora, a 88 px por hora (44 px por media hora).
- **PRF-109.** Cada columna de día **debe** tener tantos carriles como la capacidad configurada (hoy 2, leída de `booking_settings`, no fija en el código). Una cita sola **debe** ocupar un carril, no todo el ancho de la columna. Si hay más citas confirmadas simultáneas que la capacidad (por un «Guardar igualmente» de PRF-080), **deben** aparecer carriles adicionales para esas citas, marcadas con el texto «Sobre capacidad»; los carriles **no deben** distinguir peluquera.
- **PRF-110.** Las citas confirmadas **deben** asignarse a los carriles por el algoritmo de primer carril libre (ordenadas por hora de inicio). Las citas canceladas **no deben** ocupar ningún carril (siguen viéndose, atenuadas, en la lista de tarjetas).
- **PRF-111.** Cada bloque de cita **debe** mostrar la hora, el servicio y la clienta, con recorte (`ellipsis`) si no caben; el detalle completo **debe** estar en su `aria-label` y su `title`.
- **PRF-112.** Un hueco entre los tramos de un día que sí tiene horario (por ejemplo, una pausa de mediodía), o antes/después de sus tramos dentro del rango de la rejilla, **debe** verse como una banda sombreada con el texto «Fuera de horario». Un día sin ningún tramo **debe** verse como una banda «Cerrado» ocupando toda la rejilla. Un cierre puntual (PRF-021) total, o que deja la capacidad efectiva en 0, **debe** verse como una banda «Cierre». Ninguno de los tres **debe** depender solo del color. Si una cita confirmada se forzó («Guardar igualmente», PRF-080) dentro de uno de estos tres casos — un día sin tramo, un hueco «fuera de horario» o un cierre total —, **debe** seguir viéndose en su carril (revisión `agenda-timeline-grid`, hallazgos H1 y M1), con el resto del tramo banda normal alrededor; nunca debe quedar invisible solo porque el carril que ocupa también cuenta para PRF-109.
- **PRF-113.** Un cierre puntual que reduce la capacidad sin cerrarla del todo **debe** sombrear con «Cierre» solo los carriles por encima de la capacidad efectiva en ese tramo, dejando libres los carriles restantes.
- **PRF-114.** Un hueco libre de al menos 30 minutos en un carril **debe** ser una zona táctil de al menos 44 px de alto que, al tocarla, abra «Nueva cita» con la fecha y la hora de ese hueco y conserve `volver` (PRF-099). Un hueco libre de menos de 30 minutos **no** necesita ser su propia zona táctil.
- **PRF-115.** Tocar un bloque de cita **debe** llevar a su tarjeta de detalle en la lista de debajo (con Llamar, WhatsApp, Editar y Cancelar), sin una página ni un panel nuevos.
- **PRF-116.** El día de hoy **debe** mostrar una línea de «ahora» en su posición horaria dentro de la rejilla.
- **PRF-117.** Al abrir la vista Día o Semana, la rejilla **debe** desplazarse sola a la hora actual (si el día mostrado es hoy y «ahora» cae dentro del rango) o a la apertura, sin esperar ninguna interacción.
- **PRF-118.** La vista Semana en pantallas de 768 px o más **debe** usar la misma rejilla horaria en 7 columnas, con el eje de horas una sola vez. En pantallas menores de 768 px, la tira de 7 días **debe** seguir mostrando debajo la rejilla horaria del día elegido (la vista Día).
- **PRF-119.** La rejilla **debe** ser navegable por teclado, con cada hueco libre y cada bloque de cita como enlace real, en orden cronológico **global** en el HTML — intercalando los carriles por hora, no agrupado primero por carril (resuelta la ambigüedad de la revisión `agenda-timeline-grid`, hallazgo L4) —, con `aria-label` completo que incluya la plaza (PRF-109) y, en Semana, el día de esa columna (hallazgo M2). La rejilla **debe** llevar, además, un enlace «Saltar a las citas» al principio, para no obligar a tabular por cada hueco libre de la vista Día antes de llegar a la lista de tarjetas.
- **PRF-120.** Encima de la rejilla de Día y Semana (no en Mes), un selector `<select>` **debe** ofrecer «Cualquiera» y cada servicio activo con su duración; elegir uno **debe** resaltar en la rejilla, en cada carril libre de esa media hora, dónde cabe completo (PRF-123, PRF-124). El selector se envía solo con JavaScript mínimo al cambiar; un botón «Ver» **debe** seguir funcionando sin JavaScript. *Actualización (2026-10-06):* el selector admite varios servicios a la vez (PRF-132).

### Filtro de servicio en la agenda: base de cálculo (2026-10-05)

- **PRF-121.** Para resaltar dónde cabe un servicio, el panel **debe** decidir si un servicio de duración D cabe empezando a una hora dada con **solo** las reglas 1 y 2 de «Disponibilidad»: la cita completa dentro de un tramo de apertura del día, y sin alcanzar en ningún instante la capacidad efectiva (la capacidad menos las reducciones de los cierres parciales; un cierre total la deja en 0), contando solo las citas confirmadas. **No debe** aplicar la regla 3 (intervalo de la web, antelación mínima ni ventana de reserva), igual que al crear una cita desde el panel. Ese cálculo **no debe** hacer ninguna consulta a la base de datos: usa los tramos, las citas y los cierres que la agenda ya carga para dibujar la rejilla. Con varios servicios elegidos, D es la suma de sus duraciones (PRF-132).
- **PRF-122.** Para cualquier hora futura, el resultado de PRF-121 **debe** coincidir con el de la comprobación de disponibilidad del panel al crear una cita (`isAvailable(..., applyPublicRules: false)`): las dos comparten la misma comprobación de tramo y de capacidad, de modo que la rejilla nunca marque como «cabe» un hueco que el alta rechazaría, ni al revés.

### Filtro de servicio en la agenda: selector, resaltado y persistencia (2026-10-05)

- **PRF-123.** El parámetro `servicio` de la URL **debe** sobrevivir a cualquier navegación dentro de la agenda: las pestañas Día/Semana/Mes, Anterior/Siguiente/Hoy, el formulario «Ir a la fecha», «Nueva cita» (donde además preselecciona el servicio, PRF-124) y cada hueco libre — incluida una visita a Mes, que no tiene selector propio pero no debe perder el filtro al volver a Día o Semana. `servicio` **no debe** fundirse nunca con `volver`: son parámetros independientes, igual que ya lo son `hora` y `volver`. Un valor que no sea un id de un servicio activo **debe** ignorarse en silencio (como «Cualquiera»), nunca un error. Con varios servicios, el parámetro es `servicio[]` (PRF-132).
- **PRF-124.** Con un servicio elegido, cada media hora libre donde cabe completo (PRF-121) **debe** mostrar un borde y un fondo dorado sutil distintos del de un hueco libre normal — nunca solo un cambio de color — y, en vista Día, el texto «Cabe»; en Semana, sin ese texto (no cabe en columnas tan estrechas) pero siempre con un `aria-label` que lo diga. Un hueco libre donde el servicio no cabe **debe** seguir exactamente igual que sin ningún servicio elegido. Al tocar un hueco (quepa o no el servicio), «Nueva cita» **debe** abrir con ese servicio ya preseleccionado (T045). Con varios servicios, «Cabe» y la preselección valen para todos ellos (PRF-132).

### Varios servicios en una cita (2026-10-06)

Decisión del usuario, 2026-10-06: «se tiene que poder reservar para más de un servicio. Por ejemplo, yo que soy hombre cuando pido hora para la peluquería, estoy pidiendo hora para corte de pelo hombre y arreglo de barba, que son 2 servicios». Sustituye al no-objetivo anterior («los combos se dan de alta como servicios propios»). Las migraciones de reservas nunca se han ejecutado en producción, así que la cita nace ya con este modelo, sin migración de conversión.

- **PRF-125.** Una cita **debe** poder incluir de 1 a 5 servicios distintos, en la reserva online y en el panel. Se hacen seguidos, en el orden de los servicios del salón («Orden» y después nombre), no en el orden en que se eligen. La cita dura la suma de sus duraciones y ocupa **una** plaza durante todo ese tiempo: las reglas de «Disponibilidad» se aplican a la duración total. El máximo de 5 es un único valor del sistema, no una cifra repetida.
- **PRF-126.** Cada servicio de una cita **debe** guardar su nombre, su duración y su precio interno tal como eran al reservar. Cambiarlos después en el servicio no cambia la cita (como PRF-013 y PRF-015). El precio guardado sigue siendo interno: **no debe** aparecer en ninguna página pública ni en ningún correo al cliente (PRF-014).
- **PRF-127.** La página de reservas **debe** permitir elegir de 1 a 5 servicios con casillas, mostrando la duración de cada uno y la duración total antes de elegir día. Funciona sin JavaScript: la elección viaja en la dirección de la página (`servicio[]`, un valor por servicio) por el calendario y las horas. Un enlace antiguo con un solo servicio (`?servicio=3`) **debe** seguir funcionando. Una elección con un servicio repetido, más de 5 o ninguno **no debe** aceptarse.
- **PRF-128.** Si alguno de los servicios elegidos no existe, no está activo o no es reservable online, la reserva online **no debe** crearse con los demás: se rechaza entera, como PRF-033. En el panel, todos deben estar activos, salvo los que la cita ya tenía (como en T017).
- **PRF-129.** Una persona del salón **debe** poder crear, editar y mover una cita con de 1 a 5 servicios. Al editarla, los servicios que se mantienen conservan los datos de PRF-126; los que se añaden toman los actuales del servicio. Un servicio que se ha desactivado puede seguir en la cita.
- **PRF-130.** Los correos, la página de la cita, el aviso al salón y la agenda (tarjetas y bloques de la rejilla) **deben** mostrar todos los servicios de la cita con la duración total. En un bloque estrecho de la rejilla basta con el resumen recortado, siempre que el `aria-label` y el `title` lo lleven completo.
- **PRF-131.** Los límites de abuso (PRF-037, el máximo de citas futuras por cliente) y la comprobación de PRF-035 **deben** seguir contando **citas**, no servicios: una cita con 3 servicios cuenta como una.
- **PRF-132.** El selector «Cabe» de la agenda (PRF-120) **debe** admitir de 1 a 5 servicios a la vez (`servicio[]`, el mismo máximo que PRF-125) y marcar «Cabe» donde cabe la **suma** de sus duraciones, con la misma persistencia por toda la navegación que PRF-123 y la preselección de todos ellos en «Nueva cita». Decisión del usuario, 2026-10-06.

## Datos existentes y transición

No hay citas, servicios ni cuentas previos. Al desplegar se crean el horario inicial (PRF-019) y los ajustes iniciales (PRF-020). No se crean servicios: los da de alta el salón. Hasta que exista al menos un servicio reservable, la página de reservas muestra el mensaje de PRF-027. Antes de publicar hay que crear al menos una cuenta del panel, configurar el correo y la dirección del salón (PRF-055) y completar los datos pendientes de la política de privacidad (PRF-059). Las citas que lleguen por teléfono o WhatsApp deben apuntarse en el panel para que ocupen su hueco.

El remitente de correo real (PRF-070, PRF-071) sigue pendiente del buzón y el servidor SMTP del salón (ver «Preguntas abiertas»); mientras tanto, el valor documentado es provisional.

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
- **CA-10 (PRF-070 a PRF-075).** Los correos muestran «Peluquería Jenver» como remitente y como marca (colores, logo o nombre, pie propio), sin ningún rastro de la plantilla por defecto. El despliegue falla si el nombre de la aplicación o el remitente siguen siendo los de la plantilla.
- **CA-11 (PRF-077 a PRF-087).** El salón mueve una cita confirmada futura (día, hora y servicio) y puede editar los datos de la clienta a la vez. Un hueco sin capacidad, fuera de horario o en un cierre avisa con el motivo y exige confirmar antes de guardar. La clienta recibe el aviso con el mismo enlace; un fallo de envío no deshace el cambio ni se reintenta. Si cambia su email, recibe en la dirección nueva un enlace nuevo y el anterior deja de funcionar; si ese correo falla, el panel lo avisa y se reintenta. Una cita cancelada o pasada no se puede mover, un formulario desfasado no se guarda, y no queda ningún rastro del cambio en sus datos.
- **CA-12 (PRF-076, PRF-088).** El botón «Reservar cita →» de la portada y de cada página de servicio, y el «Reservar online» de la sección «Reserva tu cita», llevan a la página de reservas; los botones de llamada y de WhatsApp se mantienen. Las preguntas frecuentes sobre cómo pedir cita mencionan la reserva online.
- **CA-13 (PRF-089 a PRF-098).** En 360-414 px de ancho: los botones y enlaces de acción del panel miden al menos 44×44 px; el panel tiene un botón hamburguesa que despliega un menú vertical desplazable con los módulos y «Cerrar sesión» al final, visible también sin JavaScript; los servicios se ven en tarjetas; la agenda ofrece «Mañana» y un botón flotante para crear una cita, y separa Editar de Cancelar; la tarjeta de una cita abre WhatsApp con el teléfono normalizado; el horario semanal no hace *scroll* horizontal; el calendario y las horas de `/reservas` miden al menos 44 px; el botón de cancelar de `/cita/{token}` ocupa todo el ancho.
- **CA-14 (PRF-099 a PRF-107).** La agenda ofrece un selector Día/Semana/Mes con `vista`/`fecha` en la URL; un valor de `vista` desconocido cae a Día, que no cambia. Semana agrupa las citas de lunes a domingo con una sola consulta (rejilla en escritorio, tira de 7 días más la agenda del día elegido en móvil). Mes muestra el número de citas por día con una consulta agregada, y tocar un día abre su vista Día. Anterior/Siguiente/Hoy y el formulario «Ir a la fecha» se adaptan a la vista activa. Los días cerrados y el día de hoy se distinguen con texto o borde, no solo con color, y las rejillas de Semana (escritorio) y Mes son accesibles por teclado.
- **CA-15 (PRF-108 a PRF-124).** Día y Semana (escritorio) muestran una rejilla horaria de 88 px/hora, desde la apertura más temprana al cierre más tardío de la semana. Cada columna tiene tantos carriles como la capacidad; una cita ocupa un carril, con carriles extra y el texto «Sobre capacidad» si se supera por un «Guardar igualmente». Fuera de horario, Cerrado y Cierre se ven sombreados con texto. Un hueco libre de al menos 30 minutos se puede tocar para crear una cita con la hora ya puesta; un bloque de cita lleva a su tarjeta de detalle. Hoy muestra la línea de «ahora» y la rejilla se desplaza sola al abrirse. Semana en móvil muestra la misma rejilla del día elegido bajo la tira de 7 días. Mes no cambia. Un selector de servicio (no en Mes) resalta, en cada carril libre, dónde cabe completo su duración, sin ninguna consulta adicional, y preselecciona el servicio al crear una cita desde un hueco.
- **CA-16 (PRF-125 a PRF-132).** Una cita puede tener de 1 a 5 servicios, en el orden del salón, que ocupan una plaza durante la suma de sus duraciones. Cada servicio guarda su nombre, duración y precio interno del momento de reservar, y el precio nunca se ve fuera del panel. La web y el panel permiten elegir varios, rechazan una lista no válida entera y muestran la duración total; correos, página de la cita y agenda los listan todos. Los límites cuentan citas. El selector «Cabe» de la agenda admite varios servicios con su suma.

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
| PRF-070 | Se envían los 4 correos → el campo «De» muestra «Peluquería Jenver», no «Laravel». |
| PRF-071 | Comprobación de despliegue con el nombre de la aplicación «Laravel» o el remitente por defecto → falla. |
| PRF-072 | Un correo renderizado muestra los colores de la marca y el logo o el nombre del salón, no el tema por defecto. |
| PRF-073 | El pie del correo muestra los datos del salón, no un aviso de la plantilla. |
| PRF-074 | El HTML del correo no contiene ningún enlace ni logotipo de un tercero. |
| PRF-075 | La imagen del logo del correo es una URL absoluta y lleva texto alternativo. |
| PRF-076 | El botón del inicio de la portada y el de cada página de servicio llevan a la página de reservas; los botones de llamada y WhatsApp siguen en `tel:`/WhatsApp. |
| PRF-077 | Mover una cita confirmada futura a otro día, hora o servicio disponible → se guarda con los nuevos datos. |
| PRF-078 | Mover una cita a un hueco que solo queda libre porque es el suyo propio → se permite (no cuenta contra sí misma). |
| PRF-079 | Elegir un hueco sin capacidad o fuera de horario → aviso explicativo antes de guardar. |
| PRF-080 | Confirmar «Guardar igualmente» tras el aviso → se guarda. Sin confirmar → no se guarda. |
| PRF-081 | Cambiar a la vez la hora y el teléfono de la clienta → ambos se actualizan con las reglas de validación del alta. |
| PRF-082 | Mover una cita con email → la clienta recibe un correo con la nueva fecha/hora y el mismo enlace que ya tenía. |
| PRF-083 | Simular un fallo de envío al mover la cita → el cambio queda guardado y no hay un segundo intento automático. |
| PRF-084 | Intentar editar una cita cancelada, o una ya empezada → el panel no lo permite. |
| PRF-085 | Mover una cita y revisar sus observaciones y demás datos → no aparece ningún texto generado por el cambio. |
| PRF-086 | Dos cambios simultáneos sobre la misma cita (o un cambio y una cancelación a la vez) → el resultado final es el de uno de los dos, nunca una mezcla. Guardar un formulario abierto antes de otro cambio → no se guarda y se avisa. |
| PRF-087 | Cambiar el email de una cita → enlace nuevo; el anterior da «no encontrado»; la dirección nueva recibe un correo con la cita y el enlace nuevo (uno solo si también se mueve). Simular un fallo → el panel avisa y la tarea periódica lo reenvía. |
| PRF-088 | La sección «Reserva tu cita» de la portada lleva a la página de reservas sin perder teléfono ni WhatsApp; las preguntas frecuentes sobre pedir cita mencionan la reserva online. |
| PRF-089 | A 375 px, medir con el inspector «Editar», «Cancelar cita», «Guardar», «Nueva cita», un módulo del menú y «Cerrar sesión» → todos miden al menos 44×44 px. |
| PRF-090 | A 375 px, cualquier pantalla del panel → botón hamburguesa ≥44×44 px en la cabecera; al abrirlo, lista vertical con los módulos (≥44 px cada uno), el activo marcado, y «Cerrar sesión» al final, separado. Simular un panel con 10-12 módulos → el menú se desplaza internamente sin salirse de la pantalla. Desactivar JavaScript → el menú se ve igualmente. Con JavaScript, Escape cierra el menú y devuelve el foco al botón. A 1024 px → el menú superior horizontal de siempre, sin hamburguesa. |
| PRF-091 | A 375 px, abrir Servicios con 3 servicios → se ven como tarjetas, sin `scroll` horizontal ni `<table>`. |
| PRF-092 | En la agenda, pulsar «Mañana» → va al día siguiente al de hoy. |
| PRF-093 | A 375 px, en una agenda con varias citas, hacer `scroll` hasta el final → el botón flotante de nueva cita sigue visible y no tapa la última cita ni la navegación inferior. |
| PRF-094 | En la tarjeta de una cita, «Editar» y «Cancelar cita» → estilos y color distintos, con separación entre ambos. |
| PRF-095 | Cita con teléfono «633 912 050» → el enlace de WhatsApp de su tarjeta es `https://wa.me/34633912050`. Cita con teléfono ya con prefijo (`+34633912050`) → no se duplica el prefijo. |
| PRF-096 | A 360 px, abrir Horario → ninguna fila de tramo produce `scroll` horizontal ni recorta los campos de hora. |
| PRF-097 | A 375 px, medir las celdas del calendario y los botones de hora de `/reservas` → al menos 44 px de alto. |
| PRF-098 | A 375 px, abrir `/cita/{token}` de una cita cancelable → el botón «Cancelar cita» ocupa todo el ancho disponible. |
| PRF-099 | Las pestañas Día/Semana/Mes miden al menos 44×44 px, el enlace de cada una lleva `vista`/`fecha`, y `?vista=invalido` muestra la vista Día. Sin `vista` en la URL → Día por defecto. |
| PRF-100 | A 1024 px, vista Semana → rejilla de 7 columnas lunes-domingo con las citas de cada día. |
| PRF-101 | A 375 px, vista Semana → tira de 7 días y, debajo, la agenda del día elegido con Llamar/WhatsApp/Editar/Cancelar. |
| PRF-102 | Vista Mes con citas en varios días → cada día muestra su número de citas confirmadas. |
| PRF-103 | En vista Mes, tocar un día (con o sin citas) → abre la vista Día de esa fecha. |
| PRF-104 | En Semana, Anterior/Siguiente mueven una semana y «Hoy» vuelve a la semana actual; en Mes, un mes; «Ir a la fecha» mantiene la vista activa. |
| PRF-105 | Un día sin horario semanal, o con un cierre total, se marca «Cerrado» con texto en Semana y Mes; un cierre de capacidad reducida se señala sin marcar el día cerrado. A 360 px, las celdas de día miden al menos 44 px de alto (el ancho de 39-43 px está aceptado). |
| PRF-106 | El día de hoy se distingue con un borde en Semana y Mes, no solo con un color de fondo. |
| PRF-107 | La rejilla de Semana (escritorio) y la de Mes llevan `role="grid"`/`role="columnheader"` y se recorren con el teclado (Tab/Intro sobre los enlaces de cada día). |
| PRF-108 | Con el horario por defecto (martes-sábado 09:00-19:00), la rejilla de Día va de 09:00 a 19:00, con una línea cada 30 minutos. |
| PRF-109 | Con capacidad 2, cada columna tiene 2 carriles aunque solo haya una cita. Con 3 citas simultáneas (tras «Guardar igualmente»), aparece un tercer carril con el texto «Sobre capacidad». |
| PRF-110 | Dos citas que se solapan van a carriles distintos; ninguno menciona una peluquera. Una cita cancelada no ocupa ningún carril. |
| PRF-111 | Un bloque de cita muestra hora, servicio y clienta; con un nombre largo, se recorta con `ellipsis` y el texto completo está en `aria-label`/`title`. |
| PRF-112 | Un día con dos tramos (p. ej. 09:00-13:00 y 15:00-19:00) muestra «Fuera de horario» entre ambos. Un lunes sin tramos muestra «Cerrado» en toda la rejilla. Un cierre total puntual muestra «Cierre». |
| PRF-113 | Un cierre que reduce la capacidad de 2 a 1 sombrea con «Cierre» solo el segundo carril en ese tramo; el primero sigue libre u ocupado según corresponda. |
| PRF-114 | Tocar un hueco libre de 30 minutos o más abre «Nueva cita» con la fecha y la hora de ese hueco. |
| PRF-115 | Tocar un bloque de cita lleva a su tarjeta, con Llamar, WhatsApp, Editar y Cancelar. |
| PRF-116 | En el día de hoy, la rejilla muestra una línea en la hora actual. |
| PRF-117 | Al abrir la agenda en el día de hoy, la rejilla ya está desplazada a la hora actual sin tocar nada. |
| PRF-118 | A 1024 px, Semana muestra 7 columnas de la misma rejilla horaria. A 375 px, Semana muestra la tira de 7 días con la rejilla del día elegido debajo. |
| PRF-119 | Con el teclado (Tab), se puede llegar a cada hueco libre y a cada bloque de cita de la rejilla, en orden cronológico global (intercalando los carriles), empezando por un enlace «Saltar a las citas». |
| PRF-120 | En Día y Semana (no en Mes), el selector ofrece «Cualquiera» y los servicios activos con su duración; cambia la rejilla solo al elegir uno (JS) o al pulsar «Ver» (sin JS). |
| PRF-121 | Una cita que no deja hueco, capacidad 1 y 2, un cierre parcial a mitad del servicio, un cierre total, la pausa de mediodía, una cita que termina justo al empezar, citas canceladas y los días de cambio de hora → solo cabe donde lo permiten las reglas 1 y 2; horas fuera del intervalo o de la antelación también caben; ninguna consulta. |
| PRF-122 | En muchos días aleatorios (con semilla), el resultado coincide con `isAvailable(..., applyPublicRules: false)` para cada hora candidata. |
| PRF-123 | Con un servicio elegido, cambiar de pestaña, de semana/mes, de fecha o pasar por Mes conserva `servicio` en la URL; un valor inválido o de un servicio inactivo se ignora; `servicio` nunca aparece dentro de `volver`. |
| PRF-124 | Con «Balayage» (2 h) elegido, las medias horas de 09:00 a 17:00 muestran «Cabe» (Día) o solo su `aria-label` (Semana) con borde/fondo dorado, en cada carril libre; de 17:30 en adelante (y donde la capacidad no deja hueco) quedan como un hueco libre normal. Tocar cualquier hueco preselecciona el servicio en «Nueva cita». |
| PRF-125 | Reservar «Corte de Pelo Hombre» (15 min) + «Corte/Arreglo barba» (10 min) → una cita de 25 min en una plaza, con los dos servicios en el orden del salón; un sexto servicio → rechazado. |
| PRF-126 | Cambiar después el nombre, la duración o el precio de uno de los servicios → la cita conserva los de la reserva; el precio no aparece en ninguna página pública ni correo. |
| PRF-127 | Elegir dos servicios en `/reservas` sin JavaScript → la dirección lleva `servicio[]` dos veces, el calendario y las horas usan 25 min y se ve el total; `?servicio=3` sigue funcionando; un servicio repetido o 6 servicios → rechazado. |
| PRF-128 | Enviar dos servicios, uno de ellos no reservable online → no se crea ninguna cita. |
| PRF-129 | En el panel, crear una cita con 2 servicios, quitarle uno y añadir otro → el que se mantiene conserva sus datos; un servicio desactivado que ya tenía la cita sigue en ella. |
| PRF-130 | Una cita con 2 servicios → los correos, `/cita/{token}`, el aviso al salón, la tarjeta y el bloque de la rejilla los muestran con la duración total. |
| PRF-131 | Una clienta con una cita de 3 servicios puede reservar una segunda cita (límite de 2 citas, no de servicios). |
| PRF-132 | En la agenda, elegir 2 servicios en «Cabe» → solo se marcan las medias horas donde cabe la suma; `servicio[]` se conserva al navegar y «Nueva cita» preselecciona los dos. |

## Riesgos y marcha atrás

- **Citas por teléfono o WhatsApp sin apuntar.** La web ofrecería horas que en realidad están ocupadas. Se mitiga con el alta rápida en la agenda (PRF-046) y explicándoselo al salón.
- **Correo sin configurar.** Los clientes no recibirían su enlace. Lo impide PRF-055, y PRF-053 recupera los fallos puntuales.
- **Caché de páginas.** Una página de reservas cacheada mostraría horas ocupadas. Lo impide PRF-038. Hay que confirmar que el servidor web no añade su propia caché.
- **Datos legales pendientes.** No se puede publicar hasta completar PRF-059.
- **Marcha atrás.** Los cambios en la base de datos solo añaden tablas. Para retirar la funcionalidad basta con revertir los PRs: el botón vuelve a abrir una llamada y las tablas nuevas se quedan sin uso, sin afectar al resto de la web.
- **Tema de correo nuevo.** Un tema Markdown mal maquetado puede verse roto en Outlook de escritorio (motor Word, sin CSS moderno). Se mitiga manteniendo la estructura de tablas del tema por defecto de Laravel y revisando el HTML renderizado en Gmail y Outlook antes de darlo por bueno.
- **Guardar un hueco sin capacidad u horario (PRF-080).** Permite que el panel supere la capacidad configurada a propósito. La página pública de reservas sigue sin ofrecer ese hueco (no cambia su cálculo de disponibilidad), así que el exceso solo lo ve y lo decide el salón.

## Preguntas abiertas

| Pregunta | Responsable | Valor por defecto | Consecuencia |
| --- | --- | --- | --- |
| Titular, NIF y email de contacto para la política de privacidad | Cliente | Marcado como pendiente | Bloquea la publicación |
| Plazo de conservación de las citas | Cliente | Marcado como pendiente | Bloquea la publicación. La purga automática queda para el PR 5 |
| Buzón y servidor de correo de envío y dirección del salón real | Usuario y cliente | `reservas@peluqueriajenver.com` (provisional, 2026-10-05) | El despliegue falla hasta configurar el SMTP real (PRF-055, PRF-071) |
| Lista real de servicios y duraciones | Cliente | Ninguno sembrado | La página de reservas muestra el mensaje sin servicios |

## Tareas

[Índice de tareas](../tasks/reservas/index.md)
