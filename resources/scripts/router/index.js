import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'

//admin routes
import AdminRoutes from '@/scripts/admin/admin-router'
//  Customers routes
import CustomerRoutes from '@/scripts/customer/customer-router'
//Payment Routes

let routes = []
routes = routes.concat(AdminRoutes, CustomerRoutes)

const router = createRouter({
  history: createWebHistory(),
  linkActiveClass: 'active',
  routes,
})

router.beforeEach((to) => {
  const userStore = useUserStore()
  const globalStore = useGlobalStore()
  const companyStore = useCompanyStore()
  let ability = to.meta.ability
  const { isAppLoaded } = globalStore

  if (isAppLoaded && to.meta.requiresAuth && to.name !== 'no.company') {
    if (!companyStore.selectedCompany && !(to.meta.isSuperAdmin && userStore.currentUser?.is_super_admin)) {
      return { name: 'no.company' }
    }
  }

  if (ability && isAppLoaded && to.meta.requiresAuth) {
    if (!userStore.hasAbilities(ability)) {
      return { name: 'account.settings' }
    }
  } else if (to.meta.isSuperAdmin && isAppLoaded) {
    if (!userStore.currentUser.is_super_admin) {
      return { name: 'dashboard' }
    }
  } else if (to.meta.isOwner && isAppLoaded) {
    if (!userStore.currentUser.is_owner) {
      return { name: 'dashboard' }
    }
  }
})

export default router
