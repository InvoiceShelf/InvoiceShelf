# 🔌 API Introduction

Crater proporc### 🔑 Autenticación

Crater utiliza **Laravel Sanctum** para autenticación API.a una **API RESTful completa** que permite integrar la funcionalidad de facturación en aplicaciones externas.

## 🚀 Características de la API

### ✨ Funcionalidades Principales

- 📄 **Gestión de Facturas** - CRUD completo
- 👥 **Gestión de Clientes** - Registro y administración
- 📦 **Gestión de Productos** - Catálogo de items
- 💰 **Procesamiento de Pagos** - Registro de pagos
- 📊 **Reportes y Estadísticas** - Datos analíticos
- 🏢 **Multi-empresa** - Soporte para múltiples organizaciones

### 🛡️ Características Técnicas

- **Autenticación**: Laravel Sanctum (Bearer Token)
- **Formato**: JSON (Request/Response)
- **Versionado**: API versionada (`/api/v1/`)
- **Rate Limiting**: 60 requests/minuto por defecto
- **CORS**: Configurado para cross-origin requests
- **Paginación**: Automática en colecciones grandes
- **Filtros**: Query parameters para filtrado avanzado

## 🌐 Base URL

```
https://tu-dominio.com/api/v1/
```

### Ambientes

| Ambiente | Base URL | Propósito |
|----------|----------|-----------|
| **Producción** | `https://app.crater.financial/api/v1/` | Datos reales |
| **Staging** | `https://staging.crater.financial/api/v1/` | Testing |
| **Local** | `http://localhost:8000/api/v1/` | Desarrollo |

## 🔑 Autenticación

InvoiceShelf utiliza **Laravel Sanctum** para autenticación API.

### Obtener Token de Acceso

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password",
  "device_name": "Mi Aplicación"
}
```

**Respuesta:**
```json
{
  "data": {
    "token": "1|abc123def456...",
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "company_id": 1
    }
  }
}
```

### Usar Token en Requests

```http
GET /api/v1/invoices
Authorization: Bearer 1|abc123def456...
Accept: application/json
```

### Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer 1|abc123def456...
```

## 📋 Estructura de Respuestas

### Respuesta Exitosa (200)

```json
{
  "data": {
    "id": 1,
    "invoice_number": "INV-001",
    "customer": {
      "id": 1,
      "name": "Cliente Ejemplo"
    }
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.0.0"
  }
}
```

### Respuesta de Colección

```json
{
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-001"
    },
    {
      "id": 2,
      "invoice_number": "INV-002"
    }
  ],
  "links": {
    "first": "https://api.example.com/invoices?page=1",
    "last": "https://api.example.com/invoices?page=5",
    "prev": null,
    "next": "https://api.example.com/invoices?page=2"
  },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 67,
    "last_page": 5
  }
}
```

### Respuesta de Error

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Los datos proporcionados no son válidos.",
    "details": {
      "email": ["El campo email es obligatorio."],
      "amount": ["El campo amount debe ser un número."]
    }
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "req_abc123"
  }
}
```

## 🔢 Códigos de Estado HTTP

| Código | Significado | Uso |
|--------|-------------|-----|
| **200** | OK | Request exitoso |
| **201** | Created | Recurso creado exitosamente |
| **204** | No Content | Eliminación exitosa |
| **400** | Bad Request | Datos inválidos |
| **401** | Unauthorized | Token inválido/expirado |
| **403** | Forbidden | Sin permisos |
| **404** | Not Found | Recurso no encontrado |
| **422** | Unprocessable Entity | Errores de validación |
| **429** | Too Many Requests | Rate limit excedido |
| **500** | Internal Server Error | Error del servidor |

## 📊 Rate Limiting

### Límites por Defecto

| Endpoint | Límite | Ventana |
|----------|--------|---------|
| **Auth** | 5 requests | 1 minuto |
| **API General** | 60 requests | 1 minuto |
| **Reports** | 30 requests | 1 minuto |

### Headers de Rate Limit

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1642248300
```

### Error de Rate Limit

```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Demasiadas peticiones. Intenta de nuevo en 60 segundos."
  }
}
```

## 🔍 Filtros y Paginación

### Query Parameters

```http
GET /api/v1/invoices?status=sent&customer_id=1&per_page=20&page=2
```

#### Filtros Comunes

| Parameter | Descripción | Ejemplo |
|-----------|-------------|---------|
| `status` | Filtrar por estado | `?status=sent` |
| `search` | Búsqueda en texto | `?search=INV-001` |
| `date_from` | Fecha desde | `?date_from=2024-01-01` |
| `date_to` | Fecha hasta | `?date_to=2024-01-31` |
| `customer_id` | ID del cliente | `?customer_id=1` |

#### Paginación

| Parameter | Descripción | Default |
|-----------|-------------|---------|
| `page` | Número de página | 1 |
| `per_page` | Items por página | 15 |

#### Ordenamiento

```http
GET /api/v1/invoices?sort=created_at&order=desc
```

| Parameter | Descripción | Valores |
|-----------|-------------|---------|
| `sort` | Campo de ordenamiento | `created_at`, `total`, `invoice_number` |
| `order` | Dirección | `asc`, `desc` |

## 📚 Recursos Principales

### 📄 Facturas

```http
# Listar facturas
GET /api/v1/invoices

# Obtener factura
GET /api/v1/invoices/{id}

# Crear factura
POST /api/v1/invoices

# Actualizar factura
PUT /api/v1/invoices/{id}

# Eliminar factura
DELETE /api/v1/invoices/{id}

# Enviar factura por email
POST /api/v1/invoices/{id}/send

# Marcar como pagada
POST /api/v1/invoices/{id}/mark-paid
```

### 👥 Clientes

```http
# Listar clientes
GET /api/v1/customers

# Obtener cliente
GET /api/v1/customers/{id}

# Crear cliente
POST /api/v1/customers

# Actualizar cliente
PUT /api/v1/customers/{id}

# Eliminar cliente
DELETE /api/v1/customers/{id}
```

### 📦 Items/Productos

```http
# Listar items
GET /api/v1/items

# Obtener item
GET /api/v1/items/{id}

# Crear item
POST /api/v1/items

# Actualizar item
PUT /api/v1/items/{id}

# Eliminar item
DELETE /api/v1/items/{id}
```

### 💰 Pagos

```http
# Listar pagos
GET /api/v1/payments

# Obtener pago
GET /api/v1/payments/{id}

# Crear pago
POST /api/v1/payments

# Actualizar pago
PUT /api/v1/payments/{id}

# Eliminar pago
DELETE /api/v1/payments/{id}
```

## 🔗 Relaciones y Expansiones

### Incluir Relaciones

```http
GET /api/v1/invoices?include=customer,items,payments
```

**Respuesta:**
```json
{
  "data": {
    "id": 1,
    "invoice_number": "INV-001",
    "customer": {
      "id": 1,
      "name": "Cliente Ejemplo",
      "email": "cliente@example.com"
    },
    "items": [
      {
        "id": 1,
        "name": "Producto 1",
        "quantity": 2,
        "price": 50.00
      }
    ],
    "payments": [
      {
        "id": 1,
        "amount": 100.00,
        "payment_date": "2024-01-15"
      }
    ]
  }
}
```

### Relaciones Disponibles

| Recurso | Relaciones |
|---------|------------|
| **Invoice** | `customer`, `items`, `payments`, `company` |
| **Customer** | `invoices`, `payments`, `company` |
| **Payment** | `invoice`, `customer`, `company` |
| **Item** | `company` |

## 🧪 Testing de la API

### Postman Collection

Descarga nuestra colección de Postman:

```bash
curl -O https://api.crater.financial/postman/collection.json
```

### Ejemplo con cURL

```bash
# Autenticarse
curl -X POST https://tu-dominio.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password",
    "device_name": "Test App"
  }'

# Usar token
curl -X GET https://tu-dominio.com/api/v1/invoices \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Accept: application/json"
```

### Ejemplo con JavaScript

```javascript
// Configuración base
const API_BASE = 'https://tu-dominio.com/api/v1';
const token = '1|abc123def456...';

// Función helper
async function apiRequest(endpoint, options = {}) {
  const response = await fetch(`${API_BASE}${endpoint}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...options.headers
    },
    ...options
  });
  
  return response.json();
}

// Obtener facturas
const invoices = await apiRequest('/invoices');

// Crear factura
const newInvoice = await apiRequest('/invoices', {
  method: 'POST',
  body: JSON.stringify({
    customer_id: 1,
    invoice_date: '2024-01-15',
    items: [
      {
        name: 'Producto 1',
        quantity: 2,
        price: 50.00
      }
    ]
  })
});
```

## 📖 Documentación Interactiva

### Swagger/OpenAPI

Accede a la documentación interactiva:

```
https://tu-dominio.com/api/documentation
```

### Características de Swagger

- 🔍 **Explorador de API** interactivo
- 🧪 **Testing en vivo** de endpoints
- 📋 **Esquemas de datos** detallados
- 🔑 **Autenticación integrada**

## 🔧 SDK y Wrappers

### SDK Oficial (Próximamente)

```bash
# PHP
composer require crater/php-sdk

# JavaScript/Node.js
npm install @crater/js-sdk

# Python
pip install crater-sdk
```

### Wrapper Básico en PHP

```php
<?php

class CraterAPI
{
    private $baseUrl;
    private $token;
    
    public function __construct($baseUrl, $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }
    
    public function get($endpoint)
    {
        return $this->request('GET', $endpoint);
    }
    
    public function post($endpoint, $data = [])
    {
        return $this->request('POST', $endpoint, $data);
    }
    
    private function request($method, $endpoint, $data = null)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}

// Uso
$api = new CraterAPI('https://tu-dominio.com/api/v1', 'tu-token');
$invoices = $api->get('/invoices');
```

## 🚨 Manejo de Errores

### Tipos de Errores

1. **Errores de Validación (422)**
   ```json
   {
     "error": {
       "code": "VALIDATION_ERROR",
       "message": "Los datos no son válidos",
       "details": {
         "email": ["El email es requerido"]
       }
     }
   }
   ```

2. **Errores de Autenticación (401)**
   ```json
   {
     "error": {
       "code": "UNAUTHENTICATED",
       "message": "Token inválido o expirado"
     }
   }
   ```

3. **Errores de Autorización (403)**
   ```json
   {
     "error": {
       "code": "FORBIDDEN",
       "message": "No tienes permisos para esta acción"
     }
   }
   ```

### Best Practices para Manejo de Errores

```javascript
async function handleApiCall() {
  try {
    const response = await apiRequest('/invoices');
    
    if (response.error) {
      switch (response.error.code) {
        case 'VALIDATION_ERROR':
          // Mostrar errores de validación
          showValidationErrors(response.error.details);
          break;
        case 'UNAUTHENTICATED':
          // Redireccionar a login
          redirectToLogin();
          break;
        default:
          // Error genérico
          showError(response.error.message);
      }
      return;
    }
    
    // Procesar respuesta exitosa
    processData(response.data);
    
  } catch (error) {
    // Error de red o parsing
    showError('Error de conexión');
  }
}
```

---

## 📚 Próximos Pasos

- [🔐 Autenticación Detallada](authentication.md)
- [📋 Endpoints Completos](endpoints.md)
- [🧪 Ejemplos de Integración](examples.md)

¿Necesitas ayuda con la API? Revisa los [ejemplos de código](https://github.com/crater-invoice/examples).
