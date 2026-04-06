import { client } from '../client'
import { API } from '../endpoints'

export type MailDriver = string

export interface SmtpConfig {
  mail_driver: string
  mail_host: string
  mail_port: number | null
  mail_username: string
  mail_password: string
  mail_encryption: string
  from_mail: string
  from_name: string
}

export interface MailgunConfig {
  mail_driver: string
  mail_mailgun_domain: string
  mail_mailgun_secret: string
  mail_mailgun_endpoint: string
  from_mail: string
  from_name: string
}

export interface SesConfig {
  mail_driver: string
  mail_host: string
  mail_port: number | null
  mail_ses_key: string
  mail_ses_secret: string
  mail_ses_region: string
  from_mail: string
  from_name: string
}

export type MailConfig = SmtpConfig | MailgunConfig | SesConfig

export interface MailConfigResponse {
  mail_driver: string
  [key: string]: unknown
}

export interface TestMailPayload {
  to: string
  subject: string
  message: string
}

export const mailService = {
  async getDrivers(): Promise<MailDriver[]> {
    const { data } = await client.get(API.MAIL_DRIVERS)
    return data
  },

  async getConfig(): Promise<MailConfigResponse> {
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
