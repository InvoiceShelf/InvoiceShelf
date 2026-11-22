import { useI18n } from 'vue-i18n'
import type { InboxNotice } from '@/scripts/types/domain/inbox'

const GROUP_ICONS: Record<string, string> = {
  sales: 'DocumentTextIcon',
  purchases: 'ShoppingCartIcon',
  recurring: 'ArrowPathIcon',
  team: 'UsersIcon',
  system: 'Cog6ToothIcon',
}

const RELATIVE_UNITS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
  ['year', 31536000],
  ['month', 2592000],
  ['week', 604800],
  ['day', 86400],
  ['hour', 3600],
  ['minute', 60],
]

/**
 * How a notice reads: its title and body in the viewer's language, the icon
 * of its group, and when it arrived ("3 hours ago").
 */
export function useInboxNotice() {
  const { t, te, locale } = useI18n()

  function params(notice: InboxNotice): Record<string, string> {
    const filled: Record<string, string> = {}
    for (const [name, value] of Object.entries(notice.params ?? {})) {
      filled[name] = notice.translate?.includes(name) && te(value) ? t(value) : String(value)
    }
    return filled
  }

  function title(notice: InboxNotice): string {
    return notice.title ? t(notice.title, params(notice)) : notice.type
  }

  function body(notice: InboxNotice): string {
    return notice.body && te(notice.body) ? t(notice.body, params(notice)) : ''
  }

  function icon(notice: InboxNotice): string {
    return GROUP_ICONS[notice.group] ?? 'BellIcon'
  }

  function arrived(notice: InboxNotice): string {
    if (!notice.created_at) {
      return ''
    }
    const seconds = Math.round((new Date(notice.created_at).getTime() - Date.now()) / 1000)
    const format = new Intl.RelativeTimeFormat(String(locale.value).replace('_', '-'), {
      numeric: 'auto',
    })

    for (const [unit, size] of RELATIVE_UNITS) {
      if (Math.abs(seconds) >= size) {
        return format.format(Math.round(seconds / size), unit)
      }
    }
    return t('inbox.just_now')
  }

  return { title, body, icon, arrived }
}
