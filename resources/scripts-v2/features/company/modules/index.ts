export { moduleRoutes } from './routes'

export { useModuleStore } from './store'
export type {
  ModuleState,
  ModuleStore,
  ModuleDetailResponse,
  ModuleDetailMeta,
  InstallationStep,
} from './store'

// Views
export { default as ModuleIndexView } from './views/ModuleIndexView.vue'
export { default as ModuleDetailView } from './views/ModuleDetailView.vue'

// Components
export { default as ModuleCard } from './components/ModuleCard.vue'
