import type { RouteRecordRaw } from 'vue-router'

const NotificationsIndexView = () => import('./views/NotificationsIndexView.vue')

/** Everyone signed in has notices, so the page needs no ability. */
export const notificationRoutes: RouteRecordRaw[] = [
  {
    path: 'notifications',
    name: 'notifications.index',
    component: NotificationsIndexView,
    meta: {
      requiresAuth: true,
      title: 'inbox.title',
    },
  },
]
