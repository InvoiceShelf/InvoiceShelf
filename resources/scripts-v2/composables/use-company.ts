import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import * as ls from '../utils/local-storage'
import { LS_KEYS } from '@v2/config/constants'

export interface Company {
  id: number
  unique_hash: string
  name: string
  slug: string
  [key: string]: unknown
}

export interface CompanySettings {
  [key: string]: unknown
}

export interface CompanyCurrency {
  id: number
  code: string
  name: string
  symbol: string
  precision: number
  thousand_separator: string
  decimal_separator: string
  swap_currency_symbol?: boolean
  exchange_rate?: number
  [key: string]: unknown
}

export interface UseCompanyReturn {
  selectedCompany: Ref<Company | null>
  companies: Ref<Company[]>
  selectedCompanySettings: Ref<CompanySettings>
  selectedCompanyCurrency: Ref<CompanyCurrency | null>
  isAdminMode: Ref<boolean>
  hasCompany: ComputedRef<boolean>
  setCompany: (company: Company | null) => void
  setCompanies: (data: Company[]) => void
  setCompanySettings: (settings: CompanySettings) => void
  setCompanyCurrency: (currency: CompanyCurrency | null) => void
  enterAdminMode: () => void
  exitAdminMode: () => void
}

const selectedCompany = ref<Company | null>(null)
const companies = ref<Company[]>([])
const selectedCompanySettings = ref<CompanySettings>({})
const selectedCompanyCurrency = ref<CompanyCurrency | null>(null)
const isAdminMode = ref<boolean>(
  ls.get<string>(LS_KEYS.IS_ADMIN_MODE) === 'true'
)

/**
 * Composable for managing company selection and admin mode state.
 * Extracted from the Pinia company store, this provides reactive company state
 * with localStorage persistence for the selected company and admin mode.
 */
export function useCompany(): UseCompanyReturn {
  const hasCompany = computed<boolean>(
    () => selectedCompany.value !== null
  )

  /**
   * Set the selected company and persist to localStorage.
   * Automatically disables admin mode when a company is selected.
   *
   * @param company - The company to select, or null to deselect
   */
  function setCompany(company: Company | null): void {
    if (company) {
      ls.set(LS_KEYS.SELECTED_COMPANY, String(company.id))
      ls.remove(LS_KEYS.IS_ADMIN_MODE)
      isAdminMode.value = false
    } else {
      ls.remove(LS_KEYS.SELECTED_COMPANY)
    }
    selectedCompany.value = company
  }

  /**
   * Set the list of available companies.
   */
  function setCompanies(data: Company[]): void {
    companies.value = data
  }

  /**
   * Update the selected company's settings.
   */
  function setCompanySettings(settings: CompanySettings): void {
    selectedCompanySettings.value = settings
  }

  /**
   * Update the selected company's currency.
   */
  function setCompanyCurrency(currency: CompanyCurrency | null): void {
    selectedCompanyCurrency.value = currency
  }

  /**
   * Enter admin mode. Clears the selected company and persists admin mode.
   */
  function enterAdminMode(): void {
    isAdminMode.value = true
    ls.set(LS_KEYS.IS_ADMIN_MODE, 'true')
    ls.remove(LS_KEYS.SELECTED_COMPANY)
    selectedCompany.value = null
  }

  /**
   * Exit admin mode.
   */
  function exitAdminMode(): void {
    isAdminMode.value = false
    ls.remove(LS_KEYS.IS_ADMIN_MODE)
  }

  return {
    selectedCompany,
    companies,
    selectedCompanySettings,
    selectedCompanyCurrency,
    isAdminMode,
    hasCompany,
    setCompany,
    setCompanies,
    setCompanySettings,
    setCompanyCurrency,
    enterAdminMode,
    exitAdminMode,
  }
}
