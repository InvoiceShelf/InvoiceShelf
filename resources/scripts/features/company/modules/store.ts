import { defineStore } from 'pinia'
import { companyModulesService } from '@/scripts/api/services/companyModules.service'

export interface CompanyModuleSummary {
  slug: string
  name: string
  version: string
  has_settings: boolean
  menu: { title: string, link: string, icon: string } | null
}

export interface CompanyModulesState {
  modules: CompanyModuleSummary[]
  isFetching: boolean
}

export const useCompanyModulesStore = defineStore('company-modules', {
  state: (): CompanyModulesState => ({
    modules: [],
    isFetching: false,
  }),

  actions: {
    async fetchModules(): Promise<void> {
      this.isFetching = true
      try {
        const response = await companyModulesService.list()
        this.modules = response.data
      } finally {
        this.isFetching = false
      }
    },
  },
})
