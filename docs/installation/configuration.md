# ⚙️ Configuración

Guía completa para configurar InvoiceShelf después de la instalación.

## 🚀 Configuración Inicial

### Asistente de Configuración Web

Después de la instalación, visita tu dominio para acceder al asistente de configuración:

1. **Verificación del Sistema** - Verificación automática de requisitos
2. **Configuración de Base de Datos** - Si no se configuró previamente
3. **Información de la Empresa** - Datos de tu empresa
4. **Usuario Administrador** - Crear cuenta de administrador
5. **Configuración Final** - Optimizaciones y cache

## 📧 Configuración de Correo

### Variables de Entorno

Edita el archivo `.env` para configurar el envío de correos:

=== "SMTP"
    ```env
    MAIL_MAILER=smtp
    MAIL_HOST=smtp.gmail.com
    MAIL_PORT=587
    MAIL_USERNAME=tu-email@gmail.com
    MAIL_PASSWORD=tu-app-password
    MAIL_ENCRYPTION=tls
    MAIL_FROM_ADDRESS=noreply@tu-dominio.com
    MAIL_FROM_NAME="Tu Empresa"
    ```

=== "Mailgun"
    ```env
    MAIL_MAILER=mailgun
    MAILGUN_DOMAIN=tu-dominio.mailgun.org
    MAILGUN_SECRET=tu-api-key
    MAIL_FROM_ADDRESS=noreply@tu-dominio.com
    MAIL_FROM_NAME="Tu Empresa"
    ```

=== "SendGrid"
    ```env
    MAIL_MAILER=smtp
    MAIL_HOST=smtp.sendgrid.net
    MAIL_PORT=587
    MAIL_USERNAME=apikey
    MAIL_PASSWORD=tu-sendgrid-api-key
    MAIL_ENCRYPTION=tls
    ```

### Probar Configuración de Correo

```bash
# Enviar correo de prueba
php artisan tinker
>>> Mail::raw('Test email', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

## 💰 Configuración de Monedas

### Configurar Moneda Principal

En el panel de administración:

1. Ve a **Configuración → Empresa**
2. Selecciona tu **moneda principal**
3. Configura el **formato de números**

### Agregar Monedas Adicionales

```bash
# Ejecutar seeder de monedas
php artisan db:seed --class=CurrencySeeder

# O agregar manualmente en: Configuración → Monedas
```

### Tasas de Cambio

```env
# Configurar API de tasas de cambio
EXCHANGE_RATE_API_KEY=tu-api-key
EXCHANGE_RATE_PROVIDER=fixer  # opciones: fixer, openexchangerates
```

## 🏢 Configuración Multi-empresa

### Habilitar Multi-empresa

```env
# En .env
MULTI_COMPANY_ENABLED=true
```

### Configurar Dominios por Empresa

```env
# Configuración de subdominios
MULTI_COMPANY_SUBDOMAIN=true

# O usar rutas
MULTI_COMPANY_SUBDOMAIN=false
```

## 📁 Configuración de Almacenamiento

### Almacenamiento Local (Por Defecto)

```env
FILESYSTEM_DISK=local
```

### Amazon S3

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=tu-access-key
AWS_SECRET_ACCESS_KEY=tu-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### DigitalOcean Spaces

```env
FILESYSTEM_DISK=spaces
DO_ACCESS_KEY_ID=tu-access-key
DO_SECRET_ACCESS_KEY=tu-secret-key
DO_DEFAULT_REGION=nyc3
DO_BUCKET=tu-space-name
DO_ENDPOINT=https://nyc3.digitaloceanspaces.com
```

### Configurar Enlace Simbólico

```bash
# Crear enlace para archivos públicos
php artisan storage:link
```

## 🗄️ Configuración de Base de Datos

### Optimizaciones MySQL

```sql
-- Configuraciones recomendadas para MySQL
SET GLOBAL innodb_buffer_pool_size = 1G;
SET GLOBAL innodb_log_file_size = 256M;
SET GLOBAL max_connections = 200;
SET GLOBAL query_cache_size = 64M;
```

### Configuración de Backup

```env
# Configurar backups automáticos
BACKUP_DISK=s3  # o local
BACKUP_SCHEDULE=daily
BACKUP_RETENTION_DAYS=30
```

```bash
# Ejecutar backup manual
php artisan backup:run

# Configurar cron para backups automáticos
0 2 * * * cd /path/to/invoiceshelf && php artisan backup:run
```

## 🚀 Configuración de Cache

### Redis (Recomendado para Producción)

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
```

### Configurar Cache de Aplicación

```bash
# Limpiar cache
php artisan cache:clear

# Generar cache de configuración
php artisan config:cache

# Generar cache de rutas
php artisan route:cache

# Generar cache de vistas
php artisan view:cache
```

## 📋 Configuración de Colas

### Configurar Supervisor (Producción)

```bash
# Instalar supervisor
sudo apt install supervisor

# Crear configuración
sudo nano /etc/supervisor/conf.d/invoiceshelf-worker.conf
```

```ini
[program:invoiceshelf-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/invoiceshelf/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/invoiceshelf/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Recargar supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start invoiceshelf-worker:*
```

### Configurar Cron Jobs

```bash
# Editar crontab
crontab -e

# Agregar tarea programada
* * * * * cd /var/www/invoiceshelf && php artisan schedule:run >> /dev/null 2>&1
```

## 🔒 Configuración de Seguridad

### Configurar HTTPS

```nginx
# Configuración Nginx con SSL
server {
    listen 443 ssl http2;
    server_name tu-dominio.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    # Configuraciones SSL modernas
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
}
```

### Variables de Seguridad

```env
# Configuraciones de seguridad
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
```

### Configurar Rate Limiting

En `config/invoiceshelf.php`:

```php
'rate_limiting' => [
    'login' => '5,1',      // 5 intentos por minuto
    'api' => '60,1',       // 60 requests por minuto
    'global' => '1000,1',  // 1000 requests por minuto global
],
```

## 🎨 Configuración de PDF

### Configurar Generador PDF

```env
# Opciones: dompdf, gotenberg
PDF_DRIVER=gotenberg

# Configuración Gotenberg
GOTENBERG_ENDPOINT=http://localhost:3000

# Configuración DomPDF
DOMPDF_OPTIONS='{"isHtml5ParserEnabled": true, "isPhpEnabled": false}'
```

### Personalizar Plantillas PDF

```bash
# Publicar plantillas
php artisan vendor:publish --tag=invoiceshelf-pdf-templates

# Plantillas ubicadas en: resources/views/pdf/
```

## 🌍 Configuración de Idiomas

### Idioma por Defecto

```env
APP_LOCALE=es
APP_FALLBACK_LOCALE=en
```

### Agregar Nuevos Idiomas

```bash
# Publicar archivos de idioma
php artisan vendor:publish --tag=invoiceshelf-lang

# Archivos ubicados en: lang/
```

## 📊 Configuración de Logging

### Configurar Logs

```env
LOG_CHANNEL=stack
LOG_STACK=single,slack
LOG_LEVEL=info

# Configurar Slack (opcional)
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

### Rotación de Logs

```bash
# Configurar logrotate
sudo nano /etc/logrotate.d/invoiceshelf
```

```
/var/www/invoiceshelf/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0644 www-data www-data
    postrotate
        /bin/kill -USR1 $(cat /var/run/nginx.pid) > /dev/null 2>&1 || true
    endscript
}
```

## 🔧 Configuraciones Avanzadas

### Configurar Queue Workers

```env
# Configuración de colas
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default

# Para alta concurrencia
QUEUE_FAILED_DRIVER=database
```

### Configurar Websockets (Opcional)

```env
# Para notificaciones en tiempo real
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=tu-app-id
PUSHER_APP_KEY=tu-app-key
PUSHER_APP_SECRET=tu-app-secret
PUSHER_APP_CLUSTER=us2
```

### Variables de Entorno Completas

```env
# Aplicación
APP_NAME="Tu Empresa"
APP_ENV=production
APP_KEY=base64:tu-app-key
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invoiceshelf
DB_USERNAME=invoiceshelf
DB_PASSWORD=password-seguro

# Cache y Sesiones
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Correo
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-app-password
MAIL_ENCRYPTION=tls

# Almacenamiento
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=tu-access-key
AWS_SECRET_ACCESS_KEY=tu-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu-bucket

# Seguridad
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
```

## ✅ Verificar Configuración

### Comandos de Verificación

```bash
# Verificar configuración general
php artisan about

# Verificar configuración de correo
php artisan config:show mail

# Verificar configuración de base de datos
php artisan config:show database

# Ejecutar verificaciones de salud
php artisan health:check
```

### Panel de Administración

Accede al panel de administración para configurar:

- **Empresa**: Información, logo, configuración fiscal
- **Usuarios**: Roles y permisos
- **Plantillas**: Personalizar facturas y correos
- **Impuestos**: Configurar tipos de impuestos
- **Métodos de Pago**: Configurar formas de pago

---

## 🎯 Configuración Completada

¡Tu instalación de InvoiceShelf está ahora completamente configurada!

**Próximos Pasos:**
- [Crear tu primera factura](../user-guide/invoices.md)
- [Configurar la API](../api/introduction.md)
- [Personalizar plantillas](../customization/templates.md)

**¿Necesitas Ayuda?**
- [Discord Community](https://discord.gg/eHXf4zWhsR)
- [GitHub Issues](https://github.com/InvoiceShelf/invoiceshelf/issues)
