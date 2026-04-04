import type { RouteRecordRaw } from 'vue-router'

const dashboardRoutes: RouteRecordRaw[] = [
  {
    path: 'dashboard',
    name: 'dashboard',
    component: () => import('./views/DashboardView.vue'),
    meta: {
      ability: 'dashboard',
    },
  },
]

export default dashboardRoutes
