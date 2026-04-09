export type MailDriver =
  | 'smtp'
  | 'mail'
  | 'sendmail'
  | 'ses'
  | 'mailgun'
  | 'postmark'

export interface MailConfig {
  mail_driver: MailDriver | ''
  from_mail: string
  from_name: string
  mail_host: string
  mail_port: string
  mail_username: string
  mail_password: string
  mail_encryption: string
  mail_scheme: string
  mail_url: string
  mail_timeout: string
  mail_local_domain: string
  mail_sendmail_path: string
  mail_ses_key: string
  mail_ses_secret: string
  mail_ses_region: string
  mail_mailgun_domain: string
  mail_mailgun_secret: string
  mail_mailgun_endpoint: string
  mail_mailgun_scheme: string
  mail_postmark_token: string
  mail_postmark_message_stream_id: string
}

export interface CompanyMailConfig extends MailConfig {
  use_custom_mail_config: 'YES' | 'NO'
}

export interface TestMailPayload {
  to: string
  subject: string
  message: string
}
