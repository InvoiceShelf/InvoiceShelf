import { useI18n } from 'vue-i18n'
import type { MenuItem } from '@/scripts/api/services/bootstrap.service'

/** Keep a destination's section available wherever group headings are absent. */
export function useMenuLabel() {
  const { t } = useI18n()
  function menuContext(item: MenuItem): string {
    return item.group_label ? t(item.group_label) : ''
  }
  function menuLabel(item: MenuItem): string {
    const group = menuContext(item)
    return group ? t('navigation.grouped_item', {group, item: t(item.title)}) : t(item.title)
  }
  return { menuContext, menuLabel }
}
