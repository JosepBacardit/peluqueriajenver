# T065 — Esperas: no revelación pública y cierre

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-158
- **Depende de:** T062–T064
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** test de regresión y cierre de la documentación; sin lógica nueva.
- **Estado:** pending
- **PR / rama:** `feature/service-wait-times`.

## Plan

1. Ampliar `HidePublicServiceDurationTest`: con un servicio con esperas, ni `/reservas`, ni `/cita/{token}`, ni los 3 correos a la clienta contienen la espera (texto, minutos ni `data-*`).
2. Si la peluquera da los números, añadir `waits` a `ServiceCatalogSeeder::CATALOG`. Solo afecta a instalaciones nuevas: en producción las esperas se configuran desde el panel.
3. Matriz de cobertura y estado general al día.
