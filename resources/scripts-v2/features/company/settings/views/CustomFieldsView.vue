<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { useUserStore } from '../../../../stores/user.store'
import { customFieldService } from '../../../../api/services/custom-field.service'
import CustomFieldDropdown from '@/scripts/admin/components/dropdowns/CustomFieldIndexDropdown.vue'
import CustomFieldModal from '@/scripts/admin/components/modal-components/custom-fields/CustomFieldModal.vue'

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

const ABILITIES = {
  CREATE_CUSTOM_FIELDS: 'create-custom-field',
  DELETE_CUSTOM_FIELDS: 'delete-custom-field',
  EDIT_CUSTOM_FIELDS: 'edit-custom-field',
} as const

const modalStore = useModalStore()
const userStore = useUserStore()
const { t } = useI18n()

const table = ref<{ refresh: () => void } | null>(null)

const customFieldsColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('settings.custom_fields.name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'model_type',
    label: t('settings.custom_fields.model'),
  },
  {
    key: 'type',
    label: t('settings.custom_fields.type'),
  },
  {
    key: 'is_required',
    label: t('settings.custom_fields.required'),
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

  const response = await customFieldService.list(data)

  return {
    data: (response as Record<string, unknown>).data as unknown[],
    pagination: {
      totalPages: ((response as Record<string, unknown>).meta as Record<string, number>).last_page,
      currentPage: page,
      limit: 5,
      totalCount: ((response as Record<string, unknown>).meta as Record<string, number>).total,
    },
  }
}

function addCustomField(): void {
  modalStore.openModal({
    title: t('settings.custom_fields.add_custom_field'),
    componentName: 'CustomFieldModal',
    size: 'sm',
    refreshData: table.value?.refresh,
  })
}

function refreshTable(): void {
  table.value?.refresh()
}

function getModelType(type: string): string {
  switch (type) {
    case 'Customer':
      return t('settings.custom_fields.model_type.customer')
    case 'Invoice':
      return t('settings.custom_fields.model_type.invoice')
    case 'Estimate':
      return t('settings.custom_fields.model_type.estimate')
    case 'Expense':
      return t('settings.custom_fields.model_type.expense')
    case 'Payment':
      return t('settings.custom_fields.model_type.payment')
    default:
      return type
  }
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.menu_title.custom_fields')"
    :description="$t('settings.custom_fields.section_description')"
  >
    <template #action>
      <BaseButton
        v-if="userStore.hasAbilities(ABILITIES.CREATE_CUSTOM_FIELDS)"
        variant="primary-outline"
        @click="addCustomField"
      >
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
          {{ $t('settings.custom_fields.add_custom_field') }}
        </template>
      </BaseButton>
    </template>

    <CustomFieldModal />

    <BaseTable
      ref="table"
      :data="fetchData"
      :columns="customFieldsColumns"
      class="mt-16"
    >
      <template #cell-name="{ row }">
        {{ row.data.name }}
        <span class="text-xs text-muted"> ({{ row.data.slug }})</span>
      </template>

      <template #cell-model_type="{ row }">
        {{ getModelType(row.data.model_type) }}
      </template>

      <template #cell-is_required="{ row }">
        <BaseBadge
          :bg-color="row.data.is_required ? 'bg-green-100' : 'bg-gray-100'"
          :color="row.data.is_required ? 'text-green-800' : 'text-gray-800'"
        >
          {{
            row.data.is_required
              ? $t('settings.custom_fields.yes')
              : $t('settings.custom_fields.no')
          }}
        </BaseBadge>
      </template>

      <template
        v-if="
          userStore.hasAbilities([
            ABILITIES.DELETE_CUSTOM_FIELDS,
            ABILITIES.EDIT_CUSTOM_FIELDS,
          ])
        "
        #cell-actions="{ row }"
      >
        <CustomFieldDropdown
          :row="row.data"
          :table="table"
          :load-data="refreshTable"
        />
      </template>
    </BaseTable>
  </BaseSettingCard>
</template>
