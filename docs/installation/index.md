# 🚀 Guía de Instalación

Esta guía te llevará a través del proceso de instalación de Crater paso a paso.

## 📋 Métodos de Instalación

Elige el método que mejor se adapte a tu entorno:

=== "🐳 Docker (Recomendado)"
    La forma más rápida y sencilla de ejecutar Crater.

=== "⚡ Instalación Manual"
    Para mayor control y personalización.

=== "☁️ Servicios Cloud"
    Despliegue en plataformas como DigitalOcean, AWS, etc.

## 🐳 Instalación con Docker

!!! tip "Recomendado para Principiantes"
    Docker es la forma más sencilla de ejecutar Crater sin configuraciones complejas.

### Requisitos Previos

- Docker Desktop o Docker Engine
- Docker Compose v2+
- 2GB de RAM libre
- 5GB de espacio en disco

### Pasos de Instalación

1. **Clona el repositorio**
   ```bash
   git clone https://github.com/crater-invoice/crater.git crater
   cd crater
   ```

2. **Configura las variables de entorno**
   ```bash
   cp .env.example .env
   nano .env
   ```

3. **Inicia los servicios**
   ```bash
   docker-compose up -d
   ```

4. **Accede a la aplicación**
   - URL: `http://localhost:8080`
   - Sigue el asistente de instalación

## ⚡ Instalación Manual

### 1. Requisitos del Sistema

!!! warning "Verificar Requisitos"
    Asegúrate de cumplir con todos los requisitos antes de proceder.

| Componente | Versión Mínima | Comando de Verificación |
|------------|----------------|-------------------------|
| PHP        | 8.2           | `php -v`              |
| Composer   | 2.0           | `composer --version`   |
| Node.js    | 18.x          | `node -v`              |
| NPM        | 8.x           | `npm -v`               |

**Extensiones PHP Requeridas:**
```bash
# Verifica las extensiones instaladas
php -m | grep -E "(pdo|mysql|gd|zip|xml|curl|json|mbstring|tokenizer|openssl)"
```

### 2. Descarga del Código

```bash
# Opción 1: Descarga desde GitHub
git clone https://github.com/crater-invoice/crater.git
cd crater

# Opción 2: Descarga del release
wget https://github.com/crater-invoice/crater/releases/latest/download/crater.tar.gz
tar -xzf crater.tar.gz
cd crater
```

### 3. Configuración del Backend

```bash
# Instala dependencias PHP
composer install --no-dev --optimize-autoloader

# Copia y configura el archivo de entorno
cp .env.example .env

# Genera la clave de aplicación
php artisan key:generate
```

### 4. Configuración de la Base de Datos

Edita el archivo `.env` con tu configuración de base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crater
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

```bash
# Ejecuta las migraciones
php artisan migrate --force

# Genera datos iniciales (opcional)
php artisan db:seed
```

### 5. Configuración del Frontend

```bash
# Instala dependencias Node.js
npm install

# Compila los assets para producción
npm run build
```

### 6. Configuración del Servidor Web

=== "Apache"
    ```apache
    <VirtualHost *:80>
        ServerName tu-dominio.com
        DocumentRoot /path/to/crater/public
        
        <Directory /path/to/crater/public>
            AllowOverride All
            Require all granted
        </Directory>
        
        ErrorLog ${APACHE_LOG_DIR}/crater_error.log
        CustomLog ${APACHE_LOG_DIR}/crater_access.log combined
    </VirtualHost>
    ```

=== "Nginx"
    ```nginx
    server {
        listen 80;
        server_name tu-dominio.com;
        root /path/to/crater/public;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }
    ```

### 7. Configuración Final

```bash
# Optimiza la aplicación para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Configura permisos
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## ☁️ Despliegue Cloud

### DigitalOcean App Platform

1. **Conecta tu repositorio** en DigitalOcean App Platform
2. **Configura las variables de entorno** necesarias
3. **Despliega automáticamente** desde GitHub

### AWS EC2

```bash
# Instala dependencias en Ubuntu/Debian
sudo apt update
sudo apt install php8.2 php8.2-fpm nginx mysql-server composer nodejs npm

# Sigue los pasos de instalación manual
```

### Vercel/Netlify

!!! note "Solo Frontend"
    Estos servicios solo pueden alojar el frontend. Necesitarás un servidor separado para el backend.

## 🔧 Configuración Post-Instalación

### 1. Asistente de Configuración

1. Visita tu instalación en el navegador
2. Completa el asistente de configuración inicial:
   - Información de la empresa
   - Usuario administrador
   - Configuración de correo
   - Configuración de moneda

### 2. Configuración de Correo

Edita `.env` para configurar el envío de correos:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-app-password
MAIL_ENCRYPTION=tls
```

### 3. Configuración de Almacenamiento

```env
# Almacenamiento local (por defecto)
FILESYSTEM_DISK=local

# Amazon S3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=tu-key
AWS_SECRET_ACCESS_KEY=tu-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu-bucket
```

### 4. Tareas Programadas (Cron)

Agrega esta línea a tu crontab:

```bash
* * * * * cd /path/to/invoiceshelf && php artisan schedule:run >> /dev/null 2>&1
```

## ✅ Verificación de la Instalación

### Lista de Verificación

- [ ] La aplicación carga correctamente en el navegador
- [ ] Puedes iniciar sesión como administrador
- [ ] Puedes crear una factura de prueba
- [ ] Los correos se envían correctamente
- [ ] Las tareas programadas funcionan

### Comandos de Diagnóstico

```bash
# Verifica el estado de la aplicación
php artisan about

# Verifica la configuración
php artisan config:show

# Ejecuta las verificaciones de salud
php artisan health:check
```

## 🆘 Solución de Problemas

### Errores Comunes

!!! failure "Error 500 - Internal Server Error"
    **Causa**: Permisos incorrectos o configuración de base de datos.
    
    **Solución**:
    ```bash
    chmod -R 755 storage bootstrap/cache
    php artisan config:clear
    ```

!!! failure "Página en Blanco"
    **Causa**: Error de PHP o configuración de servidor web.
    
    **Solución**: Revisa los logs de error de PHP y del servidor web.

!!! failure "Assets No Cargan"
    **Causa**: Configuración incorrecta de Vite o permisos.
    
    **Solución**:
    ```bash
    npm run build
    php artisan storage:link
    ```

### Logs de Depuración

```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log

# Ver logs del servidor web
tail -f /var/log/nginx/error.log   # Nginx
tail -f /var/log/apache2/error.log # Apache
```

## 🔄 Actualizaciones

Para actualizar InvoiceShelf a una nueva versión:

```bash
# Respalda tu instalación actual
php artisan backup:run

# Descarga la nueva versión
git pull origin master

# Actualiza dependencias
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Ejecuta migraciones
php artisan migrate --force

# Limpia cache
php artisan cache:clear
php artisan config:cache
```

---

## 🎉 ¡Instalación Completada!

¡Felicitaciones! InvoiceShelf está ahora instalado y listo para usar.

**Próximos Pasos:**
- [Configuración Básica](configuration.md)
- [Guía de Usuario](../user-guide/getting-started.md)
- [API Documentation](../api/introduction.md)

**¿Necesitas Ayuda?**
- [Discord Community](https://discord.gg/eHXf4zWhsR)
- [GitHub Issues](https://github.com/InvoiceShelf/invoiceshelf/issues)
- [Documentación Completa](../index.md)
