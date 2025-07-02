# 📋 Requisitos del Sistema

Esta página detalla todos los requisitos necesarios para ejecutar InvoiceShelf correctamente.

## 🖥️ Requisitos del Servidor

### Recursos Mínimos

| Recurso | Mínimo | Recomendado | Producción |
|---------|--------|-------------|------------|
| **RAM** | 512MB | 2GB | 4GB+ |
| **CPU** | 1 Core | 2 Cores | 4+ Cores |
| **Almacenamiento** | 1GB | 5GB | 20GB+ |
| **Ancho de Banda** | - | 10Mbps | 100Mbps+ |

!!! tip "Escalabilidad"
    Los requisitos pueden variar según el número de usuarios y volumen de datos.

## 🐘 PHP

### Versión Requerida
- **Mínimo**: PHP 8.2
- **Recomendado**: PHP 8.3+

### Extensiones Requeridas

```bash
# Verificar extensiones instaladas
php -m | grep -E "(extension_name)"
```

#### Extensiones Obligatorias
- `pdo` - Abstracción de base de datos
- `pdo_mysql` - Driver MySQL para PDO
- `mbstring` - Manipulación de strings multi-byte
- `json` - Soporte para JSON
- `curl` - Cliente HTTP
- `openssl` - Funciones de encriptación
- `tokenizer` - Tokenizador de PHP
- `xml` - Procesamiento XML
- `zip` - Compresión ZIP
- `gd` - Procesamiento de imágenes

#### Extensiones Opcionales
- `redis` - Cache Redis (recomendado para producción)
- `imagick` - Manipulación avanzada de imágenes
- `intl` - Internacionalización
- `bcmath` - Matemáticas de precisión arbitraria

### Configuración PHP

Configuraciones recomendadas en `php.ini`:

```ini
; Memoria
memory_limit = 256M

; Tiempo de ejecución
max_execution_time = 300
max_input_time = 300

; Archivos
upload_max_filesize = 50M
post_max_size = 50M
max_file_uploads = 20

; Otros
date.timezone = "UTC"
display_errors = Off (en producción)
log_errors = On
```

## 🗄️ Base de Datos

### Motores Soportados

=== "MySQL (Recomendado)"
    - **Versión Mínima**: 5.7
    - **Recomendada**: 8.0+
    - **Configuración**:
    ```sql
    -- Configuraciones recomendadas
    SET GLOBAL innodb_file_format = 'Barracuda';
    SET GLOBAL innodb_large_prefix = 1;
    ```

=== "PostgreSQL"
    - **Versión Mínima**: 12
    - **Recomendada**: 15+
    - **Extensiones**: `uuid-ossp`, `pg_trgm`

=== "SQLite"
    - **Versión Mínima**: 3.8
    - **Uso**: Solo para desarrollo/testing
    - **No recomendado para producción**

### Configuración de Base de Datos

#### MySQL
```sql
-- Crear base de datos
CREATE DATABASE invoiceshelf 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Crear usuario
CREATE USER 'invoiceshelf'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON invoiceshelf.* TO 'invoiceshelf'@'localhost';
FLUSH PRIVILEGES;
```

#### PostgreSQL
```sql
-- Crear base de datos
CREATE DATABASE invoiceshelf 
WITH ENCODING 'UTF8' 
LC_COLLATE='en_US.UTF-8' 
LC_CTYPE='en_US.UTF-8';

-- Crear usuario
CREATE USER invoiceshelf WITH PASSWORD 'password';
GRANT ALL PRIVILEGES ON DATABASE invoiceshelf TO invoiceshelf;
```

## 🌐 Servidor Web

### Opciones Soportadas

=== "Nginx (Recomendado)"
    ```nginx
    server {
        listen 80;
        server_name example.com;
        root /var/www/invoiceshelf/public;
        index index.php;

        # Configuración optimizada
        client_max_body_size 50M;
        
        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
            
            # Configuraciones de seguridad
            fastcgi_param HTTP_PROXY "";
            fastcgi_buffers 16 16k;
            fastcgi_buffer_size 32k;
        }

        # Bloquear archivos sensibles
        location ~ /\. {
            deny all;
        }
    }
    ```

=== "Apache"
    ```apache
    <VirtualHost *:80>
        ServerName example.com
        DocumentRoot /var/www/invoiceshelf/public
        
        # Configuración optimizada
        LimitRequestBody 52428800  # 50MB
        
        <Directory /var/www/invoiceshelf/public>
            AllowOverride All
            Require all granted
            
            # Configuración de seguridad
            Options -Indexes
        </Directory>
        
        # Configuración de logs
        ErrorLog ${APACHE_LOG_DIR}/invoiceshelf_error.log
        CustomLog ${APACHE_LOG_DIR}/invoiceshelf_access.log combined
    </VirtualHost>
    ```

=== "Servidor de Desarrollo"
    ```bash
    # Solo para desarrollo
    php artisan serve --host=0.0.0.0 --port=8000
    ```

## 📦 Node.js y NPM

### Versiones Requeridas
- **Node.js**: 18.x o superior
- **NPM**: 8.x o superior
- **Recomendado**: Node.js 20.x LTS

### Verificación
```bash
# Verificar versiones
node --version
npm --version

# Verificar compatibilidad
npx envinfo --binaries --npmPackages
```

## 🛠️ Herramientas de Desarrollo

### Composer (PHP)
```bash
# Verificar versión
composer --version

# Mínimo: 2.0
# Recomendado: 2.5+
```

### Git
```bash
# Verificar versión
git --version

# Mínimo: 2.0
# Recomendado: 2.30+
```

## ☁️ Servicios Externos

### Servicios de Correo (Opcional)
- **SMTP**: Cualquier proveedor SMTP
- **Servicios Recomendados**:
  - Mailgun
  - SendGrid
  - Amazon SES
  - Gmail SMTP

### Almacenamiento (Opcional)
- **Local**: Sistema de archivos local (por defecto)
- **Cloud**:
  - Amazon S3
  - Google Cloud Storage
  - DigitalOcean Spaces

### Cache (Opcional)
- **Redis**: Recomendado para producción
- **Memcached**: Alternativa a Redis
- **File Cache**: Por defecto (no recomendado para producción)

## 🔒 Configuración de Seguridad

### SSL/TLS
!!! warning "Requerido para Producción"
    Siempre usa HTTPS en producción para proteger datos sensibles.

```bash
# Obtener certificado SSL gratuito con Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tu-dominio.com
```

### Firewall
```bash
# Configurar UFW (Ubuntu)
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable

# Configurar iptables (alternativa)
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
```

### Permisos de Archivos
```bash
# Configurar permisos correctos
find /var/www/invoiceshelf -type f -exec chmod 644 {} \;
find /var/www/invoiceshelf -type d -exec chmod 755 {} \;
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data /var/www/invoiceshelf
```

## 🐳 Docker (Alternativa)

Si prefieres usar Docker, estos son los requisitos:

### Requisitos Docker
- **Docker**: 20.10+
- **Docker Compose**: 2.0+
- **RAM**: 2GB disponible para contenedores
- **Almacenamiento**: 5GB para imágenes y volúmenes

### Verificación Docker
```bash
# Verificar instalación
docker --version
docker-compose --version

# Verificar que funciona
docker run hello-world
```

## 📊 Monitoreo del Sistema

### Herramientas Recomendadas
- **Sistema**: `htop`, `iotop`, `nethogs`
- **Aplicación**: Laravel Telescope, Laravel Horizon
- **Logs**: `tail`, `grep`, herramientas de análisis de logs

### Comandos de Verificación
```bash
# Verificar recursos del sistema
free -h          # Memoria
df -h           # Almacenamiento
top             # Procesos

# Verificar servicios
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mysql
```

## ✅ Lista de Verificación

Antes de la instalación, asegúrate de tener:

- [ ] PHP 8.2+ con todas las extensiones requeridas
- [ ] Base de datos configurada (MySQL/PostgreSQL)
- [ ] Servidor web configurado (Nginx/Apache)
- [ ] Node.js 18+ y NPM
- [ ] Composer 2.0+
- [ ] SSL/TLS configurado (para producción)
- [ ] Permisos de archivos correctos
- [ ] Firewall configurado apropiadamente

---

## 🔄 Siguiente Paso

Una vez que hayas verificado todos los requisitos:

[📥 Continuar con la Instalación](index.md){ .md-button .md-button--primary }

¿Necesitas ayuda con algún requisito específico? Consulta nuestra [guía de configuración](configuration.md) o únete a nuestro [Discord](https://discord.gg/eHXf4zWhsR).
