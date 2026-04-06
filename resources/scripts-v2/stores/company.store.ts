import { defineStore } from 'pinia'
import { ref } from 'vue'
import { companyService } from '@v2/api/services/company.service'
import type {
  UpdateCompanyPayload,
  CompanySettingsPayload,
  CreateCompanyPayload,
} from '@v2/api/services/company.service'
import { useNotificationStore } from './notification.store'
import { handleApiError } from '../utils/error-handling'
import * as localStore from '../utils/local-storage'
import type { Company } from '@v2/types/domain/company'
import type { Currency } from '@v2/types/domain/currency'
import type { ApiResponse } from '@v2/types/api'

export const useCompanyStore = defineStore('company', () => {
  // State
  const companies = ref<Company[]>([])
  const selectedCompany = ref<Company | null>(null)
  const selectedCompanySettings = ref<Record<string, string>>({})
  const selectedCompanyCurrency = ref<Currency | null>(null)
  const isAdminMode = ref<boolean>(localStore.getBoolean('isAdminMode'))
  const defaultCurrency = ref<Currency | null>(null)

  // Actions
  function setSelectedCompany(data: Company | null): void {
    if (data) {
      localStore.set('selectedCompany', data.id)
      localStore.remove('isAdminMode')
      isAdminMode.value = false
    } else {
      localStore.remove('selectedCompany')
    }
    selectedCompany.value = data
  }

  function setAdminMode(enabled: boolean): void {
    isAdminMode.value = enabled
    if (enabled) {
      localStore.set('isAdminMode', true)
      localStore.remove('selectedCompany')
      selectedCompany.value = null
    } else {
      localStore.remove('isAdminMode')
    }
  }

  async function fetchBasicMailConfig(): Promise<Record<string, unknown>> {
    try {
      return await companyService.getMailConfig()
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateCompany(data: UpdateCompanyPayload): Promise<ApiResponse<Company>> {
    try {
      const response = await companyService.update(data)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.company_info.updated_message',
      })

      selectedCompany.value = response.data
      const companyIndex = companies.value.findIndex(
        (company) => company.unique_hash === response.data.unique_hash
      )
      if (companyIndex !== -1) {
        companies.value[companyIndex] = response.data
      }

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateCompanyLogo(data: FormData): Promise<ApiResponse<Company>> {
    try {
      return await companyService.uploadLogo(data)
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function addNewCompany(data: CreateCompanyPayload): Promise<ApiResponse<Company>> {
    try {
      const response = await companyService.create(data)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'company_switcher.created_message',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchCompany(): Promise<Company> {
    try {
      const response = await companyService.listUserCompanies()
      return response.data[0]
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchUserCompanies(): Promise<ApiResponse<Company[]>> {
    try {
      return await companyService.listUserCompanies()
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchCompanySettings(settings?: string[]): Promise<Record<string, string>> {
    try {
      return await companyService.getSettings(settings)
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateCompanySettings(params: {
    data: CompanySettingsPayload
    message?: string
  }): Promise<void> {
    try {
      await companyService.updateSettings(params.data)

      Object.assign(
        selectedCompanySettings.value,
        params.data.settings
      )

      if (params.message) {
        const notificationStore = useNotificationStore()
        notificationStore.showNotification({
          type: 'success',
          message: params.message,
        })
      }
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function deleteCompany(data: { id: number }): Promise<void> {
    try {
      await companyService.delete(data)
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  function setDefaultCurrency(data: { currency: Currency }): void {
    defaultCurrency.value = data.currency
  }

  return {
    companies,
    selectedCompany,
    selectedCompanySettings,
    selectedCompanyCurrency,
    isAdminMode,
    defaultCurrency,
    setSelectedCompany,
    setAdminMode,
    fetchBasicMailConfig,
    updateCompany,
    updateCompanyLogo,
    addNewCompany,
    fetchCompany,
    fetchUserCompanies,
    fetchCompanySettings,
    updateCompanySettings,
    deleteCompany,
    setDefaultCurrency,
  }
})
