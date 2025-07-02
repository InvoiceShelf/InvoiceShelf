# Fix para Inconsistencia de Decimales en Exportaciones

## Problema Identificado

Los archivos exportados (PDF, XLSX, CSV) mostraban decimales inconsistentes debido a dos problemas principales:

1. **Diferencias en el formateo**:
   - **PDF**: Usaba `number_format($value, 2)` redondeando a 2 decimales
   - **XLSX**: Usaba valores directos de la base de datos sin redondeo
   - **CSV**: Usaba valores directos de la base de datos sin redondeo

2. **Valores monetarios almacenados como centavos**:
   - Los valores monetarios se almacenan como enteros (centavos) en la base de datos
   - Ejemplo: 5785 (centavos) = 57.85 (unidades de moneda)
   - Los exports no convertían correctamente de centavos a unidades de moneda

### Ejemplo del Problema:
- PDF mostraba: `1234.56` (convertido y redondeado por Blade)
- XLSX mostraba: `123456` (centavos sin conversión)
- CSV mostraba: `123456` (centavos sin conversión)

## Solución Implementada

### 1. Métodos de Formateo Consistente con Conversión Monetaria

Se implementaron métodos inteligentes que:
- Detectan automáticamente si un campo contiene valores monetarios
- Convierten de centavos a unidades de moneda (÷ 100)
- Aplican formateo consistente a 2 decimales

```php
private function formatNumericValue($value, $decimals = 2, $isMonetary = true)
{
    if (is_numeric($value)) {
        $floatValue = (float) $value;
        
        // Convert from cents to currency units if it's a monetary value
        if ($isMonetary && $floatValue > 0) {
            $floatValue = $floatValue / 100;
        }
        
        return round($floatValue, $decimals);
    }
    return $value;
}

private function isMonetaryField($fieldName)
{
    // Auto-detects monetary fields by name patterns
    return in_array($fieldName, ['total', 'amount', 'price', ...]) ||
           str_contains($fieldName, '_total') || 
           str_contains($fieldName, '_amount');
}
```

### 2. Aplicación Inteligente del Formateo

Se actualizó `collectData()` para aplicar formateo específico:
- **Dashboard Summary**: Forzar conversión monetaria
- **Outstanding**: Forzar conversión monetaria 
- **Cashflow**: Formatear solo arrays monetarios específicos
- **Invoices**: Auto-detección por nombre de campo

### 3. Actualización de Plantilla PDF
Se removieron las llamadas a `number_format()` ya que los valores están pre-formateados

### 4. Formateo XLSX Mejorado
Se agregó formateo de celdas numéricas con estilo `#,##0.00`

## Archivos Modificados

1. **app/Services/DashboardExportService.php**
   - Métodos de formateo con conversión monetaria
   - Auto-detección de campos monetarios
   - Formateo específico por tipo de datos
   - **FIX**: Corregido `getTopOutstandingData()` para usar la misma lógica que el frontend

2. **resources/views/app/pdf/dashboard_export.blade.php**
   - Removido double-formatting
   - Valores pre-procesados y consistentes

## Resultado

### Conversión Correcta de Centavos:
- Base de datos: `5785` (centavos)
- PDF: `57.85` (unidades de moneda)
- XLSX: `57.85` (unidades de moneda con formato `#,##0.00`)
- CSV: `57.85` (unidades de moneda)

### Consistencia Total:
Todos los formatos ahora muestran exactamente los mismos valores con precisión de 2 decimales.

## Beneficios

1. **Conversión Automática**: Centavos → Unidades de moneda
2. **Consistencia**: Mismos valores en todos los formatos
3. **Inteligencia**: Auto-detección de campos monetarios
4. **Robustez**: Manejo seguro de valores nulos y no numéricos
5. **Mantenibilidad**: Fácil agregar nuevos campos monetarios

---

## Fix Adicional: Top Outstanding Invoices

### Problema Identificado
La sección "Top Outstanding Invoices" no mostraba datos en las exportaciones debido a diferencias en la lógica entre el frontend y el export service.

### Diferencias encontradas:
1. **Rango de fechas restrictivo**: Export usaba solo mes actual (sin datos)
2. **Filtros incorrectos**: Usaba `base_due_amount > 0` en lugar de `paid_status`  
3. **Lógica de productos inconsistente**: Cálculo diferente para productos

### Solución Aplicada:
```php
// Antes (problemático):
$query = Invoice::where('base_due_amount', '>', 0)
    ->whereBetween('invoice_date', [$startDate, $endDate]) // solo mes actual
    ->whereCompany();

// Después (corregido):
$baseQuery = Invoice::whereCompany()
    ->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
    ->whereBetween('invoice_date', [$startDate, $endDate]); // año completo por defecto
```

### Beneficios:
- **Datos consistentes**: Misma lógica que el controlador del frontend
- **Rango amplio**: Año completo por defecto asegura datos disponibles
- **Filtros correctos**: Solo facturas realmente pendientes
- **Cálculos precisos**: Lógica de productos corregida 