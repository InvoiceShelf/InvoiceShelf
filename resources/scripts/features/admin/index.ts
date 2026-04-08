export { adminRoutes } from './routes'

export { useAdminStore } from './stores/admin.store'
export type {
  AdminDashboardData,
  FetchCompaniesParams,
  FetchUsersParams,
  UpdateCompanyData,
  UpdateUserData,
} from './stores/admin.store'

export { default as AdminDashboardView } from './views/AdminDashboardView.vue'
export { default as AdminCompaniesView } from './views/AdminCompaniesView.vue'
export { default as AdminCompanyEditView } from './views/AdminCompanyEditView.vue'
export { default as AdminUsersView } from './views/AdminUsersView.vue'
export { default as AdminUserEditView } from './views/AdminUserEditView.vue'
export { default as AdminSettingsView } from './views/AdminSettingsView.vue'

export { default as AdminCompanyDropdown } from './components/AdminCompanyDropdown.vue'
export { default as AdminUserDropdown } from './components/AdminUserDropdown.vue'

// Modules (super-admin marketplace browser)
export {
  adminModuleRoutes,
  useModuleStore,
  ModuleIndexView,
  ModuleDetailView,
  ModuleCard,
} from './modules'
export type {
  ModuleState,
  ModuleStore,
  ModuleDetailResponse,
  ModuleDetailMeta,
  InstallationStep,
} from './modules'
