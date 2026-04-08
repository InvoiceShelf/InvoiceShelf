export { moduleRoutes } from './routes'

export { useCompanyModulesStore } from './store'
export type {
  CompanyModuleSummary,
  CompanyModulesState,
} from './store'

// Views
export { default as CompanyModulesIndexView } from './views/CompanyModulesIndexView.vue'
export { default as ModuleSettingsView } from './views/ModuleSettingsView.vue'

// Components
export { default as CompanyModuleCard } from './components/CompanyModuleCard.vue'
