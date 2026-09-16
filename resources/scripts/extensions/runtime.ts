import { markRaw, shallowRef } from 'vue'
import type { ShallowRef } from 'vue'
import type { RouteMeta, RouteRecordRaw, Router } from 'vue-router'
import { client } from '@/scripts/api/client'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { registerAdditionalMessages } from '@/scripts/plugins/i18n'
import type {
  BootstrapCompletedEvent,
  CompanyChangeEvent,
  ComponentExtensionContribution,
  InvoiceShelfExtensionApi,
  InvoiceShelfExtensionEvents,
  PageChildContribution,
  PageContribution,
  PageRouteMeta,
  SettingsNavigationContribution,
  SettingsPageContribution,
} from './types'

type ComponentSlot =
  | 'headerActions'
  | 'companyLayoutOverlays'
  | 'richEditorToolbarActions'

interface RegisteredComponentContribution extends ComponentExtensionContribution {
  component: ComponentExtensionContribution['component']
}

function comparePriority<T extends { priority?: number; id: string }>(a: T, b: T): number {
  return (a.priority ?? 100) - (b.priority ?? 100) || a.id.localeCompare(b.id)
}

function assertContributionId(id: string): void {
  if (!id.trim()) {
    throw new Error('InvoiceShelf extension contributions require a stable id.')
  }
}

const MODULE_SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

/** Root segment below `modules/{module}` that the host keeps for its own settings route. */
const RESERVED_PAGE_SEGMENT = 'settings'

function assertModuleSlug(module: string): void {
  if (!MODULE_SLUG_PATTERN.test(module)) {
    throw new Error('InvoiceShelf extension pages need a kebab-case module slug.')
  }
}

/**
 * Normalise a module page path so it can never escape the module namespace:
 * relative only, no traversal, no query or hash, no surrounding slashes.
 * An empty result is the index page of the module.
 */
function normalisePagePath(path: string): string {
  if (path.startsWith('/')) {
    throw new Error('InvoiceShelf extension page paths must be relative.')
  }

  if (path.includes('?') || path.includes('#')) {
    throw new Error('InvoiceShelf extension page paths cannot contain a query string or hash.')
  }

  const trimmed = path.replace(/^\/+|\/+$/g, '')

  if (trimmed.split('/').includes('..')) {
    throw new Error('InvoiceShelf extension page paths cannot contain ".." segments.')
  }

  return trimmed
}

/**
 * The host owns `modules/:slug/settings`, so no module page may claim it.
 * Checked against the full path below `modules/{module}`, children included.
 */
function assertUnreservedPagePath(path: string): void {
  if (path === RESERVED_PAGE_SEGMENT || path.startsWith(`${RESERVED_PAGE_SEGMENT}/`)) {
    throw new Error(
      'InvoiceShelf reserves the "settings" segment below modules/{module} for the host settings page.',
    )
  }
}

/**
 * Route meta for a module page. `requiresAuth` is applied after the module's own
 * meta so a module can never opt its page out of the auth guard.
 */
function buildPageMeta(module: string, meta: PageRouteMeta | undefined): RouteMeta {
  return { ...meta, requiresAuth: true, extensionModule: module }
}

/**
 * Host-owned reactive registry. Modules only receive the public API below,
 * never the host's Pinia stores or layout implementation.
 */
export class ExtensionRegistry {
  readonly headerActions = shallowRef<RegisteredComponentContribution[]>([])
  readonly companyLayoutOverlays = shallowRef<RegisteredComponentContribution[]>([])
  readonly richEditorToolbarActions = shallowRef<RegisteredComponentContribution[]>([])
  readonly companySettingsNavigation = shallowRef<SettingsNavigationContribution[]>([])
  readonly adminSettingsNavigation = shallowRef<SettingsNavigationContribution[]>([])

  private readonly teardowns = new Set<() => void>()

  registerComponent(
    slot: ComponentSlot,
    contribution: ComponentExtensionContribution,
  ): () => void {
    assertContributionId(contribution.id)
    const target = this[slot] as ShallowRef<RegisteredComponentContribution[]>
    const entry: RegisteredComponentContribution = {
      ...contribution,
      component: markRaw(contribution.component),
    }

    return this.track(() => {
      target.value = [...target.value.filter((item) => item.id !== entry.id), entry]
        .sort(comparePriority)

      return () => {
        target.value = target.value.filter((item) => item !== entry)
      }
    })
  }

  registerNavigation(
    slot: 'companySettingsNavigation' | 'adminSettingsNavigation',
    contribution: SettingsNavigationContribution,
  ): () => void {
    assertContributionId(contribution.id)
    const target = this[slot] as ShallowRef<SettingsNavigationContribution[]>
    const entry = { ...contribution }

    return this.track(() => {
      target.value = [...target.value.filter((item) => item.id !== entry.id), entry]
        .sort(comparePriority)

      return () => {
        target.value = target.value.filter((item) => item !== entry)
      }
    })
  }

  reset(): void {
    for (const teardown of [...this.teardowns]) {
      teardown()
    }
  }

  trackTeardown(unregister: () => void): () => void {
    return this.track(() => unregister)
  }

  private track(register: () => () => void): () => void {
    const unregister = register()
    let active = true
    const teardown = () => {
      if (!active) return
      active = false
      unregister()
      this.teardowns.delete(teardown)
    }

    this.teardowns.add(teardown)
    return teardown
  }
}

export const extensionRegistry = new ExtensionRegistry()

class ExtensionApi implements InvoiceShelfExtensionApi {
  private readonly listeners = new Map<
    keyof InvoiceShelfExtensionEvents,
    Set<(payload: unknown) => void>
  >()
  private readonly settingsPageTeardowns = new Map<string, () => void>()
  private readonly pageTeardowns = new Map<string, () => void>()
  private readonly pagePaths = new Map<string, string>()

  constructor(readonly router: Router) {}

  readonly client = client

  registerHeaderAction(contribution: ComponentExtensionContribution): () => void {
    return extensionRegistry.registerComponent('headerActions', contribution)
  }

  registerCompanyLayoutOverlay(contribution: ComponentExtensionContribution): () => void {
    return extensionRegistry.registerComponent('companyLayoutOverlays', contribution)
  }

  registerRichEditorToolbarAction(contribution: ComponentExtensionContribution): () => void {
    return extensionRegistry.registerComponent('richEditorToolbarActions', contribution)
  }

  registerCompanySettingsNavigation(contribution: SettingsNavigationContribution): () => void {
    return extensionRegistry.registerNavigation('companySettingsNavigation', contribution)
  }

  registerAdminSettingsNavigation(contribution: SettingsNavigationContribution): () => void {
    return extensionRegistry.registerNavigation('adminSettingsNavigation', contribution)
  }

  registerCompanySettingsPage(contribution: SettingsPageContribution): () => void {
    return this.registerSettingsPage('settings', 'companySettingsNavigation', contribution)
  }

  registerAdminSettingsPage(contribution: SettingsPageContribution): () => void {
    return this.registerSettingsPage('admin.settings', 'adminSettingsNavigation', contribution)
  }

  /**
   * Mount a module-owned full page under `/admin/modules/{module}/{path}`, as a
   * child of the company layout, so it gets the host chrome, the auth guard and
   * the company bootstrap for free. An empty `path` is the module index page at
   * `/admin/modules/{module}`; the `settings` segment is reserved by the host.
   *
   * Route params are handed to the component as props (`props: true`) because a
   * module bundle runs against its own Vue copy and cannot call `useRoute()`.
   *
   * `meta.ability` takes a namespaced module ability id (for example
   * `tasks-projects:view-project`) and is enforced by the existing router guard
   * and by the post-bootstrap re-check in the company layout. `requiresAuth` is
   * forced on every record, so a module cannot opt out of either.
   *
   * The matching PHP side should point `Registry::registerMenu` at
   * `/admin/modules/{module}` so the sidebar entry stays highlighted on every
   * sub-page (the sidebar matches the longest link prefix of the active route).
   *
   * Returns a teardown that removes the route; `reset()` removes it too.
   */
  registerPage(contribution: PageContribution): () => void {
    assertContributionId(contribution.id)
    assertModuleSlug(contribution.module)

    const module = contribution.module
    const path = normalisePagePath(contribution.path)
    assertUnreservedPagePath(path)

    const pageKey = `${module}:${contribution.id}`
    const pathKey = `${module}/${path}`
    const owner = this.pagePaths.get(pathKey)

    if (owner && owner !== pageKey) {
      throw new Error(
        `InvoiceShelf extension page ${pathKey} is already registered by ${owner}.`,
      )
    }

    const routeName = `extension.page.${module}.${contribution.id}`
    const children = this.buildPageChildren(module, routeName, path, contribution.children ?? [])

    // Re-registering the same module + id replaces the previous route.
    this.pageTeardowns.get(pageKey)?.()

    const record: RouteRecordRaw = {
      path: path ? `modules/${module}/${path}` : `modules/${module}`,
      name: routeName,
      props: true,
      component: markRaw(contribution.component),
      meta: buildPageMeta(module, contribution.meta),
      children,
    }

    const removeRoute = this.router.addRoute('admin', record)
    const tracked = extensionRegistry.trackTeardown(() => {
      removeRoute()
      this.pagePaths.delete(pathKey)
      this.pageTeardowns.delete(pageKey)
    })

    this.pageTeardowns.set(pageKey, tracked)
    this.pagePaths.set(pathKey, pageKey)

    return tracked
  }

  addMessages(messages: Record<string, Record<string, unknown>>): void {
    registerAdditionalMessages(messages)
  }

  notify(type: 'success' | 'error' | 'warning' | 'info', message: string): void {
    useNotificationStore().showNotification({ type, message })
  }

  on<EventName extends keyof InvoiceShelfExtensionEvents>(
    event: EventName,
    listener: (payload: InvoiceShelfExtensionEvents[EventName]) => void,
  ): () => void {
    const listeners = this.listeners.get(event) ?? new Set<(payload: unknown) => void>()
    this.listeners.set(event, listeners)
    listeners.add(listener as (payload: unknown) => void)
    return () => listeners.delete(listener as (payload: unknown) => void)
  }

  emit<EventName extends keyof InvoiceShelfExtensionEvents>(
    event: EventName,
    payload: InvoiceShelfExtensionEvents[EventName],
  ): void {
    for (const listener of this.listeners.get(event) ?? []) {
      listener(payload)
    }
  }

  reset(): void {
    extensionRegistry.reset()
    this.settingsPageTeardowns.clear()
    this.pageTeardowns.clear()
    this.pagePaths.clear()
    for (const listeners of this.listeners.values()) {
      listeners.clear()
    }
  }

  /**
   * Build the child route records of a module page. Child paths stay relative to
   * the parent, so `''` is the index child.
   */
  private buildPageChildren(
    module: string,
    parentName: string,
    parentPath: string,
    children: readonly PageChildContribution[],
  ): RouteRecordRaw[] {
    const seenPaths = new Set<string>()

    return children.map((child) => {
      assertContributionId(child.id)

      const childPath = normalisePagePath(child.path)
      assertUnreservedPagePath(parentPath ? `${parentPath}/${childPath}` : childPath)

      if (seenPaths.has(childPath)) {
        throw new Error(
          `InvoiceShelf extension page ${parentName} registers the child path "${childPath}" twice.`,
        )
      }

      seenPaths.add(childPath)

      return {
        path: childPath,
        name: `${parentName}.${child.id}`,
        props: true,
        component: markRaw(child.component),
        meta: buildPageMeta(module, child.meta),
      }
    })
  }

  private registerSettingsPage(
    parentName: string,
    navigationSlot: 'companySettingsNavigation' | 'adminSettingsNavigation',
    contribution: SettingsPageContribution,
  ): () => void {
    assertContributionId(contribution.id)
    if (!contribution.path || contribution.path.startsWith('/')) {
      throw new Error('InvoiceShelf extension settings paths must be relative.')
    }

    const routeName = `extension.${parentName}.${contribution.id}`
    const pageKey = `${parentName}:${contribution.id}`
    this.settingsPageTeardowns.get(pageKey)?.()

    const removeRoute = this.router.addRoute(parentName, {
      path: contribution.path,
      name: routeName,
      component: markRaw(contribution.component),
      meta: contribution.meta,
    })
    const removeNavigation = extensionRegistry.registerNavigation(navigationSlot, {
      id: contribution.id,
      priority: contribution.priority,
      visible: contribution.visible,
      title: contribution.title,
      icon: contribution.icon,
      to: { name: routeName },
    })

    let active = true
    const teardown = () => {
      if (!active) return
      active = false
      removeNavigation()
      removeRoute()
      this.settingsPageTeardowns.delete(pageKey)
    }

    const trackedTeardown = extensionRegistry.trackTeardown(teardown)
    this.settingsPageTeardowns.set(pageKey, trackedTeardown)
    return trackedTeardown
  }
}

let extensionApi: ExtensionApi | null = null

export function createExtensionApi(router: Router): InvoiceShelfExtensionApi {
  extensionApi ??= new ExtensionApi(router)
  return extensionApi
}

export function emitBootstrapCompleted(payload: BootstrapCompletedEvent): void {
  extensionApi?.emit('bootstrap:completed', payload)
}

export function emitCompanyChanging(payload: CompanyChangeEvent): void {
  extensionApi?.emit('company:changing', payload)
}

export function emitCompanyChanged(payload: CompanyChangeEvent): void {
  extensionApi?.emit('company:changed', payload)
}

export function isContributionVisible(contribution: { visible?: () => boolean }): boolean {
  try {
    return contribution.visible?.() ?? true
  } catch (error) {
    console.warn('InvoiceShelf extension visibility predicate failed.', error)
    return false
  }
}

export function extensionItems<T extends { visible?: () => boolean }>(
  items: readonly T[],
): T[] {
  return items.filter(isContributionVisible)
}
