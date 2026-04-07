import type { RouteRecordRaw } from 'vue-router'

const itemRoutes: RouteRecordRaw[] = [
  {
    path: 'items',
    name: 'items.index',
    component: () => import('./views/ItemIndexView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'view-item',
    },
  },
  {
    path: 'items/create',
    name: 'items.create',
    component: () => import('./views/ItemCreateView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'create-item',
    },
  },
  {
    path: 'items/:id/edit',
    name: 'items.edit',
    component: () => import('./views/ItemCreateView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'edit-item',
    },
  },
]

export default itemRoutes
