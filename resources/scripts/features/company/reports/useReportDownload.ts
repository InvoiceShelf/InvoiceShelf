import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { downloadDocument } from '@/scripts/utils/documents'

/**
 * The download button in the reports header, wired to whichever report tab is
 * on screen through `globalStore.downloadReport`.
 *
 * The report is fetched through the API client and handed over as a file, so
 * it carries whatever credential this build has: a cookie on the web, a bearer
 * token in a client. A failure says so instead of opening an empty window.
 *
 * `resolvePath` is called at click time: the report parameters are read off
 * the form as they stand, exactly as the old code did before opening the URL.
 */
export function useReportDownload(resolvePath: () => string | null): () => void {
  const { t } = useI18n()
  const notificationStore = useNotificationStore()

  async function download(): Promise<void> {
    const path = resolvePath()

    if (!path) {
      return
    }

    try {
      await downloadDocument(path, { download: 'true' })
    } catch {
      notificationStore.showNotification({
        type: 'error',
        message: t('pdf.download_failed'),
      })
    }
  }

  return () => {
    void download()
  }
}
