import type { RouteRecordRaw } from 'vue-router'

const EstimateIndexView = () => import('./views/EstimateIndexView.vue')
const EstimateCreateView = () => import('./views/EstimateCreateView.vue')
const EstimateDetailView = () => import('./views/EstimateDetailView.vue')

export const estimateRoutes: RouteRecordRaw[] = [
  {
    path: 'estimates',
    name: 'estimates.index',
    component: EstimateIndexView,
    meta: {
      requiresAuth: true,
      ability: 'view-estimate',
      title: 'estimates.title',
    },
  },
  {
    path: 'estimates/create',
    name: 'estimates.create',
    component: EstimateCreateView,
    meta: {
      requiresAuth: true,
      ability: 'create-estimate',
      title: 'estimates.new_estimate',
    },
  },
  {
    path: 'estimates/:id/edit',
    name: 'estimates.edit',
    component: EstimateCreateView,
    meta: {
      requiresAuth: true,
      ability: 'edit-estimate',
      title: 'estimates.edit_estimate',
    },
  },
  {
    path: 'estimates/:id/view',
    name: 'estimates.view',
    component: EstimateDetailView,
    meta: {
      requiresAuth: true,
      ability: 'view-estimate',
      title: 'estimates.title',
    },
  },
]
