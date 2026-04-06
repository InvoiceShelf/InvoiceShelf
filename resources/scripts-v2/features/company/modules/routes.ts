import type { RouteRecordRaw } from 'vue-router'

const ModuleIndexView = () => import('./views/ModuleIndexView.vue')
const ModuleDetailView = () => import('./views/ModuleDetailView.vue')

export const moduleRoutes: RouteRecordRaw[] = [
  {
    path: 'modules',
    name: 'modules.index',
    component: ModuleIndexView,
    meta: {
      requiresAuth: true,
      ability: 'manage-module',
      title: 'modules.title',
    },
  },
  {
    path: 'modules/:slug',
    name: 'modules.view',
    component: ModuleDetailView,
    meta: {
      requiresAuth: true,
      ability: 'manage-module',
      title: 'modules.title',
    },
  },
]
