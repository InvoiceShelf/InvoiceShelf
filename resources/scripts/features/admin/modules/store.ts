import { defineStore } from 'pinia'
import { moduleService } from '../../../api/services/module.service'
import type {
  Module,
} from '../../../types/domain/module'
import type {
  ModuleCheckResponse,
  ModuleDetailResponse,
  ModuleInstallPayload,
} from '../../../api/services/module.service'

// ----------------------------------------------------------------
// Types
// ----------------------------------------------------------------

export interface InstallationStep {
  translationKey: string
  stepUrl: string
  time: string | null
  started: boolean
  completed: boolean
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface ModuleState {
  currentModule: ModuleDetailResponse | null
  modules: Module[]
  apiToken: string | null
  currentUser: {
    api_token: string | null
  }
  marketplaceStatus: {
    authenticated: boolean
    premium: boolean
    invalidToken: boolean
  }
  enableModules: string[]
}

export const useModuleStore = defineStore('modules', {
  state: (): ModuleState => ({
    currentModule: null,
    modules: [],
    apiToken: null,
    currentUser: {
      api_token: null,
    },
    marketplaceStatus: {
      authenticated: false,
      premium: false,
      invalidToken: false,
    },
    enableModules: [],
  }),

  getters: {
    salesTaxUSEnabled: (state): boolean =>
      state.enableModules.includes('SalesTaxUS'),

    installedModules: (state): Module[] =>
      state.modules.filter((m) => m.installed),
  },

  actions: {
    async fetchModules(): Promise<void> {
      const response = await moduleService.list()
      this.modules = response.data
    },

    async fetchModule(slug: string): Promise<ModuleDetailResponse> {
      const response = await moduleService.get(slug)
      this.currentModule = response
      return response
    },

    async checkApiToken(token: string): Promise<ModuleCheckResponse> {
      const response = await moduleService.checkToken(token)
      this.marketplaceStatus = {
        authenticated: response.authenticated ?? false,
        premium: response.premium ?? false,
        invalidToken: response.error === 'invalid_token',
      }
      return response
    },

    setApiToken(token: string | null): void {
      this.apiToken = token
      this.currentUser.api_token = token
    },

    clearMarketplaceStatus(): void {
      this.marketplaceStatus = {
        authenticated: false,
        premium: false,
        invalidToken: false,
      }
    },

    async disableModule(moduleName: string): Promise<{ success: boolean }> {
      return moduleService.disable(moduleName)
    },

    async enableModule(moduleName: string): Promise<{ success: boolean }> {
      return moduleService.enable(moduleName)
    },

    async installModule(
      payload: ModuleInstallPayload,
      onStepUpdate?: (step: InstallationStep) => void,
    ): Promise<boolean> {
      const steps: InstallationStep[] = [
        {
          translationKey: 'modules.download_zip_file',
          stepUrl: '/api/v1/modules/download',
          time: null,
          started: false,
          completed: false,
        },
        {
          translationKey: 'modules.unzipping_package',
          stepUrl: '/api/v1/modules/unzip',
          time: null,
          started: false,
          completed: false,
        },
        {
          translationKey: 'modules.copying_files',
          stepUrl: '/api/v1/modules/copy',
          time: null,
          started: false,
          completed: false,
        },
        {
          translationKey: 'modules.completing_installation',
          stepUrl: '/api/v1/modules/complete',
          time: null,
          started: false,
          completed: false,
        },
      ]

      let path: string | null = null

      for (const step of steps) {
        step.started = true
        onStepUpdate?.(step)

        try {
          const stepFns: Record<string, () => Promise<Record<string, unknown>>> = {
            '/api/v1/modules/download': () =>
              moduleService.download({
                ...payload,
                path: path ?? undefined,
              }) as Promise<Record<string, unknown>>,
            '/api/v1/modules/unzip': () =>
              moduleService.unzip({
                ...payload,
                path: path ?? undefined,
              }) as Promise<Record<string, unknown>>,
            '/api/v1/modules/copy': () =>
              moduleService.copy({
                ...payload,
                path: path ?? undefined,
              }) as Promise<Record<string, unknown>>,
            '/api/v1/modules/complete': () =>
              moduleService.complete({
                ...payload,
                path: path ?? undefined,
              }) as Promise<Record<string, unknown>>,
          }

          const result = await stepFns[step.stepUrl]()
          step.completed = true
          onStepUpdate?.(step)

          if ((result as Record<string, unknown>).path) {
            path = (result as Record<string, unknown>).path as string
          }

          if (!(result as Record<string, unknown>).success) {
            const message = (result as Record<string, unknown>).error
            if (typeof message === 'string') {
              const { useNotificationStore } = await import('@/scripts/stores/notification.store')
              useNotificationStore().showNotification({
                type: 'error',
                message,
              })
            }
            return false
          }
        } catch (err: unknown) {
          step.completed = true
          onStepUpdate?.(step)
          const { useNotificationStore } = await import('@/scripts/stores/notification.store')
          useNotificationStore().showNotification({
            type: 'error',
            message: err instanceof Error ? err.message : 'Module installation failed',
          })
          return false
        }
      }

      return true
    },
  },
})

export type ModuleStore = ReturnType<typeof useModuleStore>
