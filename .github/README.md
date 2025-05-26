# CI/CD Pipeline Documentation

Este repositorio incluye un pipeline completo de CI/CD configurado con GitHub Actions para las ramas `master` y `develop`.

## Estructura de Pipelines

### 1. CI Pipeline (`.github/workflows/ci.yml`)

Se ejecuta en cada push y pull request a las ramas `master` y `develop`.

**Trabajos incluidos:**
- **php-tests**: Ejecuta pruebas PHP/Laravel con múltiples versiones de PHP (8.2, 8.3)
- **frontend-tests**: Ejecuta linter y build de los assets frontend (Vue.js)
- **security-audit**: Ejecuta auditorías de seguridad para dependencias PHP y Node.js
- **deployment-check**: Prepara artefactos de despliegue para ramas principales
- **notify**: Envía notificaciones del estado del pipeline

**Características:**
- Matriz de testing con PHP 8.2 y 8.3
- Matriz de testing con Node.js 18.x y 20.x
- Base de datos MySQL para pruebas
- Cache de dependencias para mejorar rendimiento
- Cobertura de código con Xdebug
- Linting con PHP Pint y ESLint

### 2. Deploy Pipeline (`.github/workflows/deploy.yml`)

Se ejecuta en pushes a `master` o manualmente via workflow_dispatch.

**Ambientes:**
- **Staging**: Despliega automáticamente desde la rama `develop`
- **Production**: Despliega desde la rama `master` con verificaciones adicionales

### 3. Release Pipeline (`.github/workflows/release.yml`)

Se ejecuta automáticamente cuando se crea un tag con formato `v*`.

**Características:**
- Genera release automático en GitHub
- Crea paquete de distribución optimizado
- Build y push de imagen Docker
- Generación automática de changelog

## Configuración de Entorno

### Variables de Entorno Necesarias

Para que el pipeline funcione correctamente, configura los siguientes secrets en GitHub:

```bash
# Para releases con Docker
DOCKERHUB_USERNAME=tu_usuario_docker
DOCKERHUB_TOKEN=tu_token_docker

# Para notificaciones (opcional)
SLACK_WEBHOOK_URL=tu_webhook_slack
DISCORD_WEBHOOK_URL=tu_webhook_discord
```

### Archivos de Configuración

- **`.env.ci`**: Configuración específica para el entorno de CI
- **`.env.example`**: Plantilla de configuración estándar

## Flujo de Trabajo

### Desarrollo

1. **Feature Branch** → Crear rama desde `develop`
2. **Pull Request** → Hacia `develop`
3. **CI Checks** → Ejecuta todos los tests y verificaciones
4. **Merge** → Si todos los checks pasan
5. **Deploy to Staging** → Automático tras merge a `develop`

### Producción

1. **Release Branch** → Crear desde `develop`
2. **Pull Request** → Hacia `master`
3. **CI Checks** → Ejecuta verificaciones completas
4. **Merge** → Si todos los checks pasan
5. **Deploy to Production** → Manual o automático
6. **Tag Creation** → Crear tag `v*` para release oficial

### Releases

1. **Create Tag** → `git tag v1.0.0 && git push origin v1.0.0`
2. **Automatic Release** → GitHub Release creado automáticamente
3. **Docker Image** → Imagen subida a Docker Hub
4. **Notification** → Equipo notificado del nuevo release

## Comandos Útiles

### Ejecutar tests localmente

```bash
# Tests PHP
composer test
./vendor/bin/pest
./vendor/bin/pint --test

# Tests Frontend
npm test
npm run build

# Auditoría de seguridad
composer audit
npm audit
```

### Crear release

```bash
# Crear y push tag para release
git tag v1.0.0
git push origin v1.0.0

# O usar GitHub CLI
gh release create v1.0.0 --generate-notes
```

## Dependabot

El archivo `.github/dependabot.yml` configura actualizaciones automáticas semanales para:
- Dependencias de Composer (PHP)
- Dependencias de npm (Node.js)
- GitHub Actions

## Monitoreo

### Estados del Pipeline

- ✅ **Success**: Todos los checks pasaron
- ❌ **Failed**: Uno o más checks fallaron
- ⏳ **Pending**: Pipeline en ejecución
- ⚠️ **Warning**: Checks pasaron con warnings

### Artefactos

Los artefactos de deployment se almacenan por 30 días y incluyen:
- Código optimizado para producción
- Assets compilados
- Dependencias de producción

## Troubleshooting

### Errores Comunes

1. **Tests PHP fallan**: Verificar configuración de base de datos en `.env.ci`
2. **Build frontend falla**: Verificar dependencias en `package.json`
3. **Deploy falla**: Verificar permisos y configuración de secrets
4. **Release falla**: Verificar formato de tag y permisos de repositorio

### Debug

Para debuggear issues en el pipeline:

1. Revisar logs en la sección Actions de GitHub
2. Ejecutar comandos localmente con la misma configuración
3. Verificar secrets y variables de entorno
4. Comprobar permisos del repositorio

## Contribuir

Para mejorar el pipeline:

1. Fork del repositorio
2. Crear rama para mejoras del pipeline
3. Testear cambios en fork
4. Crear PR con descripción detallada de cambios
