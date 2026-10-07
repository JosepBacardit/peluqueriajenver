# T065 — Esperas: no revelación pública y cierre

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-158
- **Depende de:** T062–T064
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** test de regresión y cierre de la documentación; sin lógica nueva.
- **Estado:** done
- **PR / rama:** `feature/service-wait-times`.

## Resolución

- `tests/Feature/Booking/HidePublicServiceDurationTest.php`:
  - nueva función auxiliar `expectNoWaitRevealed()`, con un servicio «Coloración» de 120 min que espera desde el minuto 30 durante 45 (10:30–11:15 en una cita de las 10:00);
  - comprueba que el HTML contiene el servicio y la hora de inicio, pero no la palabra «espera» (como palabra entera), ni «10:30», «11:15» o «12:00», ni «45 min», «2 h», `data-wait-minutes` o el JSON (`"minutes"`).
- Se aplica a `/cita/{token}` y a los 3 correos a la clienta (confirmación, cancelación y cambio de hora). Un test aparte prueba que la búsqueda por palabra entera no confunde «Te esperamos», presente en todos los correos, y sí detecta «Espera: 10:30–11:15» e «incl. 45 min de espera».
- `/reservas` ya estaba cubierta en T063 (`ServiceWaitsFormTest`), y los correos al salón con la espera, en T064 (`WaitTimesAgendaTest`).
- `ServiceCatalogSeeder` sin cambios, por decisión del usuario: la peluquera configura las esperas desde el panel.
- Los tests nuevos pasan sin tocar código de aplicación: son de regresión, porque la no revelación ya se cumplía desde T062–T064.

Suite completa en verde; Pint pasado sobre el test.

## Plan original

1. Ampliar `HidePublicServiceDurationTest`: con un servicio con esperas, ni `/reservas`, ni `/cita/{token}`, ni los 3 correos a la clienta contienen la espera (texto, minutos ni `data-*`).
2. Si la peluquera da los números, añadir `waits` a `ServiceCatalogSeeder::CATALOG`. Solo afecta a instalaciones nuevas: en producción las esperas se configuran desde el panel.
3. Matriz de cobertura y estado general al día.
