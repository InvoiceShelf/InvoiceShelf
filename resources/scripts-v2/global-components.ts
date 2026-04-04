import { defineAsyncComponent } from 'vue'
import type { App, Component } from 'vue'

/**
 * Exclude list for components that should be async-loaded or are
 * not needed as global registrations.
 */
const EXCLUDE = new Set([
  'BaseMultiselect',
  'InvoicePublicPage',
  'InvoiceInformationCard',
])

/**
 * Register all base components globally so they can be used in
 * templates without explicit imports.
 */
export function defineGlobalComponents(app: App): void {
  // Eager-load base components (excluding heavy/page-level ones)
  const components: Record<string, { default: Component }> = import.meta.glob(
    [
      './components/base/*.vue',
      '!./components/base/BaseMultiselect.vue',
      '!./components/base/InvoicePublicPage.vue',
      '!./components/base/InvoiceInformationCard.vue',
    ],
    { eager: true }
  )

  for (const [path, definition] of Object.entries(components)) {
    const fileName = path.split('/').pop()
    if (!fileName) continue

    const componentName = fileName.replace(/\.\w+$/, '')
    app.component(componentName, definition.default)
  }

  // Async-load heavier components
  app.component(
    'BaseTable',
    defineAsyncComponent(() => import('./components/table/DataTable.vue'))
  )

  app.component(
    'BaseMultiselect',
    defineAsyncComponent(() => import('./components/base/BaseMultiselect.vue'))
  )

  app.component(
    'BaseEditor',
    defineAsyncComponent(() => import('./components/editor/RichEditor.vue'))
  )
}
