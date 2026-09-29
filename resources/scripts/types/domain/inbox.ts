/**
 * A notice in the bell. The words are translation keys filled in with the
 * params; params named in `translate` hold a translation key themselves.
 */
export interface InboxNotice {
  id: string
  type: string
  group: InboxGroup
  title: string | null
  body: string | null
  params: Record<string, string>
  translate: string[]
  url: string | null
  read_at: string | null
  created_at: string | null
}

export type InboxGroup = 'sales' | 'purchases' | 'recurring' | 'team' | 'system'

/** Where one kind of notice reaches the signed-in user, in this company. */
export interface NotificationPreference {
  type: string
  group: InboxGroup
  personal: boolean
  /** False when the company switched this kind of notice off */
  enabled: boolean
  bell: boolean
  mail: boolean
  /** Which channels are the user's own choice rather than the company's default */
  customised: { bell: boolean; mail: boolean }
}

/** What the company's owner decided for one kind of notice. */
export interface CompanyNotificationDefault {
  type: string
  group: InboxGroup
  personal: boolean
  enabled: boolean
  bell: boolean
  mail: boolean
}

export interface InboxPage {
  data: InboxNotice[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    unread_count: number
  }
}
