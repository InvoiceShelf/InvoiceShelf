/**
 * App-wide constants for the InvoiceShelf application.
 */

/** Document status values */
export const DOCUMENT_STATUS = {
  DRAFT: 'DRAFT',
  SENT: 'SENT',
  VIEWED: 'VIEWED',
  EXPIRED: 'EXPIRED',
  ACCEPTED: 'ACCEPTED',
  REJECTED: 'REJECTED',
  PAID: 'PAID',
  UNPAID: 'UNPAID',
  PARTIALLY_PAID: 'PARTIALLY PAID',
  COMPLETED: 'COMPLETED',
  DUE: 'DUE',
} as const

export type DocumentStatus = typeof DOCUMENT_STATUS[keyof typeof DOCUMENT_STATUS]

/** Badge color configuration for document statuses */
export interface BadgeColor {
  bgColor: string
  color: string
}

export const STATUS_BADGE_COLORS: Record<string, BadgeColor> = {
  DRAFT: { bgColor: '#F8EDCB', color: '#744210' },
  PAID: { bgColor: '#D5EED0', color: '#276749' },
  UNPAID: { bgColor: '#F8EDC', color: '#744210' },
  SENT: { bgColor: 'rgba(246, 208, 154, 0.4)', color: '#975a16' },
  REJECTED: { bgColor: '#E1E0EA', color: '#1A1841' },
  ACCEPTED: { bgColor: '#D5EED0', color: '#276749' },
  VIEWED: { bgColor: '#C9E3EC', color: '#2c5282' },
  EXPIRED: { bgColor: '#FED7D7', color: '#c53030' },
  'PARTIALLY PAID': { bgColor: '#C9E3EC', color: '#2c5282' },
  COMPLETED: { bgColor: '#D5EED0', color: '#276749' },
  DUE: { bgColor: '#F8EDCB', color: '#744210' },
  YES: { bgColor: '#D5EED0', color: '#276749' },
  NO: { bgColor: '#FED7D7', color: '#c53030' },
}

/** Theme options */
export const THEME = {
  LIGHT: 'light',
  DARK: 'dark',
  SYSTEM: 'system',
} as const

export type Theme = typeof THEME[keyof typeof THEME]

/** Local storage keys used throughout the app */
export const LS_KEYS = {
  AUTH_TOKEN: 'auth.token',
  SELECTED_COMPANY: 'selectedCompany',
  IS_ADMIN_MODE: 'isAdminMode',
  SIDEBAR_COLLAPSED: 'sidebarCollapsed',
  THEME: 'theme',
} as const

/** Notification types */
export const NOTIFICATION_TYPE = {
  SUCCESS: 'success',
  ERROR: 'error',
  INFO: 'info',
  WARNING: 'warning',
} as const

export type NotificationType = typeof NOTIFICATION_TYPE[keyof typeof NOTIFICATION_TYPE]

/** Pagination defaults */
export const PAGINATION_DEFAULTS = {
  PAGE: 1,
  LIMIT: 15,
} as const

/** Dialog variant options */
export const DIALOG_VARIANT = {
  PRIMARY: 'primary',
  DANGER: 'danger',
} as const

export type DialogVariant = typeof DIALOG_VARIANT[keyof typeof DIALOG_VARIANT]

/** Modal size options */
export const MODAL_SIZE = {
  SM: 'sm',
  MD: 'md',
  LG: 'lg',
  XL: 'xl',
} as const

export type ModalSize = typeof MODAL_SIZE[keyof typeof MODAL_SIZE]

/** Valid image MIME types for uploads */
export const VALID_IMAGE_TYPES = [
  'image/gif',
  'image/jpeg',
  'image/png',
] as const
