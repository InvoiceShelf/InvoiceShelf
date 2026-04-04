import { defineAsyncComponent } from 'vue'
import type { App, Component } from 'vue'

/**
 * Register all base components globally so they can be used in
 * templates without explicit imports.
 *
 * Eager-loaded components come from `./components/base/*.vue` via
 * Vite's `import.meta.glob`. A handful of heavier components
 * (table, multiselect, editor) are registered as async components
 * to keep the initial bundle small.
 */
export function defineGlobalComponents(app: App): void {
  // Eager-load all single-file base components
  const components: Record<string, { default: Component }> = import.meta.glob(
    './components/base/*.vue',
    { eager: true }
  )

  for (const [path, definition] of Object.entries(components)) {
    const fileName = path.split('/').pop()
    if (!fileName) continue

    const componentName = fileName.replace(/\.\w+$/, '')
    app.component(componentName, definition.default)
  }

  // Async-load heavier components
  const BaseTable = defineAsyncComponent(
    () => import('./components/table/DataTable.vue')
  )

  const BaseMultiselect = defineAsyncComponent(
    () => import('./components/base/BaseMultiselect.vue')
  )

  const BaseEditor = defineAsyncComponent(
    () => import('./components/editor/RichEditor.vue')
  )

  app.component('BaseTable', BaseTable)
  app.component('BaseMultiselect', BaseMultiselect)
  app.component('BaseEditor', BaseEditor)
}
