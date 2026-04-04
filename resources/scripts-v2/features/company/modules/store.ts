import { defineStore } from 'pinia'
import { moduleService } from '../../../api/services/module.service'
import type {
  Module,
  ModuleReview,
  ModuleFaq,
  ModuleLink,
  ModuleScreenshot,
} from '../../../types/domain/module'

// ----------------------------------------------------------------
// Types
// ----------------------------------------------------------------

export interface ModuleDetailMeta {
  modules: Module[]
}

export interface ModuleDetailResponse {
  data: Module
  meta: ModuleDetailMeta
}

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
      const data = response as unknown as ModuleDetailResponse

      if ((data as Record<string, unknown>).error === 'invalid_token') {
        this.currentModule = null
        this.modules = []
        this.apiToken = null
        this.currentUser.api_token = null
        return data
      }

      this.currentModule = data
      return data
    },

    async checkApiToken(token: string): Promise<{ success: boolean; error?: string }> {
      const response = await moduleService.checkToken(token)
      return {
        success: response.success ?? false,
        error: response.error,
      }
    },

    async disableModule(moduleName: string): Promise<{ success: boolean }> {
      return moduleService.disable(moduleName)
    },

    async enableModule(moduleName: string): Promise<{ success: boolean }> {
      return moduleService.enable(moduleName)
    },

    async installModule(
      moduleName: string,
      version: string,
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
              moduleService.download({ module: moduleName, version, path: path ?? undefined } as never) as Promise<Record<string, unknown>>,
            '/api/v1/modules/unzip': () =>
              moduleService.unzip({ module: moduleName, version, path: path ?? undefined } as never) as Promise<Record<string, unknown>>,
            '/api/v1/modules/copy': () =>
              moduleService.copy({ module: moduleName, version, path: path ?? undefined } as never) as Promise<Record<string, unknown>>,
            '/api/v1/modules/complete': () =>
              moduleService.complete({ module: moduleName, version, path: path ?? undefined } as never) as Promise<Record<string, unknown>>,
          }

          const result = await stepFns[step.stepUrl]()
          step.completed = true
          onStepUpdate?.(step)

          if ((result as Record<string, unknown>).path) {
            path = (result as Record<string, unknown>).path as string
          }

          if (!(result as Record<string, unknown>).success) {
            return false
          }
        } catch {
          step.completed = true
          onStepUpdate?.(step)
          return false
        }
      }

      return true
    },
  },
})

export type ModuleStore = ReturnType<typeof useModuleStore>
