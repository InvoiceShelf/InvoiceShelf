import type { RouteRecordRaw } from 'vue-router'

const customerRoutes: RouteRecordRaw[] = [
  {
    path: 'customers',
    name: 'customers.index',
    component: () => import('./views/CustomerIndexView.vue'),
    meta: {
      ability: 'view-customer',
    },
  },
  {
    path: 'customers/create',
    name: 'customers.create',
    component: () => import('./views/CustomerCreateView.vue'),
    meta: {
      ability: 'create-customer',
    },
  },
  {
    path: 'customers/:id/edit',
    name: 'customers.edit',
    component: () => import('./views/CustomerCreateView.vue'),
    meta: {
      ability: 'edit-customer',
    },
  },
  {
    path: 'customers/:id/view',
    name: 'customers.view',
    component: () => import('./views/CustomerDetailView.vue'),
    meta: {
      ability: 'view-customer',
    },
  },
]

export default customerRoutes
