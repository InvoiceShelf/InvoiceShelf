export type AnnouncementLevel = 'info' | 'warning' | 'critical'

export type AnnouncementAudience = 'everyone' | 'admins'

/** An announcement as a user sees it, already in their language. */
export interface ActiveAnnouncement {
  id: number
  source: 'local' | 'feed'
  level: AnnouncementLevel
  audience: AnnouncementAudience
  title: string
  body: string
  link_url: string | null
  link_label: string | null
  starts_at: string | null
  ends_at: string | null
}

export interface AnnouncementTranslation {
  title?: string
  body?: string
  link_label?: string
}

/** An announcement as the super admin manages it. */
export interface Announcement {
  id: number
  source: 'local' | 'feed'
  title: string
  body: string
  link_url: string | null
  link_label: string | null
  level: AnnouncementLevel
  audience: AnnouncementAudience
  starts_at: string | null
  ends_at: string | null
  translations: Record<string, AnnouncementTranslation>
  hidden: boolean
  updated_at: string | null
}

export interface AnnouncementPayload {
  title: string
  body: string
  link_url: string | null
  link_label: string | null
  level: AnnouncementLevel
  audience: AnnouncementAudience
  starts_at: string | null
  ends_at: string | null
  translations: Record<string, AnnouncementTranslation>
}
