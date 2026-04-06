import type { RouteRecordRaw } from 'vue-router'

const reportRoutes: RouteRecordRaw[] = [
  {
    path: 'reports',
    name: 'reports',
    component: () => import('./views/ReportsLayoutView.vue'),
    meta: {
      requiresAuth: true,
      ability: 'view-financial-reports',
    },
  },
]

export default reportRoutes
