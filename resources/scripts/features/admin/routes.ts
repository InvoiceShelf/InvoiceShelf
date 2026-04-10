import type { RouteRecordRaw } from 'vue-router'
import { adminModuleRoutes } from './modules/routes'

const CompanyLayout = () => import('../../layouts/CompanyLayout.vue')
const AdminDashboardView = () => import('./views/AdminDashboardView.vue')
const AdminCompaniesView = () => import('./views/AdminCompaniesView.vue')
const AdminCompanyEditView = () => import('./views/AdminCompanyEditView.vue')
const AdminUsersView = () => import('./views/AdminUsersView.vue')
const AdminUserEditView = () => import('./views/AdminUserEditView.vue')
const AdminSettingsView = () => import('./views/AdminSettingsView.vue')
const AdminMailConfigView = () => import('./views/settings/AdminMailConfigView.vue')
const AdminPdfGenerationView = () => import('./views/settings/AdminPdfGenerationView.vue')
const AdminBackupView = () => import('./views/settings/AdminBackupView.vue')
const AdminFileDiskView = () => import('./views/settings/AdminFileDiskView.vue')
const AdminFontView = () => import('./views/settings/AdminFontView.vue')
const AdminUpdateAppView = () => import('./views/settings/AdminUpdateAppView.vue')
const AdminAppearanceView = () => import('./views/settings/AdminAppearanceView.vue')

export const adminRoutes: RouteRecordRaw[] = [
  {
    path: '/admin/administration',
    component: CompanyLayout,
    meta: {
      requiresAuth: true,
      isSuperAdmin: true,
      usesAdminBootstrap: true,
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
      ...adminModuleRoutes,
      {
        path: 'settings',
        name: 'admin.settings',
        component: AdminSettingsView,
        meta: {
          isSuperAdmin: true,
        },
        children: [
          {
            path: '',
            redirect: 'mail-configuration',
          },
          {
            path: 'mail-configuration',
            name: 'admin.settings.mail',
            meta: {
              isSuperAdmin: true,
            },
            component: AdminMailConfigView,
          },
          {
            path: 'pdf-generation',
            name: 'admin.settings.pdf',
            meta: {
              isSuperAdmin: true,
            },
            component: AdminPdfGenerationView,
          },
          {
            path: 'backup',
            name: 'admin.settings.backup',
            meta: {
              isSuperAdmin: true,
            },
            component: AdminBackupView,
          },
          {
            path: 'file-disk',
            name: 'admin.settings.disk',
            meta: {
              isSuperAdmin: true,
            },
            component: AdminFileDiskView,
          },
          {
            path: 'fonts',
            name: 'admin.settings.fonts',
            meta: {
              isSuperAdmin: true,
            },
            component: AdminFontView,
          },
          {
            path: 'update-app',
            name: 'admin.settings.update',
            meta: {
              isSuperAdmin: true,
            },
            component: AdminUpdateAppView,
          },
          {
            path: 'appearance',
            name: 'admin.settings.appearance',
            meta: {
              isSuperAdmin: true,
            },
            component: AdminAppearanceView,
          },
        ],
      },
    ],
  },
]
