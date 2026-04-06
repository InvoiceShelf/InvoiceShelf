import type { RouteRecordRaw } from 'vue-router'

const ExpenseIndexView = () => import('./views/ExpenseIndexView.vue')
const ExpenseCreateView = () => import('./views/ExpenseCreateView.vue')

export const expenseRoutes: RouteRecordRaw[] = [
  {
    path: 'expenses',
    name: 'expenses.index',
    component: ExpenseIndexView,
    meta: {
      requiresAuth: true,
      ability: 'view-expense',
      title: 'expenses.title',
    },
  },
  {
    path: 'expenses/create',
    name: 'expenses.create',
    component: ExpenseCreateView,
    meta: {
      requiresAuth: true,
      ability: 'create-expense',
      title: 'expenses.new_expense',
    },
  },
  {
    path: 'expenses/:id/edit',
    name: 'expenses.edit',
    component: ExpenseCreateView,
    meta: {
      requiresAuth: true,
      ability: 'edit-expense',
      title: 'expenses.edit_expense',
    },
  },
]
