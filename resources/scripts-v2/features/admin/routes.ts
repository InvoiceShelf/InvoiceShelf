import type { RouteRecordRaw } from 'vue-router'

const CompanyLayout = () => import('../../layouts/CompanyLayout.vue')
const AdminDashboardView = () => import('./views/AdminDashboardView.vue')
const AdminCompaniesView = () => import('./views/AdminCompaniesView.vue')
const AdminCompanyEditView = () => import('./views/AdminCompanyEditView.vue')
const AdminUsersView = () => import('./views/AdminUsersView.vue')
const AdminUserEditView = () => import('./views/AdminUserEditView.vue')
const AdminSettingsView = () => import('./views/AdminSettingsView.vue')

export const adminRoutes: RouteRecordRaw[] = [
  {
    path: '/admin/administration',
    component: CompanyLayout,
    meta: {
      requiresAuth: true,
      isSuperAdmin: true,
    },
    children: [
      {
        path: 'dashboard',
        name: 'admin.dashboard',
        component: AdminDashboardView,
        meta: {
          isSuperAdmin: true,
        },
      },
      {
        path: 'companies',
        name: 'admin.companies.index',
        component: AdminCompaniesView,
        meta: {
          isSuperAdmin: true,
        },
      },
      {
        path: 'companies/:id/edit',
        name: 'admin.companies.edit',
        component: AdminCompanyEditView,
        meta: {
          isSuperAdmin: true,
        },
      },
      {
        path: 'users',
        name: 'admin.users.index',
        component: AdminUsersView,
        meta: {
          isSuperAdmin: true,
        },
      },
      {
        path: 'users/:id/edit',
        name: 'admin.users.edit',
        component: AdminUserEditView,
        meta: {
          isSuperAdmin: true,
        },
      },
      {
        path: 'settings',
        name: 'admin.settings',
        component: AdminSettingsView,
        meta: {
          isSuperAdmin: true,
        },
        children: [
          {
            path: 'mail-configuration',
            name: 'admin.settings.mail',
            meta: {
              isSuperAdmin: true,
            },
            // Loaded by settings sub-routes
            component: { template: '<router-view />' },
          },
          {
            path: 'pdf-generation',
            name: 'admin.settings.pdf',
            meta: {
              isSuperAdmin: true,
            },
            component: { template: '<router-view />' },
          },
          {
            path: 'backup',
            name: 'admin.settings.backup',
            meta: {
              isSuperAdmin: true,
            },
            component: { template: '<router-view />' },
          },
          {
            path: 'file-disk',
            name: 'admin.settings.disk',
            meta: {
              isSuperAdmin: true,
            },
            component: { template: '<router-view />' },
          },
          {
            path: 'update-app',
            name: 'admin.settings.update',
            meta: {
              isSuperAdmin: true,
            },
            component: { template: '<router-view />' },
          },
        ],
      },
    ],
  },
]
