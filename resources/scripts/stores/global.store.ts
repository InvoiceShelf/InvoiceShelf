import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import groupBy from 'lodash/groupBy'
import { bootstrapService } from '@/scripts/api/services/bootstrap.service'
import type { MenuItem, ModuleMenuItem, BootstrapResponse } from '@/scripts/api/services/bootstrap.service'
import { settingService } from '@/scripts/api/services/setting.service'
import type {
  DateFormat,
  TimeFormat,
  ConfigResponse,
  GlobalSettingsPayload,
  NumberPlaceholdersParams,
  NumberPlaceholder,
} from '@/scripts/api/services/setting.service'
import { useCompanyStore } from './company.store'
import { useUserStore } from './user.store'
import { useNotificationStore } from './notification.store'
import { handleApiError } from '../utils/error-handling'
import * as localStore from '../utils/local-storage'
import type { Currency } from '@/scripts/types/domain/currency'
import type { Country } from '@/scripts/types/domain/customer'

export const useGlobalStore = defineStore('global', () => {
  // State
  const config = ref<Record<string, unknown> | null>(null)
  const globalSettings = ref<Record<string, string> | null>(null)

  const timeZones = ref<string[]>([])
  const dateFormats = ref<DateFormat[]>([])
  const timeFormats = ref<TimeFormat[]>([])
  const currencies = ref<Currency[]>([])
  const countries = ref<Country[]>([])
  const languages = ref<Array<{ code: string; name: string }>>([])
  const fiscalYears = ref<Array<{ key: string; value: string }>>([])

  const mainMenu = ref<MenuItem[]>([])
  const settingMenu = ref<MenuItem[]>([])
  const moduleMenu = ref<ModuleMenuItem[]>([])

  const isAppLoaded = ref<boolean>(false)
  const isSidebarOpen = ref<boolean>(false)
  const isSidebarCollapsed = ref<boolean>(localStore.getBoolean('sidebarCollapsed'))
  const areCurrenciesLoading = ref<boolean>(false)

  const downloadReport = ref<(() => void) | null>(null)

  // Getters
  const menuGroups = computed<MenuItem[][]>(() => {
    return Object.values(groupBy(mainMenu.value, 'group'))
  })

  const hasActiveModules = computed<boolean>(() => moduleMenu.value.length > 0)

  // Actions
  async function bootstrap(options?: { adminMode?: boolean }): Promise<BootstrapResponse> {
    const companyStore = useCompanyStore()
    const userStore = useUserStore()

    try {
      const shouldUseAdminBootstrap = options?.adminMode ?? companyStore.isAdminMode
      const response = await bootstrapService.bootstrap(shouldUseAdminBootstrap)

      mainMenu.value = response.main_menu
      settingMenu.value = response.setting_menu
      moduleMenu.value = response.module_menu ?? []

      config.value = response.config
      globalSettings.value = response.global_settings

      // user store
      userStore.currentUser = response.current_user
      userStore.currentUserSettings = response.current_user_settings
      userStore.currentAbilities = response.current_user_abilities

      // Sync user form with bootstrap data
      if (response.current_user) {
        userStore.userForm = {
          name: response.current_user.name ?? '',
          email: response.current_user.email ?? '',
          password: '',
          confirm_password: '',
          language: response.current_user_settings?.language ?? '',
        }
      }

      // company store
      companyStore.companies = response.companies

      if (response.admin_mode === true) {
        companyStore.setAdminMode(true)
        companyStore.setSelectedCompany(null)
        companyStore.selectedCompanySettings = {}
        companyStore.selectedCompanyCurrency = null
      } else if (response.current_company) {
        companyStore.setAdminMode(false)
        companyStore.setSelectedCompany(response.current_company)
        companyStore.selectedCompanySettings = response.current_company_settings
        companyStore.selectedCompanyCurrency = response.current_company_currency
      } else {
        companyStore.setAdminMode(false)
        companyStore.setSelectedCompany(null)
        companyStore.selectedCompanySettings = {}
        companyStore.selectedCompanyCurrency = null
      }

      isAppLoaded.value = true

      // Load UI language: user preference > company setting > English
      // 'default' means "use company language"
      const userLang = userStore.currentUserSettings.language
      const uiLanguage =
        (userLang && userLang !== 'default' ? userLang : '') ||
        (response.current_company_settings as Record<string, string>)?.language ||
        'en'
      await (window as Record<string, unknown>).loadLanguage?.(uiLanguage)

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchCurrencies(): Promise<Currency[]> {
    if (currencies.value.length || areCurrenciesLoading.value) {
      return currencies.value
    }

    areCurrenciesLoading.value = true

    try {
      const response = await settingService.getCurrencies()
      currencies.value = response.data.map((currency) => ({
        ...currency,
        name: `${currency.code} - ${currency.name}`,
      }))
      areCurrenciesLoading.value = false
      return currencies.value
    } catch (err: unknown) {
      areCurrenciesLoading.value = false
      handleApiError(err)
      throw err
    }
  }

  async function fetchConfig(params?: Record<string, string>): Promise<ConfigResponse> {
    try {
      const response = await settingService.getConfig(params)

      if ((response as Record<string, unknown>).languages) {
        languages.value = (response as Record<string, unknown>).languages as Array<{
          code: string
          name: string
        }>
      } else if ((response as Record<string, unknown>).fiscal_years) {
        fiscalYears.value = (response as Record<string, unknown>).fiscal_years as Array<{
          key: string
          value: string
        }>
      }

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchDateFormats(): Promise<DateFormat[]> {
    if (dateFormats.value.length) {
      return dateFormats.value
    }

    try {
      const response = await settingService.getDateFormats()
      dateFormats.value = response.date_formats
      return dateFormats.value
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchTimeFormats(): Promise<TimeFormat[]> {
    if (timeFormats.value.length) {
      return timeFormats.value
    }

    try {
      const response = await settingService.getTimeFormats()
      timeFormats.value = response.time_formats
      return timeFormats.value
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchTimeZones(): Promise<string[]> {
    if (timeZones.value.length) {
      return timeZones.value
    }

    try {
      const response = await settingService.getTimezones()
      timeZones.value = response.time_zones
      return timeZones.value
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchCountries(): Promise<Country[]> {
    if (countries.value.length) {
      return countries.value
    }

    try {
      const response = await settingService.getCountries()
      countries.value = response.data
      return countries.value
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchPlaceholders(
    params: NumberPlaceholdersParams
  ): Promise<{ placeholders: NumberPlaceholder[] }> {
    try {
      return await settingService.getNumberPlaceholders(params)
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  function setSidebarVisibility(val: boolean): void {
    isSidebarOpen.value = val
  }

  function toggleSidebarCollapse(): void {
    isSidebarCollapsed.value = !isSidebarCollapsed.value
    localStore.set('sidebarCollapsed', isSidebarCollapsed.value)
  }

  function setIsAppLoaded(loaded: boolean): void {
    isAppLoaded.value = loaded
  }

  async function updateGlobalSettings(params: {
    data: GlobalSettingsPayload
    message?: string
  }): Promise<void> {
    try {
      await settingService.updateGlobalSettings(params.data)

      if (globalSettings.value) {
        Object.assign(
          globalSettings.value,
          params.data.settings
        )
      }

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

  return {
    // State
    config,
    globalSettings,
    timeZones,
    dateFormats,
    timeFormats,
    currencies,
    countries,
    languages,
    fiscalYears,
    mainMenu,
    settingMenu,
    moduleMenu,
    isAppLoaded,
    isSidebarOpen,
    isSidebarCollapsed,
    areCurrenciesLoading,
    downloadReport,
    // Getters
    menuGroups,
    hasActiveModules,
    // Actions
    bootstrap,
    fetchCurrencies,
    fetchConfig,
    fetchDateFormats,
    fetchTimeFormats,
    fetchTimeZones,
    fetchCountries,
    fetchPlaceholders,
    setSidebarVisibility,
    toggleSidebarCollapse,
    setIsAppLoaded,
    updateGlobalSettings,
  }
})
