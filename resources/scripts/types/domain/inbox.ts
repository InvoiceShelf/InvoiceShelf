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

/** Where one kind of notice reaches the signed-in user. */
export interface NotificationPreference {
  type: string
  group: InboxGroup
  personal: boolean
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
