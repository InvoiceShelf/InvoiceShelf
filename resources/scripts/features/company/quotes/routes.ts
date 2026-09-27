import type { RouteRecordRaw } from 'vue-router'

const QuoteIndexView = () => import('./views/QuoteIndexView.vue')
const QuoteCreateView = () => import('./views/QuoteCreateView.vue')
const QuoteDetailView = () => import('./views/QuoteDetailView.vue')

export const quoteRoutes: RouteRecordRaw[] = [
  {
    path: 'quotes',
    name: 'quotes.index',
    component: QuoteIndexView,
    meta: {
      requiresAuth: true,
      proposalKind: 'quote',
      ability: 'view-quote',
      title: 'quotes.title',
    },
  },
  {
    path: 'quotes/create',
    name: 'quotes.create',
    component: QuoteCreateView,
    meta: {
      requiresAuth: true,
      proposalKind: 'quote',
      ability: 'create-quote',
      title: 'quotes.new_quote',
    },
  },
  {
    path: 'quotes/:id/edit',
    name: 'quotes.edit',
    component: QuoteCreateView,
    meta: {
      requiresAuth: true,
      proposalKind: 'quote',
      ability: 'edit-quote',
      title: 'quotes.edit_quote',
    },
  },
  {
    path: 'quotes/:id/view',
    name: 'quotes.view',
    component: QuoteDetailView,
    meta: {
      requiresAuth: true,
      proposalKind: 'quote',
      ability: 'view-quote',
      title: 'quotes.title',
    },
  },
]
