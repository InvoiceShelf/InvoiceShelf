<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { backupService, type Backup } from '@/scripts/api/services/backup.service'
import { diskService, type Disk } from '@/scripts/api/services/disk.service'
import {
  getErrorTranslationKey,
  handleApiError,
} from '@/scripts/utils/error-handling'
import AdminBackupModal from '@/scripts/features/admin/components/settings/AdminBackupModal.vue'

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

interface FetchParams {
  page: number
  filter: Record<string, unknown>
  sort: { fieldName: string; order: string }
}

interface FetchResult {
  data: Backup[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

const modalStore = useModalStore()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const table = ref<{ refresh: () => void } | null>(null)
const backupDisk = ref<Disk | null>(null)
const isFetchingInitialData = ref(false)
const backupError = ref('')

const backupColumns = computed<TableColumn[]>(() => [
  {
    key: 'path',
    label: t('settings.backup.path'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'created_at',
    label: t('settings.backup.created_at'),
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'size',
    label: t('settings.backup.size'),
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'disk_name',
    label: t('settings.disk.title', 1),
    tdClass: 'font-medium text-muted',
    sortable: false,
  },
  {
    key: 'actions',
    label: '',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

loadBackupDisk()

async function loadBackupDisk(): Promise<void> {
  isFetchingInitialData.value = true

  try {
    const [diskResponse, purposesResponse] = await Promise.all([
      diskService.list({ limit: 'all' }),
      diskService.getDiskPurposes(),
    ])

    const disks = diskResponse.data
    const backupDiskId = purposesResponse.backup_disk_id

    backupDisk.value =
      (backupDiskId ? disks.find((disk) => disk.id === Number(backupDiskId)) : null) ??
      disks.find((disk) => disk.set_as_default) ??
      disks[0] ??
      null
    // Refresh table now that we know which disk to query
    refreshTable()
  } catch (error: unknown) {
    showApiError(error)
  } finally {
    isFetchingInitialData.value = false
  }
}

async function fetchData({ page }: FetchParams): Promise<FetchResult> {
  if (!backupDisk.value) {
    return emptyResult(page)
  }

  backupError.value = ''

  try {
    const response = await backupService.list({
      disk: backupDisk.value.driver,
      file_disk_id: backupDisk.value.id,
    })

    if (response.error) {
      backupError.value = t('settings.backup.invalid_disk_credentials')

      return emptyResult(page)
    }

    return {
      data: response.backups,
      pagination: {
        totalPages: 1,
        currentPage: 1,
        totalCount: response.backups.length,
        limit: response.backups.length || 1,
      },
    }
  } catch (error: unknown) {
    showApiError(error)
    return emptyResult(page)
  }
}

async function removeBackup(backup: Backup): Promise<void> {
  if (!backupDisk.value) {
    return
  }

  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('settings.backup.backup_confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  })

  if (!confirmed) {
    return
  }

  try {
    const response = await backupService.delete({
      disk: backupDisk.value.driver,
      file_disk_id: backupDisk.value.id,
      path: backup.path,
    })

    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: t('settings.backup.deleted_message'),
      })
      refreshTable()
    }
  } catch (error: unknown) {
    showApiError(error)
  }
}

async function downloadBackup(backup: Backup): Promise<void> {
  if (!backupDisk.value) {
    return
  }

  isFetchingInitialData.value = true
  let objectUrl = ''

  try {
    const blob = await backupService.download({
      disk: backupDisk.value.driver,
      file_disk_id: backupDisk.value.id,
      path: backup.path,
    })

    objectUrl = window.URL.createObjectURL(blob)

    const downloadLink = document.createElement('a')
    downloadLink.href = objectUrl
    downloadLink.setAttribute(
      'download',
      backup.path.split('/').pop() ?? 'backup.zip'
    )
    document.body.appendChild(downloadLink)
    downloadLink.click()
    document.body.removeChild(downloadLink)
  } catch (error: unknown) {
    showApiError(error)
  } finally {
    if (objectUrl) {
      window.URL.revokeObjectURL(objectUrl)
    }
    isFetchingInitialData.value = false
  }
}

function openCreateBackupModal(): void {
  if (!backupDisk.value) {
    return
  }

  modalStore.openModal({
    title: t('settings.backup.create_backup'),
    componentName: 'AdminBackupModal',
    size: 'sm',
    data: {
      file_disk_id: backupDisk.value.id,
    },
    refreshData: table.value?.refresh,
  })
}

function refreshTable(): void {
  table.value?.refresh()
}

function emptyResult(page: number): FetchResult {
  return {
    data: [],
    pagination: {
      totalPages: 1,
      currentPage: page,
      totalCount: 0,
      limit: 1,
    },
  }
}

function showApiError(error: unknown): void {
  const normalizedError = handleApiError(error)
  const translationKey = getErrorTranslationKey(normalizedError.message)

  notificationStore.showNotification({
    type: 'error',
    message: translationKey ? t(translationKey) : normalizedError.message,
  })
}
</script>

<template>
  <AdminBackupModal />

  <BaseSettingCard
    :title="$t('settings.backup.title', 1)"
    :description="$t('settings.backup.description')"
  >
    <template #action>
      <BaseButton variant="primary-outline" @click="openCreateBackupModal">
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
        </template>
        {{ $t('settings.backup.new_backup') }}
      </BaseButton>
    </template>

    <BaseErrorAlert
      v-if="backupError"
      class="mt-6"
      :errors="[backupError]"
    />

    <BaseTable
      ref="table"
      class="mt-10"
      :show-filter="false"
      :data="fetchData"
      :columns="backupColumns"
    >
      <template #cell-disk_name>
        {{ backupDisk?.name ?? '-' }}
      </template>

      <template #cell-actions="{ row }">
        <BaseDropdown>
          <template #activator>
            <div class="inline-block">
              <BaseIcon name="EllipsisHorizontalIcon" class="text-muted" />
            </div>
          </template>

          <BaseDropdownItem @click="downloadBackup(row.data)">
            <BaseIcon name="CloudArrowDownIcon" class="mr-3 text-body" />
            {{ $t('general.download') }}
          </BaseDropdownItem>

          <BaseDropdownItem @click="removeBackup(row.data)">
            <BaseIcon name="TrashIcon" class="mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </template>
    </BaseTable>
  </BaseSettingCard>
</template>
