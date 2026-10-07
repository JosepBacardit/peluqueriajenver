# Configuración de Headers de Caché para Nginx

El servidor de producción usa **nginx**, no Apache. Los headers de caché deben configurarse en nginx, no en .htaccess.

## Pasos para configurar en el servidor nginx de producción:

### 1. Ubicar el archivo de configuración de nginx

Generalmente está en:
- `/etc/nginx/sites-available/peluqueriajenver.com` o similar
- `/etc/nginx/conf.d/peluqueriajenver.com.conf`

### 2. Agregar esta configuración al bloque `server`

```nginx
server {
    # ... otras configuraciones ...

    # Agregar después del root y antes de los location blocks:

    # ============================================
    # CACHE HEADERS PARA ASSETS ESTÁTICOS
    # ============================================
    
    # Cache para imágenes (1 año)
    location ~* \.(png|jpg|jpeg|gif|ico|webp|svg)$ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        add_header Pragma "public";
    }

    # Cache para CSS y JavaScript (1 año)
    location ~* \.(css|js)$ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }

    # Cache para fuentes (1 año)
    location ~* \.(woff|woff2|ttf|otf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }

    # ============================================
    # HTML: SIN CACHÉ PROPIA DE NGINX (decisión del usuario, 2026-10-06)
    # ============================================
    #
    # El HTML de esta aplicación no son archivos .html en disco: cada
    # página la genera PHP-FPM en cada petición, así que el bloque
    # `location ~* \.html?$` que había aquí antes nunca llegaba a
    # aplicarse a ellas (solo a un .html suelto que no existe en este
    # proyecto) y, aun así, su `max-age=86400` llevaba a confusión.
    #
    # La caché de las páginas públicas ahora la decide la propia
    # aplicación en App\Http\Middleware\CacheHeaders: ya no un `max-age`
    # largo, sino `Cache-Control: no-cache` más un ETag calculado del
    # contenido, para que un cambio de horario o del interruptor de
    # reserva online se vea sin esperar a que caduque una copia. nginx no
    # debe añadir, quitar ni sobrescribir esos headers para las peticiones
    # que llegan a `index.php` (el bloque `location ~ \.php$` habitual de
    # Laravel) — ningún `add_header Cache-Control` ni `expires` en ese
    # bloque ni en el `server` general, para que el header que puso
    # PHP-FPM llegue intacto al navegador. `/admin`, `/cita` y `/reservas`
    # siguen con su propio `no-store`, puesto por la misma aplicación.

    # ============================================
    # WEBP AUTOMÁTICO
    # ============================================
    
    # Servir .webp si el navegador lo soporta
    location ~* \.(png|jpg|jpeg)$ {
        if ($http_accept ~* "webp") {
            rewrite ^(.*)$ $1.webp break;
        }
    }

    # ============================================
    # SECURITY HEADERS
    # ============================================
    
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Vary "Accept-Encoding, Accept" always;

    # ... resto de la configuración ...
}
```

### 3. Recargar nginx

```bash
sudo nginx -s reload
```

## Verificación

Después de aplicar la configuración, verifica que los headers se están enviando:

```bash
curl -I https://www.peluqueriajenver.com/images/tu-pelo-en-buenas-manos.png
```

Deberías ver headers como:
```
Cache-Control: public, max-age=31536000, immutable
Pragma: public
Expires: Wed, 24 Jun 2027 ...
```

Para una página HTML pública, en cambio, comprueba que nginx deja pasar el `Cache-Control` y el `ETag` de la aplicación sin añadir un `max-age` propio:

```bash
curl -I https://www.peluqueriajenver.com/
```

```
Cache-Control: no-cache, private
ETag: "..."
```

Y que repetir la petición con ese `ETag` responde 304 sin cuerpo:

```bash
curl -I -H 'If-None-Match: "<el-etag-de-arriba>"' https://www.peluqueriajenver.com/
```

## Alternativa: Usando location blocks en Laravel

Si no tienes acceso directo a la configuración de nginx, puedes crear un archivo `.env` que configure los headers mediante Laravel, aunque es menos eficiente.

Contacta a tu proveedor de hosting para que agregue esta configuración.
