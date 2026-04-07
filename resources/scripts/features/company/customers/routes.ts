import type { RouteRecordRaw } from 'vue-router'

const customerRoutes: RouteRecordRaw[] = [
  {
    path: 'customers',
    name: 'customers.index',
    component: () => import('./views/CustomerIndexView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'view-customer',
    },
  },
  {
    path: 'customers/create',
    name: 'customers.create',
    component: () => import('./views/CustomerCreateView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'create-customer',
    },
  },
  {
    path: 'customers/:id/edit',
    name: 'customers.edit',
    component: () => import('./views/CustomerCreateView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'edit-customer',
    },
  },
  {
    path: 'customers/:id/view',
    name: 'customers.view',
    component: () => import('./views/CustomerDetailView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'view-customer',
    },
  },
]

export default customerRoutes
