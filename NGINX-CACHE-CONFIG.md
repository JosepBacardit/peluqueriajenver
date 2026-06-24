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

    # Cache para HTML (1 día para homepage, 7 días para otras páginas)
    location ~* \.html?$ {
        expires 1d;
        add_header Cache-Control "public, max-age=86400, must-revalidate";
    }

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

## Alternativa: Usando location blocks en Laravel

Si no tienes acceso directo a la configuración de nginx, puedes crear un archivo `.env` que configure los headers mediante Laravel, aunque es menos eficiente.

Contacta a tu proveedor de hosting para que agregue esta configuración.
