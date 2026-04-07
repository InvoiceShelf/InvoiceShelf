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

  // ---------------------------------------------------------------------------
  // Alias registrations: map old Base* names to new v2 components so that
  // templates using the legacy names continue to resolve correctly.
  // ---------------------------------------------------------------------------

  // Form
  const FormGroup = () => import('./components/form/FormGroup.vue')
  const FormGrid = () => import('./components/form/FormGrid.vue')
  const SwitchSection = () => import('./components/form/SwitchSection.vue')

  app.component('BaseInputGroup', defineAsyncComponent(FormGroup))
  app.component('BaseInputGrid', defineAsyncComponent(FormGrid))
  app.component('BaseSwitchSection', defineAsyncComponent(SwitchSection))

  // Layout
  const Page = () => import('./components/layout/Page.vue')
  const PageHeader = () => import('./components/layout/PageHeader.vue')
  const Breadcrumb = () => import('./components/layout/Breadcrumb.vue')
  const BreadcrumbItem = () => import('./components/layout/BreadcrumbItem.vue')
  const FilterWrapper = () => import('./components/layout/FilterWrapper.vue')
  const EmptyPlaceholder = () => import('./components/layout/EmptyPlaceholder.vue')
  const ContentPlaceholder = () => import('./components/layout/ContentPlaceholder.vue')
  const ContentPlaceholderBox = () => import('./components/layout/ContentPlaceholderBox.vue')
  const ContentPlaceholderText = () => import('./components/layout/ContentPlaceholderText.vue')
  const ContentPlaceholderHeading = () => import('./components/layout/ContentPlaceholderHeading.vue')
  app.component('BasePage', defineAsyncComponent(Page))
  app.component('BasePageHeader', defineAsyncComponent(PageHeader))
  app.component('BaseBreadcrumb', defineAsyncComponent(Breadcrumb))
  app.component('BaseBreadcrumbItem', defineAsyncComponent(BreadcrumbItem))
  app.component('BaseFilterWrapper', defineAsyncComponent(FilterWrapper))
  app.component('BaseEmptyPlaceholder', defineAsyncComponent(EmptyPlaceholder))
  app.component('BaseContentPlaceholders', defineAsyncComponent(ContentPlaceholder))
  app.component('BaseContentPlaceholdersBox', defineAsyncComponent(ContentPlaceholderBox))
  app.component('BaseContentPlaceholdersText', defineAsyncComponent(ContentPlaceholderText))
  app.component('BaseContentPlaceholdersHeading', defineAsyncComponent(ContentPlaceholderHeading))

  // Table
  const TablePagination = () => import('./components/table/TablePagination.vue')

  app.component('BaseTablePagination', defineAsyncComponent(TablePagination))

  // Notifications
  const NotificationRoot = () => import('./components/notifications/NotificationRoot.vue')
  const NotificationItem = () => import('./components/notifications/NotificationItem.vue')

  app.component('NotificationRoot', defineAsyncComponent(NotificationRoot))
  app.component('NotificationItem', defineAsyncComponent(NotificationItem))

  // Status badge aliases (map old Base* prefix names to the already eager-loaded
  // components from ./components/base/*.vue)
  const invoiceStatusBadge = components['./components/base/InvoiceStatusBadge.vue']
  const estimateStatusBadge = components['./components/base/EstimateStatusBadge.vue']
  const paidStatusBadge = components['./components/base/PaidStatusBadge.vue']
  const recurringInvoiceStatusBadge = components['./components/base/RecurringInvoiceStatusBadge.vue']

  if (invoiceStatusBadge) app.component('BaseInvoiceStatusBadge', invoiceStatusBadge.default)
  if (estimateStatusBadge) app.component('BaseEstimateStatusBadge', estimateStatusBadge.default)
  if (paidStatusBadge) app.component('BasePaidStatusBadge', paidStatusBadge.default)
  if (recurringInvoiceStatusBadge) app.component('BaseRecurringInvoiceStatusBadge', recurringInvoiceStatusBadge.default)
}
