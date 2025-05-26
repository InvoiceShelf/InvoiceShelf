# Análisis del Filtro "Activo" - Dashboard InvoiceShelf

## Resumen del Análisis

Este documento detalla el análisis realizado para implementar el filtro "Activo" en el dashboard de InvoiceShelf, que permitirá mostrar solo elementos activos/vigentes.

## Estructura Actual del Dashboard

### Frontend (Vue.js)
- **Admin Dashboard**: `resources/scripts/admin/views/dashboard/Dashboard.vue`
- **Customer Dashboard**: `resources/scripts/customer/views/dashboard/Dashboard.vue`
- **Componentes principales**:
  - `DashboardStats.vue` - Tarjetas de estadísticas
  - `DashboardTable.vue` - Tablas de elementos recientes
  - `DashboardChart.vue` - Gráficos financieros (solo admin)

### Backend (Laravel)
- **Admin Controller**: `app/Http/Controllers/V1/Admin/Dashboard/DashboardController.php`
- **Customer Controller**: `app/Http/Controllers/V1/Customer/General/DashboardController.php`

### Stores (Pinia)
- **Admin Store**: `resources/scripts/admin/stores/dashboard.js`
- **Customer Store**: `resources/scripts/customer/stores/dashboard.js`

## Entidades y Estados Identificados

### 1. Invoices (Facturas)
**Estados disponibles**:
- `DRAFT` - Borrador
- `SENT` - Enviada
- `VIEWED` - Vista
- `COMPLETED` - Completada
- `UNPAID` - No pagada
- `PARTIALLY_PAID` - Parcialmente pagada
- `PAID` - Pagada

**Criterio "Activo"**: Facturas con estado `SENT`, `VIEWED` y paid_status `UNPAID`, `PARTIALLY_PAID` (excluir `DRAFT`, `COMPLETED`, `PAID`)

### 2. Estimates (Presupuestos)
**Estados disponibles**:
- `DRAFT` - Borrador
- `SENT` - Enviado
- `VIEWED` - Visto
- `EXPIRED` - Expirado
- `ACCEPTED` - Aceptado
- `REJECTED` - Rechazado

**Criterio "Activo"**: Presupuestos con estado `SENT`, `VIEWED` (excluir `DRAFT`, `EXPIRED`, `ACCEPTED`, `REJECTED`)

### 3. Customers (Clientes)
**Campos relevantes**:
- `enable_portal` - Boolean para acceso al portal
- No hay campo de estado específico

**Criterio "Activo"**: Clientes con `enable_portal = true` y que tengan facturas o presupuestos activos

### 4. Payments (Pagos)
**Consideración**: Los pagos no tienen estados de "activo/inactivo" por naturaleza, se consideran siempre válidos una vez creados.

## Implementación Realizada

### 1. Dashboard Stores Actualizados ✅

#### Admin Store (`resources/scripts/admin/stores/dashboard.js`)
```javascript
// Nuevo estado agregado
activeFilter: {
  enabled: false,
  persistKey: 'dashboard_active_filter',
}

// Nuevos getters
isActiveFilterEnabled: (state) => state.activeFilter.enabled

// Nuevas acciones
- toggleActiveFilter()
- setActiveFilter(enabled)
- persistActiveFilter()
- loadActiveFilter()
- initialize()
```

#### Customer Store (`resources/scripts/customer/stores/dashboard.js`)
```javascript
// Mismo patrón implementado con clave diferente
persistKey: 'customer_dashboard_active_filter'
```

### 2. Backend Controllers Actualizados ✅

#### Admin Dashboard Controller
- ✅ Acepta parámetro `active_only` (boolean)
- ✅ Filtrado implementado en todas las consultas:
  - **Invoices**: `whereIn('status', ['SENT', 'VIEWED'])->whereIn('paid_status', ['UNPAID', 'PARTIALLY_PAID'])`
  - **Estimates**: `whereIn('status', ['SENT', 'VIEWED'])`
  - **Customers**: `where('enable_portal', true)` + relaciones activas
  - **Chart data**: Filtrado aplicado a totales mensuales
- ✅ Respuesta incluye `active_filter_applied` para debugging
- ✅ Documentación PHPDoc actualizada

#### Customer Dashboard Controller
- ✅ Acepta parámetro `active_only` (boolean)
- ✅ Filtrado implementado en todas las consultas:
  - **Invoices**: `whereIn('status', ['SENT', 'VIEWED'])->whereIn('paid_status', ['UNPAID', 'PARTIALLY_PAID'])`
  - **Estimates**: `whereIn('status', ['SENT', 'VIEWED'])`
  - **Payments**: Sin filtro (siempre activos)
- ✅ Respuesta incluye `active_filter_applied` para debugging
- ✅ Documentación PHPDoc actualizada

### 3. Tests Automatizados Implementados ✅

#### Admin Dashboard Tests (`tests/Feature/Admin/DashboardTest.php`)
- ✅ Test básico de dashboard sin filtro
- ✅ Test de dashboard con filtro activo
- ✅ Test de filtrado correcto de invoices
- ✅ Test de filtrado correcto de estimates
- ✅ Test de filtrado correcto de customers
- ✅ Test de variaciones de parámetros boolean

#### Customer Dashboard Tests (`tests/Feature/Customer/DashboardTest.php`)
- ✅ Test básico de dashboard sin filtro
- ✅ Test de dashboard con filtro activo
- ✅ Test de filtrado correcto de invoices
- ✅ Test de filtrado correcto de estimates
- ✅ Test de variaciones de parámetros boolean

### 4. Funcionalidades Implementadas

#### Persistencia
- Estado del filtro guardado en `localStorage`
- Claves separadas para admin y customer
- Recuperación automática al inicializar

#### API Integration
- Parámetro `active_only=true` enviado cuando el filtro está activo
- Recarga automática de datos al cambiar el filtro
- ✅ Backend procesa correctamente el parámetro
- ✅ Filtrado aplicado a todas las consultas relevantes

#### Gestión de Estado
- Getters para acceso reactivo al estado
- Acciones para toggle y set del filtro
- Manejo de errores en persistencia

## Próximos Pasos

### ~~Paso 2: Backend Implementation~~ ✅ COMPLETADO
1. ✅ Modificar `DashboardController.php` para aceptar `active_only` parameter
2. ✅ Implementar lógica de filtrado en consultas
3. ✅ Agregar tests PHPUnit
4. ✅ Documentar con PHPDoc

### Paso 3: Frontend UI
1. Crear componente toggle para el filtro
2. Integrar en `DashboardStats.vue`
3. Agregar indicadores visuales del estado del filtro
4. Implementar transiciones suaves

### Paso 4: Setup Base del Modo Oscuro
1. Configurar `darkMode: 'class'` en `tailwind.config.js`
2. Crear store para el tema (`useThemeStore`)
3. Implementar persistencia en localStorage
4. Crear composable `useTheme` para manejo del tema

## Consideraciones Técnicas

### Performance
- El filtro puede reducir significativamente el volumen de datos
- Considerar índices en campos de estado para optimización

### UX
- El filtro debe ser claramente visible pero no intrusivo
- Estado persistente mejora la experiencia del usuario
- Indicadores visuales cuando el filtro está activo

### Compatibilidad
- Implementación backward-compatible
- Parámetro opcional en API
- Fallback graceful si localStorage no está disponible

### Seguridad
- Validación de parámetros en backend
- Autorización mantenida en todas las consultas
- Tests cubren casos edge

## Criterios de Aceptación

- ✅ Filtro funcional en admin dashboard (backend)
- ✅ Filtro funcional en customer dashboard (backend)
- ✅ Estado persistente entre sesiones (frontend)
- ✅ API backend soporta parámetro `active_only`
- ✅ Tests automatizados implementados
- ✅ Documentación completa
- [ ] UX intuitiva y responsive (Paso 3)

## Detalles de Implementación Backend

### Lógica de Filtrado Implementada

#### Para Invoices (Facturas)
```php
if ($activeOnly) {
    $query->whereIn('status', [
        Invoice::STATUS_SENT,
        Invoice::STATUS_VIEWED
    ])->whereIn('paid_status', [
        Invoice::STATUS_UNPAID,
        Invoice::STATUS_PARTIALLY_PAID
    ]);
}
```

#### Para Estimates (Presupuestos)
```php
if ($activeOnly) {
    $query->whereIn('status', [
        Estimate::STATUS_SENT,
        Estimate::STATUS_VIEWED
    ]);
}
```

#### Para Customers (Clientes)
```php
if ($activeOnly) {
    $query->where('enable_portal', true)
        ->where(function ($query) {
            $query->whereHas('invoices', function ($subQuery) {
                $subQuery->whereIn('status', [
                    Invoice::STATUS_SENT,
                    Invoice::STATUS_VIEWED
                ])->whereIn('paid_status', [
                    Invoice::STATUS_UNPAID,
                    Invoice::STATUS_PARTIALLY_PAID
                ]);
            })->orWhereHas('estimates', function ($subQuery) {
                $subQuery->whereIn('status', [
                    Estimate::STATUS_SENT,
                    Estimate::STATUS_VIEWED
                ]);
            });
        });
}
``` 