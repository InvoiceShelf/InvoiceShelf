import type { NavigationGuardWithThis, RouteLocationNormalized } from 'vue-router'
import { useUserStore } from '@v2/stores/user.store'
import { useGlobalStore } from '@v2/stores/global.store'
import { useCompanyStore } from '@v2/stores/company.store'

/**
 * Main authentication and authorization guard.
 *
 * Handles:
 * - Redirecting to the no-company view when no company is selected
 *   (unless in admin mode or the user is a super admin visiting a
 *   super-admin-only route).
 * - Ability-based access control: redirects to account settings when
 *   the current user lacks the required ability.
 * - Super admin route protection: redirects non-super-admins to the
 *   dashboard.
 * - Owner route protection: redirects non-owners to the dashboard.
 */
export const authGuard: NavigationGuardWithThis<undefined> = (
  to: RouteLocationNormalized
) => {
  const userStore = useUserStore()
  const globalStore = useGlobalStore()
  const companyStore = useCompanyStore()

  const { isAppLoaded } = globalStore
  const ability = to.meta.ability

  // Guard 1: no company selected -> redirect to no-company view
  // Skip if the target IS the no-company view, or if we are in admin
  // mode, or if the route is super-admin-only and the user qualifies.
  if (isAppLoaded && to.meta.requiresAuth && to.name !== 'no.company') {
    const isSuperAdminRoute =
      to.meta.isSuperAdmin === true &&
      currentUserIsSuperAdmin(userStore)

    if (
      !companyStore.selectedCompany &&
      !companyStore.isAdminMode &&
      !isSuperAdminRoute
    ) {
      return { name: 'no.company' }
    }
  }

  // Guard 2: ability check
  if (ability && isAppLoaded && to.meta.requiresAuth) {
    if (!userStore.hasAbilities(ability)) {
      return { name: 'settings.account' }
    }
    return
  }

  // Guard 3: super admin check
  if (to.meta.isSuperAdmin && isAppLoaded) {
    if (!currentUserIsSuperAdmin(userStore)) {
      return { name: 'dashboard' }
    }
    return
  }

  // Guard 4: owner check
  if (to.meta.isOwner && isAppLoaded) {
    if (!currentUserIsOwner(userStore)) {
      return { name: 'dashboard' }
    }
    return
  }
}

// ---- helpers ----

function currentUserIsSuperAdmin(
  userStore: ReturnType<typeof useUserStore>
): boolean {
  return userStore.currentUser?.is_super_admin ?? false
}

function currentUserIsOwner(
  userStore: ReturnType<typeof useUserStore>
): boolean {
  return userStore.currentUser?.is_owner ?? false
}
