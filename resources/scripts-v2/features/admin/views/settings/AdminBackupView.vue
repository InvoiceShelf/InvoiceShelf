<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@v2/stores/modal.store'
import { useDialogStore } from '@v2/stores/dialog.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import { backupService, type Backup } from '@v2/api/services/backup.service'
import { diskService, type Disk } from '@v2/api/services/disk.service'
import {
  getErrorTranslationKey,
  handleApiError,
} from '@v2/utils/error-handling'
import AdminBackupModal from '@v2/features/admin/components/settings/AdminBackupModal.vue'

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

interface DiskOption extends Disk {
  display_name: string
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
const disks = ref<DiskOption[]>([])
const selectedDisk = ref<DiskOption | null>(null)
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
    key: 'actions',
    label: '',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

watch(
  selectedDisk,
  (newDisk, oldDisk) => {
    if (newDisk?.id && oldDisk?.id && newDisk.id !== oldDisk.id) {
      refreshTable()
    }
  }
)

loadDisks()

async function loadDisks(): Promise<void> {
  isFetchingInitialData.value = true

  try {
    const response = await diskService.list({ limit: 'all' })

    disks.value = response.data.map((disk) => ({
      ...disk,
      display_name: `${disk.name} - [${disk.driver}]`,
    }))

    selectedDisk.value =
      disks.value.find((disk) => disk.set_as_default) ?? disks.value[0] ?? null
  } catch (error: unknown) {
    showApiError(error)
  } finally {
    isFetchingInitialData.value = false
  }
}

async function fetchData({ page }: FetchParams): Promise<FetchResult> {
  if (!selectedDisk.value) {
    return emptyResult(page)
  }

  backupError.value = ''

  try {
    const response = await backupService.list({
      disk: selectedDisk.value.driver,
      file_disk_id: selectedDisk.value.id,
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
  if (!selectedDisk.value) {
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
      disk: selectedDisk.value.driver,
      file_disk_id: selectedDisk.value.id,
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
  if (!selectedDisk.value) {
    return
  }

  isFetchingInitialData.value = true
  let objectUrl = ''

  try {
    const blob = await backupService.download({
      disk: selectedDisk.value.driver,
      file_disk_id: selectedDisk.value.id,
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
  modalStore.openModal({
    title: t('settings.backup.create_backup'),
    componentName: 'AdminBackupModal',
    size: 'sm',
    data: {
      disks: disks.value,
      selectedDiskId: selectedDisk.value?.id ?? null,
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

    <div class="grid my-14 md:grid-cols-3">
      <BaseInputGroup
        :label="$t('settings.disk.select_disk')"
        :content-loading="isFetchingInitialData"
      >
        <BaseMultiselect
          v-model="selectedDisk"
          :content-loading="isFetchingInitialData"
          :options="disks"
          track-by="id"
          value-prop="id"
          label="display_name"
          :placeholder="$t('settings.disk.select_disk')"
          object
          searchable
          class="w-full"
        />
      </BaseInputGroup>
    </div>

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
