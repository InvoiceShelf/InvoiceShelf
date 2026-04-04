import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    ability?: string
    isOwner?: boolean
    isSuperAdmin?: boolean
    redirectIfAuthenticated?: boolean
    isInstallation?: boolean
    title?: string
  }
}
