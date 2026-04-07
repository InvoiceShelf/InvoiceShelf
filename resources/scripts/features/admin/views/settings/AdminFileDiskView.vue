<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { DiskPurposes } from '@/scripts/api/services/disk.service'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { diskService, type Disk } from '@/scripts/api/services/disk.service'
import {
  getErrorTranslationKey,
  handleApiError,
} from '@/scripts/utils/error-handling'
import AdminFileDiskModal from '@/scripts/features/admin/components/settings/AdminFileDiskModal.vue'

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

// Disk purpose assignments
const allDisks = ref<Disk[]>([])
const purposes = ref<DiskPurposes>({
  media_disk_id: null,
  pdf_disk_id: null,
  backup_disk_id: null,
})
const originalPurposes = ref<DiskPurposes>({
  media_disk_id: null,
  pdf_disk_id: null,
  backup_disk_id: null,
})
const isSavingPurposes = ref(false)

onMounted(async () => {
  try {
    const [disksRes, purposesRes] = await Promise.all([
      diskService.list({ limit: 'all' as unknown as number }),
      diskService.getDiskPurposes(),
    ])
    allDisks.value = disksRes.data
    const normalized = {
      media_disk_id: purposesRes.media_disk_id ? Number(purposesRes.media_disk_id) : null,
      pdf_disk_id: purposesRes.pdf_disk_id ? Number(purposesRes.pdf_disk_id) : null,
      backup_disk_id: purposesRes.backup_disk_id ? Number(purposesRes.backup_disk_id) : null,
    }
    purposes.value = { ...normalized }
    originalPurposes.value = { ...normalized }
  } catch {
    // Silently fail
  }
})

function hasChangedPurposes(): boolean {
  return (
    purposes.value.media_disk_id !== originalPurposes.value.media_disk_id ||
    purposes.value.pdf_disk_id !== originalPurposes.value.pdf_disk_id ||
    purposes.value.backup_disk_id !== originalPurposes.value.backup_disk_id
  )
}

async function savePurposes(): Promise<void> {
  if (hasChangedPurposes()) {
    const confirmed = await dialogStore.openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.disk.change_disk_warning'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })

    if (!confirmed) {
      return
    }
  }

  isSavingPurposes.value = true
  try {
    await diskService.updateDiskPurposes(purposes.value)
    originalPurposes.value = { ...purposes.value }
    notificationStore.showNotification({
      type: 'success',
      message: t('settings.disk.purposes_saved'),
    })
  } catch (error: unknown) {
    showApiError(error)
  } finally {
    isSavingPurposes.value = false
  }
}

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

  <!-- Disk Assignments -->
  <BaseSettingCard
    :title="$t('settings.disk.disk_assignments')"
    :description="$t('settings.disk.disk_assignments_description')"
    class="mt-6"
  >
    <BaseInputGrid class="mt-4">
      <BaseInputGroup :label="$t('settings.disk.media_storage')">
        <BaseMultiselect
          v-model="purposes.media_disk_id"
          :options="allDisks"
          value-prop="id"
          label="name"
          track-by="name"
          :can-deselect="false"
          :placeholder="$t('settings.disk.select_disk')"
        />
        <span class="text-xs text-subtle mt-1 block">
          {{ $t('settings.disk.media_storage_description') }}
        </span>
      </BaseInputGroup>

      <BaseInputGroup :label="$t('settings.disk.pdf_storage')">
        <BaseMultiselect
          v-model="purposes.pdf_disk_id"
          :options="allDisks"
          value-prop="id"
          label="name"
          track-by="name"
          :can-deselect="false"
          :placeholder="$t('settings.disk.select_disk')"
        />
        <span class="text-xs text-subtle mt-1 block">
          {{ $t('settings.disk.pdf_storage_description') }}
        </span>
      </BaseInputGroup>

      <BaseInputGroup :label="$t('settings.disk.backup_storage')">
        <BaseMultiselect
          v-model="purposes.backup_disk_id"
          :options="allDisks"
          value-prop="id"
          label="name"
          track-by="name"
          :can-deselect="false"
          :placeholder="$t('settings.disk.select_disk')"
        />
        <span class="text-xs text-subtle mt-1 block">
          {{ $t('settings.disk.backup_storage_description') }}
        </span>
      </BaseInputGroup>
    </BaseInputGrid>

    <BaseButton
      :loading="isSavingPurposes"
      :disabled="isSavingPurposes"
      variant="primary"
      class="mt-6"
      @click="savePurposes"
    >
      {{ $t('general.save') }}
    </BaseButton>
  </BaseSettingCard>
</template>
