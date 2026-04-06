<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@v2/stores/modal.store'
import { useDialogStore } from '@v2/stores/dialog.store'
import { useGlobalStore } from '@v2/stores/global.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import { diskService, type Disk } from '@v2/api/services/disk.service'
import {
  getErrorTranslationKey,
  handleApiError,
} from '@v2/utils/error-handling'
import AdminFileDiskModal from '@v2/features/admin/components/settings/AdminFileDiskModal.vue'

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
  data: Disk[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

const modalStore = useModalStore()
const dialogStore = useDialogStore()
const globalStore = useGlobalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const table = ref<{ refresh: () => void } | null>(null)
const savePdfToDisk = ref(
  (globalStore.globalSettings?.save_pdf_to_disk ?? 'NO') === 'YES'
)

const fileDiskColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('settings.disk.disk_name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'driver',
    label: t('settings.disk.filesystem_driver'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'type',
    label: t('settings.disk.disk_type'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'set_as_default',
    label: t('settings.disk.is_default'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'actions',
    label: '',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

const savePdfToDiskField = computed<boolean>({
  get: () => savePdfToDisk.value,
  set: async (enabled) => {
    savePdfToDisk.value = enabled

    await globalStore.updateGlobalSettings({
      data: {
        settings: {
          save_pdf_to_disk: enabled ? 'YES' : 'NO',
        },
      },
      message: t('general.setting_updated'),
    })
  },
})

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const response = await diskService.list({
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  })

  return {
    data: response.data,
    pagination: {
      totalPages: response.meta.last_page,
      currentPage: page,
      totalCount: response.meta.total,
      limit: Number(response.meta.per_page) || 5,
    },
  }
}

async function setDefaultDisk(id: number): Promise<void> {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('settings.disk.set_default_disk_confirm'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  })

  if (!confirmed) {
    return
  }

  try {
    await diskService.update(id, { set_as_default: true })

    notificationStore.showNotification({
      type: 'success',
      message: t('settings.disk.success_set_default_disk'),
    })

    refreshTable()
  } catch (error: unknown) {
    showApiError(error)
  }
}

async function removeDisk(id: number): Promise<void> {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('settings.disk.confirm_delete'),
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
    const response = await diskService.delete(id)

    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: t('settings.disk.deleted_message'),
      })
      refreshTable()
    }
  } catch (error: unknown) {
    showApiError(error)
  }
}

function openCreateDiskModal(): void {
  modalStore.openModal({
    title: t('settings.disk.new_disk'),
    componentName: 'AdminFileDiskModal',
    size: 'lg',
    refreshData: table.value?.refresh,
  })
}

function openEditDiskModal(disk: Disk): void {
  modalStore.openModal({
    title: t('settings.disk.edit_file_disk'),
    componentName: 'AdminFileDiskModal',
    id: disk.id,
    data: disk,
    size: 'lg',
    refreshData: table.value?.refresh,
  })
}

function canShowActions(disk: Disk): boolean {
  return !disk.set_as_default || disk.type !== 'SYSTEM'
}

function refreshTable(): void {
  table.value?.refresh()
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
  <AdminFileDiskModal />

  <BaseSettingCard
    :title="$t('settings.disk.title', 1)"
    :description="$t('settings.disk.description')"
  >
    <template #action>
      <BaseButton variant="primary-outline" @click="openCreateDiskModal">
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
        </template>
        {{ $t('settings.disk.new_disk') }}
      </BaseButton>
    </template>

    <BaseTable
      ref="table"
      class="mt-16"
      :data="fetchData"
      :columns="fileDiskColumns"
    >
      <template #cell-set_as_default="{ row }">
        <span
          :class="
            row.data.set_as_default
              ? 'bg-success text-status-green'
              : 'bg-surface-muted text-muted'
          "
          class="inline-flex rounded-full px-2 py-1 text-xs font-medium uppercase"
        >
          {{
            row.data.set_as_default ? $t('general.yes') : $t('general.no')
          }}
        </span>
      </template>

      <template #cell-actions="{ row }">
        <BaseDropdown v-if="canShowActions(row.data)">
          <template #activator>
            <div class="inline-block">
              <BaseIcon name="EllipsisHorizontalIcon" class="text-muted" />
            </div>
          </template>

          <BaseDropdownItem
            v-if="!row.data.set_as_default"
            @click="setDefaultDisk(row.data.id)"
          >
            <BaseIcon class="mr-3 text-body" name="CheckCircleIcon" />
            {{ $t('settings.disk.set_default_disk') }}
          </BaseDropdownItem>

          <BaseDropdownItem
            v-if="row.data.type !== 'SYSTEM'"
            @click="openEditDiskModal(row.data)"
          >
            <BaseIcon name="PencilIcon" class="mr-3 text-body" />
            {{ $t('general.edit') }}
          </BaseDropdownItem>

          <BaseDropdownItem
            v-if="row.data.type !== 'SYSTEM' && !row.data.set_as_default"
            @click="removeDisk(row.data.id)"
          >
            <BaseIcon name="TrashIcon" class="mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </template>
    </BaseTable>

    <BaseDivider class="mt-8 mb-2" />

    <BaseSwitchSection
      v-model="savePdfToDiskField"
      :title="$t('settings.disk.save_pdf_to_disk')"
      :description="$t('settings.disk.disk_setting_description')"
    />
  </BaseSettingCard>
</template>
