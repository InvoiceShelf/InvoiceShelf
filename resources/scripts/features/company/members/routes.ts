import type { RouteRecordRaw } from 'vue-router'

const memberRoutes: RouteRecordRaw[] = [
  {
    path: 'members',
    name: 'members.index',
    component: () => import('./views/MemberIndexView.vue'),
    meta: {
      requiresAuth: true,
      isOwner: true,
    },
  },
]

export default memberRoutes
