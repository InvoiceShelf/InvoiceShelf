export { useCompanyModulesStore } from './store'
export type {
  CompanyModuleSummary,
  CompanyModulesState,
} from './store'

// Views
export { default as CompanyModulesIndexView } from './views/CompanyModulesIndexView.vue'

// Components
export { default as CompanyModuleCard } from './components/CompanyModuleCard.vue'
export { default as ModuleSettingsModal } from './components/ModuleSettingsModal.vue'
