import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    ability?: string | string[]
    isOwner?: boolean
    isSuperAdmin?: boolean
    usesAdminBootstrap?: boolean
    redirectIfAuthenticated?: boolean
    isCustomerPortal?: boolean
    customerPortalGuest?: boolean
    isInstallation?: boolean
    title?: string
    /** Set by extensions.registerPage: the module slug that owns the route. */
    extensionModule?: string
  }
}
