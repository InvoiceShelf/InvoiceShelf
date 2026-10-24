import { useI18n } from 'vue-i18n'
import { entitySaveHooks } from '@/scripts/extensions/runtime'
import { toEntityRecord } from '@/scripts/extensions/records'
import type { EntityFormContext, ExtensionEntity } from '@/scripts/extensions/types'
import { useNotificationStore } from '@/scripts/stores/notification.store'

/**
 * Run the modules' save hooks for a record the host has just saved.
 *
 * Each hook runs on its own: one that throws, rejects or takes longer than
 * ten seconds is logged and reported as a warning, and the rest still run. The record is already
 * saved, so a failing module must never surface as a failed save that
 * invites the user to submit again. Resolves to true when a hook took over
 * the next navigation.
 */
/** A hook that has not settled by then counts as failed, so the user is never left waiting on a saved form. */
const HOOK_TIMEOUT_MS = 10_000

export function useEntitySaveHooks() {
  const { t } = useI18n()
  const notificationStore = useNotificationStore()

  async function run(entity: ExtensionEntity, saved: unknown, context: EntityFormContext): Promise<boolean> {
    const record = toEntityRecord(entity, saved)
    if (record === null) {
      return false
    }

    let handled = false
    for (const hook of entitySaveHooks(entity)) {
      try {
        if ((await withTimeout(Promise.resolve(hook.handle(record, context)), hook.id)) === true && !handled) {
          handled = true
        }
      } catch (error) {
        console.error(`InvoiceShelf extension save hook "${hook.id}" failed.`, error)
        notificationStore.showNotification({ type: 'warning', message: t('general.extension_save_hook_failed') })
      }
    }

    return handled
  }

  return { run }
}

function withTimeout<T>(promise: Promise<T>, id: string): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error(`Save hook "${id}" did not finish within ${HOOK_TIMEOUT_MS} ms.`)), HOOK_TIMEOUT_MS)
    promise.then(
      (value) => {
        clearTimeout(timer)
        resolve(value)
      },
      (error: unknown) => {
        clearTimeout(timer)
        reject(error)
      },
    )
  })
}
