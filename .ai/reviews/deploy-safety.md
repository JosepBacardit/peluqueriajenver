# Revisión independiente: deploy-safety

**Revisor:** Claude Opus 5.5, en un contexto limpio y sin relación con la implementación.
**Código revisado:** `main..chore/deploy-safety`, commits `e11608b` (`deploy.sh`, `deploy:check`, `config/logging.php`, `AGENTS.md`) y `1350105` (corrección de texto en `AGENTS.md`).
**Referencia:** `cobaprojects/deploy.sh` (punta actual, con el *preflight* de `02ed875`) y `cobaprojects/.ai/reviews/deploy-safety.md` (10 hallazgos resueltos). Conocimiento aplicado: `developer-brain/knowledge/vps-ovh.md`.

**Evidencia reproducida:**

- `docker compose exec -T app php artisan test`: 17 tests en verde y 1 omitido (80 aserciones). `DeployCheckCommandTest`: 5 en verde y 1 omitido porque el contenedor corre como root.
- El test omitido, ejecutado como `www-data` (`docker compose exec -T -u www-data app php vendor/bin/pest --filter="not writable"`), pasa: 1 test y 2 aserciones. Después de las dos ejecuciones, `git status --porcelain` sigue vacío.
- `bash -n deploy.sh`: correcto.
- `shellcheck` (imagen `koalaman/shellcheck:stable`, montada en solo lectura) sin avisos con las reglas por defecto. Con `-o all` solo salen avisos opcionales de estilo: `SC2250` (21) y `SC2312` (3).
- `git ls-files -s deploy.sh`: `100755`. `git ls-files --eol deploy.sh`: `lf`.
- Producción, consultada con `curl -sS --max-time 20 -o /dev/null -w '%{http_code} %{redirect_url}'`: `/`, `/contacto`, `/avisos-legales` y `/sitemap.xml` dan **200 sin redirección**. `/sitemap.xml` sirve `application/xml` y empieza por `<?xml version="1.0" encoding="UTF-8"?>`. En cambio, **`/up` da 500 con la traza completa** (ver hallazgo 1).
- Búsqueda de `Mail::`, `Notification::`, `->notify(` en `app/`, `resources/` y `routes/`: ningún resultado. No existen `app/Mail` ni `app/Notifications`. `routes/web.php` solo tiene `GET` que devuelven vistas. Se confirma que **el proyecto no envía correos** y que no hace falta `notify-pending` ni un cron.

No se ha modificado código de aplicación, tests, configuración, scripts ni migraciones.

---

## Hallazgos

### 1. (Crítica, fuera del diff, en producción ahora) `www.peluqueriajenver.com` tiene `APP_DEBUG=true` y `www-data` no puede escribir en `storage/framework/views`: `/up` muestra la traza en público

**Evidencia:** `curl https://www.peluqueriajenver.com/up` devuelve 500 y la página de errores de Laravel en modo *debug* (unos 160 KB). El título es `tempnam(): file created in the system's temporary directory (500 Internal Server Error)` y la traza muestra rutas como `/var/www/peluqueriajenver.com/vendor/laravel/framework/src/Illuminate/Filesystem/Filesystem.php#L222`. Es el mismo incidente que tuvo cobaprojects el 2026-10-02. `knowledge/vps-ovh.md` dice que ese día «el usuario dejó `APP_DEBUG=false` en todos los proyectos del VPS», pero aquí no es así, o se ha vuelto a cachear una configuración antigua.

Las demás páginas dan 200 solo porque sus vistas Blade ya estaban compiladas. `/up` usa una vista del *framework* que todavía no estaba compilada, y en cuanto haya que compilar cualquier otra (al vaciar la caché de vistas o con una vista nueva), esa página también dará 500.

**Impacto:** ahora mismo cualquiera puede ver la traza, la ruta del servidor y los datos de la petición. La web puede caer entera en cuanto se vacíe la caché de vistas.

**Recomendación (sin esperar a este PR), en el VPS y como `deploy`:**

1. Poner `APP_DEBUG=false` en el `.env`.
2. Arreglar los permisos con `sudo chown -R deploy:www-data storage bootstrap/cache` y `sudo chmod -R ug+rwX storage bootstrap/cache`.
3. Ejecutar `sudo -u deploy php artisan optimize`.
4. Comprobar que `/up` da 200.

Anotar en `knowledge/vps-ovh.md` que la regla no estaba aplicada en peluqueriajenver. Dato que sale de la propia traza: la ruta en el VPS es, casi seguro, **`/var/www/peluqueriajenver.com`**. Hay que confirmarla con `pwd` y anotarla.

---

### 2. (Media) La comprobación previa no garantiza lo que promete: `git pull --ff-only` vuelve a ir a la red dentro del mantenimiento, y `git fetch origin` puede no ser el remoto del *upstream*

**Evidencia:**

- `deploy.sh:107` ejecuta `git fetch origin` y `deploy.sh:114` comprueba `HEAD` contra `@{u}`.
- Más tarde, ya con la web en `down` (`deploy.sh:138-139`), `deploy.sh:142` ejecuta `git pull --ff-only`, que hace **un segundo fetch** desde el remoto configurado en `branch.<rama>.remote`.
- Para evitar esto, cobaprojects ya usa `git fetch` (sin remoto) y `git merge --ff-only "@{u}"` (commit `02ed875`, «reuses this exact fetch rather than risking a second one racing ahead of the ancestry check»). Esta adaptación vuelve a `pull`.

**Impacto:**

- **Segundo fetch dentro del mantenimiento:** un corte transitorio de SSH o de GitHub entre la comprobación y el `pull` (después hay un `sudo -v` interactivo, así que pueden pasar minutos) deja la web en `down`. Es justo el incidente que la comprobación quiere evitar.
- **Carrera:** si alguien hace *push* (o *force-push*) en ese intervalo, se despliega un commit que no se ha comprobado. Con un *force-push*, el `pull --ff-only` falla con la web en mantenimiento.
- **Remoto distinto de `origin`:** si el *upstream* de la rama está en otro remoto, `git fetch origin` no actualiza `@{u}`. La comprobación de ascendencia se hace contra una referencia antigua, no prueba el acceso al remoto real, y el `pull` es el primero que llega a él, ya en mantenimiento. Hoy no pasa (solo hay `origin`), pero el script no lo garantiza.
- **Sin novedades:** si `HEAD` ya es `@{u}`, la comprobación pasa y el despliegue sigue completo. Es correcto, porque se necesita para la instrucción «re-run ./deploy.sh» después de un fallo en mantenimiento. Con `merge --ff-only @{u}` también funciona («Already up to date»).

**Recomendación:** hacer lo mismo que cobaprojects: `git fetch` sin argumento (usa el remoto del *upstream*), o sacar el remoto con `git config "branch.$(git symbolic-ref --short HEAD).remote"`, y en `deploy.sh:142` cambiar `git pull --ff-only` por `git merge --ff-only '@{u}'`. Así no hay red dentro del mantenimiento y se despliega exactamente lo que se ha comprobado. Actualizar también el comentario de `deploy.sh:100-101` y el texto de `AGENTS.md:155-157`.

---

### 3. (Media) Con el `.env` actual, el primer `./deploy.sh` dejará la web en mantenimiento a propósito, y «Before the first real deploy» no lo avisa

**Evidencia:** `deploy.sh:155` ejecuta `deploy:check`, que falla si `APP_DEBUG` no es `false` (`DeployCheckCommand.php:57-62`). La web de producción tiene ahora `APP_DEBUG=true` (hallazgo 1). `AGENTS.md:216-240` («Before the first real deploy») enumera la ruta, la clave SSH y los permisos de `storage`, pero no las variables del `.env`.

**Impacto:** si el hallazgo 1 no se corrige antes, el primer despliegue llega hasta `deploy:check`, falla y deja la web en `down` hasta que alguien edite el `.env`, vuelva a cachear y ejecute `up`. El comportamiento es el previsto, pero hay una caída que se puede evitar.

**Recomendación:** añadir a «Before the first real deploy» comprobar en el `.env` del VPS `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL=https://www.peluqueriajenver.com`. Opcional: que `deploy.sh` ejecute también `php artisan deploy:check` como `deploy`, antes de `down`, solo con las comprobaciones de configuración, para fallar con la web abierta. Esto último es una mejora, no un requisito.

---

### 4. (Baja) Una rama sin *upstream*, un `HEAD` desacoplado o commits locales sin subir se presentan como «diverged»

**Evidencia:** `deploy.sh:114-118`. Si no hay *upstream*, `git merge-base --is-ancestor HEAD '@{u}'` sale con 128 («fatal: no upstream configured for branch …»). Con `HEAD` desacoplado o un `HEAD` por delante de `@{u}`, sale con un código distinto de 0. En todos los casos el script dice «the histories themselves disagree», «this is not an access problem».

**Impacto:** bloquear es correcto en los tres casos (el comportamiento es seguro y la web no se toca), pero el mensaje lleva a buscar una divergencia que no existe, por ejemplo en un *checkout* nuevo del VPS clonado sin `--track`.

**Recomendación:** comprobar antes `git rev-parse --abbrev-ref --symbolic-full-name '@{u}' >/dev/null 2>&1`, y si falla, mostrar «This branch has no upstream; run `git branch -u origin/<rama>`». Opcional: distinguir «por delante» (`git merge-base --is-ancestor '@{u}' HEAD`) para decir que hay commits locales sin subir.

---

### 5. (Baja) `AGENTS.md` atribuye a este proyecto el incidente de SSH de cobaprojects y presenta la comprobación como nueva

**Evidencia:** `AGENTS.md:158-163`: «This is new compared to cobaprojects: on peluqueriajenver's first real deploy, `deploy.sh` put the site in maintenance mode and then `git pull` failed on an SSH problem». Sin embargo:

- `deploy.sh:104` lo atribuye a «the 2026-10-02 cobaprojects incident»;
- `cobaprojects/AGENTS.md:134-135` y su commit `02ed875` lo documentan allí;
- `AGENTS.md:216-218` dice que el próximo despliegue será el primero de este proyecto con `deploy.sh`.

**Impacto:** la historia del proyecto queda mal documentada y contradice el propio script y la sección siguiente.

**Recomendación:** decir que la comprobación viene de cobaprojects (incidente del 2026-10-02 en su primer despliegue real) y que aquí se hace antes de `sudo -v`, que es la diferencia real.

---

### 6. (Baja) `AGENTS.md` remite a «Known traps» para la rotura del sitemap, pero esa sección no la menciona

**Evidencia:** `AGENTS.md:183-184`: «`/sitemap.xml` is included because it already broke once in this project, see "Known traps"». `AGENTS.md:256-266` («Known traps») solo habla de los textos comerciales y de `pdo_sqlite`. La rotura es la de `short_open_tag` (commit `d65c8d8`).

**Impacto:** quien siga la referencia no encuentra nada.

**Recomendación:** añadir a «Known traps» una línea sobre `short_open_tag=On` y la declaración XML del sitemap (`d65c8d8`, `SitemapTest`), o cambiar la referencia por el commit.

---

## Comprobado sin hallazgos

- **Los diez hallazgos de cobaprojects no se han reintroducido:**
  1. `down` va antes de `pull`, `composer` y `npm` (`deploy.sh:136-149`).
  2. Hay un `sudo -v` justo antes de `down` y `sudo -n` después (`deploy.sh:137`, `fix_permissions -n`, `sudo -n -u`).
  3. N/A: no hay `notify-pending`.
  4. `deploy.sh` está en Git como `100755`.
  5. `fix_permissions` se ejecuta al principio y otra vez después de `optimize`.
  6. Se llama a `on_error` antes de cada `exit 1` de la comprobación final, y `curl -sS --max-time 20`.
  7. Se usan `storage_path()` y `bootstrapPath()`, `useStoragePath()` en los tests, y hay un test con `0555` que se salta si se ejecuta como root (y pasa como `www-data`).
  8. `'permission' => 0664` en los canales `single` y `daily`. `.env.example` usa `LOG_STACK=single`, y Monolog aplica `chmod` explícito, que no depende del `umask`.
  9. Las referencias apuntan a `"Production deploys"`, que existe.
  10. N/A: no hay modelo.
- **Orden de la comprobación previa:** va después de la comprobación de root y del `.env`, y antes de `sudo -v`, del árbol limpio y de `fix_permissions`. Un fallo de red no pide contraseña ni toca la web. `if ! git fetch` no dispara la trampa `ERR`, y el `on_error` explícito imprime «production is untouched», que es correcto (`deploy_stage=not_started`).
- **Rutas de la comprobación final:** las cuatro existen en `routes/web.php` y dan 200 sin redirección en `https://www.peluqueriajenver.com` (que es el `APP_URL`, según los `<loc>` del sitemap). Sugerencia opcional: añadir `/up`. Después de `optimize`, es la única ruta que compila una vista en tiempo de petición (la del *framework*), así que es un buen indicador de que `storage/framework/views` se puede escribir. Ahora mismo detecta el hallazgo 1.
- **`deploy:check` como `www-data`:** se ejecuta después de `optimize`, así que lee la configuración cacheada, y `sudo` limpia el entorno. `DeployCheckCommand` es igual que el de cobaprojects, salvo las variables de contacto, que aquí no existen.
- **Sin correo ni cron:** confirmado más arriba. El comentario de `deploy.sh:26-28` y `AGENTS.md:204-214` son correctos.

## Estado

| ID | Severidad | Estado |
| --- | --- | --- |
| 1 | Crítica (producción, fuera del diff) | open — requiere acceso al VPS, fuera de alcance de este cambio |
| 2, 3 | Media | resolved |
| 4, 5, 6 | Baja | resolved |

## Resolución (2026-10-02, agente programador)

Commit `dc90209` (`deploy.sh`, `AGENTS.md`) resuelve los hallazgos 2 a 6. Su mensaje de commit solo cita explícitamente los hallazgos 2, 4 y la sugerencia de `/up` (etiquetada ahí, por error, como «Finding 6»); esta sección es la referencia correcta y completa de qué resuelve cada hallazgo.

- **Finding 2** (commit `dc90209`): `deploy.sh` ya no hace un segundo `fetch` dentro de mantenimiento. El preflight ahora es `git fetch` sin remoto (usa el *upstream* configurado de la rama, igual que la versión final de `cobaprojects/deploy.sh`, commits `02ed875`/`fb327f9`/`b4ed698`), y el paso «Pulling the latest code» pasó de `git pull --ff-only` a `git merge --ff-only "@{u}"`, que reutiliza exactamente el commit ya comprobado como ancestro, sin volver a la red. También se movió el preflight de antes del primer `sudo -v` a entre `fix_permissions` y el segundo `sudo -v`/`down`, para que la estructura del script sea igual a la de cobaprojects, no solo equivalente en intención.
- **Finding 3** (commit `dc90209`): «Before the first real deploy» en `AGENTS.md` ahora incluye comprobar en el `.env` del VPS `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL=https://www.peluqueriajenver.com` antes del primer `./deploy.sh`, y recuerda que tras editar el `.env` a mano hay que ejecutar `php artisan optimize` como `deploy` porque la configuración queda cacheada. También se cambió `ls -ld storage bootstrap/cache` (solo primer nivel) por `find storage bootstrap/cache \( ! -user deploy -o ! -group www-data \) -print`, que recorre todas las subcarpetas.
- **Finding 4** (commit `dc90209`): se añadió, antes de la comprobación de ascendencia, `git rev-parse --abbrev-ref --symbolic-full-name "@{u}"` (igual que `cobaprojects/deploy.sh` tras su propio hallazgo equivalente, commit `fb327f9`), que distingue «sin *upstream* configurado» de «divergida» con un mensaje propio para cada caso. Verificado en un clon de prueba (`C:\Users\pepeb\AppData\Local\Temp\deploy-preflight-test`, borrado después):
  - `HEAD` desacoplado → `git rev-parse --abbrev-ref --symbolic-full-name "@{u}"` falla con `fatal: HEAD does not point to a branch` (código 128), capturado por el `if` correspondiente; el script sale antes de `down` con el mensaje de «sin *upstream*».
  - Un commit local sin subir (`HEAD` por delante de `@{u}`, con *upstream* configurado) → `git merge-base --is-ancestor HEAD "@{u}"` falla (código 1), capturado por el `if` de divergencia; también sale antes de `down`. Confirmado además que `git merge --ff-only "@{u}"` en ese mismo estado solo haría «Already up to date.» (es decir, bloquear aquí es más estricto de lo necesario para ese caso concreto, pero es el mismo comportamiento que ya acepta la versión de referencia de cobaprojects, que tampoco distingue «por delante» de «divergida»; no se ha ido más allá de igualar esa referencia).
- **Finding 5** (commit `dc90209`): `AGENTS.md` ya no dice que el incidente de SSH ocurrió «on peluqueriajenver's first real deploy». La sección «Production deploys» ahora dice explícitamente que el incidente fue el primer despliegue real de **cobaprojects**, que este proyecto no ha tenido su primer despliegue todavía, y que el preflight se adopta desde el principio para no repetir ese problema.
- **Finding 6** (commit `dc90209`): se añadió a «Known traps» la entrada que faltaba sobre `short_open_tag=On` y la declaración XML del sitemap (commit `d65c8d8`, `tests/Feature/SitemapTest.php`), así que la referencia desde «Production deploys» («see "Known traps"») ya apunta a algo que existe.
- **Sugerencia** (sección «Comprobado sin hallazgos», commit `dc90209`): se añadió `/up` a la comprobación final de `curl`, con la misma justificación que `cobaprojects/deploy.sh` (commit `b4ed698`): es la única de las cinco rutas que compila una vista Blade en cada petición en vez de servir una ya compilada, así que es la que de verdad comprueba que `storage/framework/views` es escribible — justo lo que hoy falla en producción (hallazgo 1).
- **Arreglo adicional, pedido junto con lo anterior, sin número de hallazgo** (commit `dc90209`): el mensaje de `on_error` para el caso `live` sugería `git checkout <previous-commit> && ./deploy.sh`, que no funciona porque el preflight de los hallazgos 2/4 solo permite avanzar (*fast-forward*); ahora indica `git revert <commit>`, subirlo, y volver a ejecutar `./deploy.sh`.
- **Finding 1** (crítico, abierto): **no resuelto en este cambio.** Requiere actuar directamente sobre el VPS (editar el `.env` de producción, arreglar la propiedad de `storage`/`bootstrap/cache` con `sudo chown`/`chmod`, y ejecutar `sudo -u deploy php artisan optimize`), y la tarea en curso tiene instrucción explícita de no tocar el VPS. Queda documentado aquí para que se resuelva directamente en el servidor, fuera de este *pull request*; no se maquilla como resuelto.

**Verificación:** `bash -n deploy.sh` → correcto; `shellcheck` (imagen `koalaman/shellcheck:stable`, reglas por defecto) → sin avisos; `git ls-files -s deploy.sh` → `100755`; `docker compose exec -T app php artisan test` → 17 *passed*, 1 *skipped* (80 aserciones), sin cambios respecto a antes de esta resolución porque ningún archivo PHP se ha tocado; `docker compose exec -T app vendor/bin/pint --test --format agent` → limpio en los archivos de esta tarea (la deuda de estilo preexistente en `CacheHeaders.php`, `bootstrap/app.php` y `routes/web.php` es ajena y no se ha tocado).
