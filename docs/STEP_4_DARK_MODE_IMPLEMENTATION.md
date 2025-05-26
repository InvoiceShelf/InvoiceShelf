# Paso 4 - Implementación de Dark Mode

## Resumen
Implementación completa del modo oscuro con toggle y persistencia para InvoiceShelf, incluyendo configuración de Tailwind CSS, store de tema, componente toggle y integración en layouts.

## Configuración de Tailwind CSS

### 1. Habilitación de Dark Mode
**Archivo:** `tailwind.config.js`

**Cambios:**
- Agregado `darkMode: 'class'` para habilitar el modo oscuro basado en clases CSS
- Permite el control programático del tema mediante la clase `dark` en el elemento HTML

## Store de Tema

### 1. Theme Store
**Ubicación:** `resources/scripts/stores/theme.js`

**Características:**
- Store Pinia para manejo centralizado del estado del tema
- Persistencia en localStorage con clave `invoiceshelf_dark_mode`
- Detección automática de preferencias del sistema
- Aplicación automática de clases CSS al documento

**Estado:**
- `isDarkMode`: Boolean que indica si el modo oscuro está activo
- `persistKey`: Clave para localStorage

**Getters:**
- `currentTheme`: Retorna 'dark' o 'light'
- `oppositeTheme`: Retorna el tema opuesto

**Acciones:**
- `toggleDarkMode()`: Alterna entre modos
- `setDarkMode(enabled)`: Establece modo específico
- `applyTheme()`: Aplica clases CSS al documento
- `persistTheme()`: Guarda preferencia en localStorage
- `loadTheme()`: Carga preferencia desde localStorage
- `getSystemPreference()`: Detecta preferencia del sistema
- `initialize()`: Inicializa el store y escucha cambios del sistema
- `resetToSystemPreference()`: Resetea a preferencia del sistema

## Componente Dark Mode Toggle

### 1. DarkModeToggle.vue
**Ubicación:** `resources/scripts/components/theme/DarkModeToggle.vue`

**Características:**
- Componente Vue 3 reutilizable con Composition API
- Switch toggle con iconos animados (Sol/Luna)
- Indicador de tema actual con badge
- Estados de loading opcionales
- Configuración flexible de etiquetas y descripciones

**Props:**
- `showLabel` (Boolean): Mostrar etiqueta y descripción
- `showDescription` (Boolean): Mostrar texto descriptivo
- `loading` (Boolean): Estado de carga
- `id` (String): ID único para el switch

**Eventos:**
- `change`: Emitido cuando cambia el estado del tema

**Iconos:**
- `SunIcon`: Modo claro (amarillo)
- `MoonIcon`: Modo oscuro (azul)
- Transiciones suaves entre iconos

## Integración en Layouts

### 1. Layout Admin
**Archivo:** `resources/scripts/admin/layouts/LayoutBasic.vue`

**Cambios:**
- Importación del `useThemeStore`
- Inicialización del theme store en `onMounted`
- Configuración automática al cargar la aplicación

### 2. Layout Customer
**Archivo:** `resources/scripts/customer/layouts/LayoutBasic.vue`

**Cambios:**
- Importación del `useThemeStore`
- Inicialización del theme store en función `loadData`
- Configuración automática al cargar la aplicación

### 3. Header Admin
**Archivo:** `resources/scripts/admin/layouts/partials/TheSiteHeader.vue`

**Cambios:**
- Agregado toggle de dark mode en la barra de navegación
- Posicionado entre CompanySwitcher y dropdown de usuario
- Estilo consistente con otros elementos del header
- Fondo semi-transparente con hover effects

### 4. Header Customer
**Archivo:** `resources/scripts/customer/layouts/partials/TheSiteHeader.vue`

**Cambios:**
- Agregado toggle de dark mode antes del dropdown de perfil
- Integración limpia con el diseño existente
- Responsive design mantenido

## Soporte de Dark Mode en Componentes

### 1. ActiveFilter.vue
**Actualizaciones aplicadas:**
- `bg-white dark:bg-gray-800`: Fondo del contenedor
- `border-gray-200 dark:border-gray-700`: Bordes
- `text-gray-900 dark:text-gray-100`: Texto principal
- `text-gray-500 dark:text-gray-400`: Texto secundario
- `bg-primary-100 dark:bg-primary-900`: Badge de estado activo
- `text-primary-800 dark:text-primary-200`: Texto del badge

## Traducciones

### 1. Archivo de Idioma
**Ubicación:** `lang/en.json`

**Nuevas traducciones:**
```json
"theme": {
  "dark_mode": {
    "label": "Dark Mode",
    "description": "Toggle between light and dark themes",
    "dark": "Dark",
    "light": "Light"
  }
}
```

## Características Técnicas

### 1. Persistencia
- Preferencias guardadas en localStorage
- Claves separadas para admin y customer (si fuera necesario)
- Carga automática al inicializar la aplicación
- Fallback a preferencias del sistema

### 2. Detección del Sistema
- Escucha cambios en `prefers-color-scheme`
- Actualización automática si no hay preferencia guardada
- Respeta preferencias explícitas del usuario

### 3. Aplicación de Tema
- Manipulación de clases CSS en `document.documentElement`
- Clase `dark` agregada/removida dinámicamente
- Transiciones suaves entre temas

### 4. Accesibilidad
- Labels apropiados para screen readers
- Focus states bien definidos
- Contraste adecuado en ambos temas
- Indicadores visuales claros

## Testing

### 1. Tests Existentes
- Todos los tests del filtro activo siguen pasando
- No hay regresiones en funcionalidad existente
- Compatibilidad mantenida con componentes base

### 2. Verificación Manual
- Toggle funciona correctamente en ambos portales
- Persistencia funciona entre sesiones
- Iconos cambian apropiadamente
- Transiciones son suaves

## Notas de Implementación

1. **Compatibilidad**: Totalmente compatible con el sistema existente
2. **Performance**: Mínimo impacto en rendimiento
3. **Mantenibilidad**: Código bien estructurado y documentado
4. **Escalabilidad**: Fácil agregar soporte a más componentes
5. **UX**: Experiencia de usuario intuitiva y consistente

## Próximos Pasos

Para completar el soporte de dark mode:
1. Agregar clases dark mode a más componentes base
2. Actualizar componentes de formularios
3. Revisar gráficos y charts
4. Testear en diferentes navegadores
5. Optimizar transiciones y animaciones

## Archivos Modificados

### Nuevos Archivos:
- `resources/scripts/stores/theme.js`
- `resources/scripts/components/theme/DarkModeToggle.vue`
- `docs/STEP_4_DARK_MODE_IMPLEMENTATION.md`

### Archivos Modificados:
- `tailwind.config.js`
- `lang/en.json`
- `resources/scripts/admin/layouts/LayoutBasic.vue`
- `resources/scripts/customer/layouts/LayoutBasic.vue`
- `resources/scripts/admin/layouts/partials/TheSiteHeader.vue`
- `resources/scripts/customer/layouts/partials/TheSiteHeader.vue`
- `resources/scripts/components/dashboard/ActiveFilter.vue` 