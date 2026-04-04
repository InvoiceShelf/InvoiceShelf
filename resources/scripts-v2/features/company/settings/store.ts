import { defineStore } from 'pinia'
import { ref } from 'vue'
import { companyService } from '../../../api/services/company.service'
import type { CompanySettingsPayload } from '../../../api/services/company.service'
import { mailService } from '../../../api/services/mail.service'
import type { MailDriver, MailConfigResponse } from '../../../api/services/mail.service'
import { useNotificationStore } from '../../../stores/notification.store'
import { handleApiError } from '../../../utils/error-handling'

/**
 * Thin settings store for company mail configuration.
 * Most settings views call companyStore.updateCompanySettings() directly;
 * this store only manages state for the more complex mail configuration flow.
 */
export const useSettingsStore = defineStore('settings', () => {
  // Company Mail state
  const mailDrivers = ref<MailDriver[]>([])
  const mailConfigData = ref<MailConfigResponse | null>(null)
  const currentMailDriver = ref<string>('smtp')

  async function fetchMailDrivers(): Promise<MailDriver[]> {
    try {
      const response = await mailService.getDrivers()
      mailDrivers.value = response
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchMailConfig(): Promise<MailConfigResponse> {
    try {
      const response = await companyService.getMailConfig() as unknown as MailConfigResponse
      mailConfigData.value = response
      currentMailDriver.value = response.mail_driver ?? 'smtp'
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateMailConfig(payload: Record<string, unknown>): Promise<void> {
    try {
      await companyService.saveMailConfig(payload)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.mail.config_updated',
      })
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  return {
    mailDrivers,
    mailConfigData,
    currentMailDriver,
    fetchMailDrivers,
    fetchMailConfig,
    updateMailConfig,
  }
})
