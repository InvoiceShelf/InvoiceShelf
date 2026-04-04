<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@v2/stores/modal.store'
import { useDialogStore } from '@v2/stores/dialog.store'
import { itemService } from '@v2/api/services/item.service'
import ItemUnitModal from './ItemUnitModal.vue'

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
  data: unknown[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

interface RowData {
  data: {
    id: number
    name: string
  }
}

const { t } = useI18n()
const table = ref<{ refresh: () => void } | null>(null)

const modalStore = useModalStore()
const dialogStore = useDialogStore()

const columns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('settings.customization.items.unit_name'),
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

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  const response = await itemService.listUnits(data)

  return {
    data: (response as Record<string, unknown>).data as unknown[],
    pagination: {
      totalPages: ((response as Record<string, unknown>).meta as Record<string, number>).last_page,
      currentPage: page,
      totalCount: ((response as Record<string, unknown>).meta as Record<string, number>).total,
      limit: 5,
    },
  }
}

function addItemUnit(): void {
  modalStore.openModal({
    title: t('settings.customization.items.add_item_unit'),
    componentName: 'ItemUnitModal',
    refreshData: table.value?.refresh,
    size: 'sm',
  })
}

function editItemUnit(row: RowData): void {
  modalStore.openModal({
    title: t('settings.customization.items.edit_item_unit'),
    componentName: 'ItemUnitModal',
    data: row.data.id,
    refreshData: table.value?.refresh,
  })
}

function removeItemUnit(row: RowData): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.customization.items.item_unit_confirm_delete'),
      yesLabel: t('general.yes'),
      noLabel: t('general.no'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        await itemService.deleteUnit(row.data.id)
        table.value?.refresh()
      }
    })
}
</script>

<template>
  <ItemUnitModal />

  <div class="flex flex-wrap justify-end mt-2 lg:flex-nowrap">
    <BaseButton variant="primary-outline" @click="addItemUnit">
      <template #left="slotProps">
        <BaseIcon :class="slotProps.class" name="PlusIcon" />
      </template>
      {{ $t('settings.customization.items.add_item_unit') }}
    </BaseButton>
  </div>

  <BaseTable ref="table" class="mt-10" :data="fetchData" :columns="columns">
    <template #cell-actions="{ row }">
      <BaseDropdown>
        <template #activator>
          <div class="inline-block">
            <BaseIcon name="EllipsisHorizontalIcon" class="text-muted" />
          </div>
        </template>

        <BaseDropdownItem @click="editItemUnit(row)">
          <BaseIcon
            name="PencilIcon"
            class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
          />
          {{ $t('general.edit') }}
        </BaseDropdownItem>
        <BaseDropdownItem @click="removeItemUnit(row)">
          <BaseIcon
            name="TrashIcon"
            class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
          />
          {{ $t('general.delete') }}
        </BaseDropdownItem>
      </BaseDropdown>
    </template>
  </BaseTable>
</template>
