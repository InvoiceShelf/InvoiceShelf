import { client } from '../client'
import { API } from '../endpoints'
import type { MailConfig, MailDriver, TestMailPayload } from '@/scripts/types/mail-config'

export const mailService = {
  async getDrivers(): Promise<MailDriver[]> {
    const { data } = await client.get(API.MAIL_DRIVERS)
    return data
  },

  async getConfig(): Promise<MailConfig> {
    const { data } = await client.get(API.MAIL_CONFIG)
    return data
  },

  async saveConfig(payload: MailConfig): Promise<{ success?: string; error?: string }> {
    const { data } = await client.post(API.MAIL_CONFIG, payload)
    return data
  },

  async testEmail(payload: TestMailPayload): Promise<{ success?: boolean; error?: string }> {
    const { data } = await client.post(API.MAIL_TEST, payload)
    return data
  },
}
