import type { RouteRecordRaw } from 'vue-router'

const PaymentIndexView = () => import('./views/PaymentIndexView.vue')
const PaymentCreateView = () => import('./views/PaymentCreateView.vue')
const PaymentDetailView = () => import('./views/PaymentDetailView.vue')

export const paymentRoutes: RouteRecordRaw[] = [
  {
    path: 'payments',
    name: 'payments.index',
    component: PaymentIndexView,
    meta: {
      ability: 'view-payment',
      title: 'payments.title',
    },
  },
  {
    path: 'payments/create',
    name: 'payments.create',
    component: PaymentCreateView,
    meta: {
      ability: 'create-payment',
      title: 'payments.new_payment',
    },
  },
  {
    path: 'payments/:id/edit',
    name: 'payments.edit',
    component: PaymentCreateView,
    meta: {
      ability: 'edit-payment',
      title: 'payments.edit_payment',
    },
  },
  {
    path: 'payments/:id/view',
    name: 'payments.view',
    component: PaymentDetailView,
    meta: {
      ability: 'view-payment',
      title: 'payments.title',
    },
  },
  {
    path: 'payments/:id/create',
    name: 'payments.create-from-invoice',
    component: PaymentCreateView,
    meta: {
      ability: 'create-payment',
      title: 'payments.new_payment',
    },
  },
]
