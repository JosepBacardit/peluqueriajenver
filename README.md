# Peluquería Jenver

Aplicación web moderna para la gestión y administración de la peluquería Jenver, desarrollada con Laravel.

## Acerca del Proyecto

Peluquería Jenver es una plataforma integral diseñada para optimizar la experiencia tanto de clientes como del personal administrativo del salón de belleza. La aplicación facilita la gestión de citas, clientes, servicios y más.

## Requisitos del Sistema

- **PHP**: 8.3 o superior
- **Composer**: Última versión
- **Node.js**: 18+ (para compilar assets)
- **npm**: 9+ (incluido con Node.js)
- **Base de datos**: SQLite, MySQL o PostgreSQL

## Características Principales

- 📅 Gestión de citas y reservas
- 👥 Administración de clientes
- 💇 Catálogo de servicios y precios
- 👨‍💼 Gestión de empleados y especialistas
- 💰 Control de ingresos y reportes
- 🎯 Sistema de notificaciones
- 📱 Interfaz responsive para dispositivos móviles

## Instalación

### 1. Clonar o descargar el proyecto

```bash
cd peluqueriajenver
```

### 2. Ejecutar el setup automático

```bash
composer setup
```

Este comando ejecutará automáticamente:
- Instalación de dependencias PHP
- Creación del archivo `.env`
- Generación de la clave de aplicación
- Migración de la base de datos
- Instalación de dependencias npm
- Compilación de assets

### 3. Instalación Manual (si prefieres)

Si no deseas usar el comando `setup`, sigue estos pasos:

```bash
# Instalar dependencias PHP
composer install

# Copiar archivo de configuración
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate

# Ejecutar migraciones
php artisan migrate

# Instalar dependencias JavaScript
npm install

# Compilar assets
npm run build
```

## Desarrollo

Para ejecutar el servidor de desarrollo con todos los servicios necesarios:

```bash
composer run dev
```

Este comando inicia simultáneamente:
- 🖥️ Servidor Laravel (http://localhost:8000)
- 📦 Worker de colas
- ⚡ Vite (compilación de assets)

## Estructura del Proyecto

```
├── app/                 # Código de la aplicación (Controllers, Models, etc.)
├── database/           # Migraciones y seeders
├── resources/          # Vistas Blade, CSS y JavaScript
├── routes/             # Definición de rutas
├── public/             # Archivos públicos (CSS, JS compilados)
├── storage/            # Almacenamiento de archivos (logs, uploads)
├── tests/              # Pruebas unitarias e integración
└── config/             # Archivos de configuración
```

## Testing

Para ejecutar las pruebas automatizadas:

```bash
composer test
```

## Variables de Entorno

Configura el archivo `.env` con los siguientes datos importantes:

```env
APP_NAME=PeluqueriaJenver
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# O usar MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=peluqueria_jenver
# DB_USERNAME=root
# DB_PASSWORD=

MAIL_MAILER=log
# Configura tu proveedor de email si necesitas notificaciones
```

## Solución de Problemas

### Error de permisos en `storage/`

```bash
chmod -R 775 storage bootstrap/cache
```

### La base de datos SQLite no se crea

```bash
php artisan migrate --force
```

### Assets no se compilan

```bash
npm run build
```

## Contribución

Si deseas contribuir al desarrollo de Peluquería Jenver, por favor:

1. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
2. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
3. Push a la rama (`git push origin feature/AmazingFeature`)
4. Abre un Pull Request

## Licencia

Este proyecto está bajo la licencia MIT. Ver el archivo `LICENSE` para más detalles.

## Contacto

**Peluquería Jenver**
- 📧 Email: [tu-email@peluqueriajenver.com]
- 📱 Teléfono: [tu-teléfono]
- 🌐 Sitio Web: [tu-sitio-web]

---

Desarrollado con ❤️ usando [Laravel](https://laravel.com) | Última actualización: 2026
