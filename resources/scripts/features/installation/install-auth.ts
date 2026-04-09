import { LS_KEYS } from '@/scripts/config/constants'
import * as localStore from '@/scripts/utils/local-storage'

export function setInstallWizardAuth(token: string, companyId?: number | string | null): void {
  localStore.set(LS_KEYS.INSTALL_AUTH_TOKEN, token)
  setInstallWizardCompany(companyId)
}

export function setInstallWizardCompany(companyId?: number | string | null): void {
  if (companyId === null || companyId === undefined || companyId === '') {
    localStore.remove(LS_KEYS.INSTALL_SELECTED_COMPANY)
    return
  }

  localStore.set(LS_KEYS.INSTALL_SELECTED_COMPANY, String(companyId))
}

export function clearInstallWizardAuth(): void {
  localStore.remove(LS_KEYS.INSTALL_AUTH_TOKEN)
  localStore.remove(LS_KEYS.INSTALL_SELECTED_COMPANY)
}
