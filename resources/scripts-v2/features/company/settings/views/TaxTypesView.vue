<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'
import { useModalStore } from '../../../../stores/modal.store'
import { taxTypeService } from '../../../../api/services/tax-type.service'
import TaxTypeDropdown from '@/scripts/admin/components/dropdowns/TaxTypeIndexDropdown.vue'
import TaxTypeModal from '@/scripts/admin/components/modal-components/TaxTypeModal.vue'

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
  CREATE_TAX_TYPE: 'create-tax-type',
  DELETE_TAX_TYPE: 'delete-tax-type',
  EDIT_TAX_TYPE: 'edit-tax-type',
} as const

const { t } = useI18n()
const companyStore = useCompanyStore()
const modalStore = useModalStore()
const userStore = useUserStore()
const table = ref<{ refresh: () => void } | null>(null)
const taxPerItemSetting = ref<string>(companyStore.selectedCompanySettings.tax_per_item)

const defaultCurrency = computed(() => companyStore.selectedCompanyCurrency)

const taxTypeColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('settings.tax_types.tax_name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'calculation_type',
    label: t('settings.tax_types.calculation_type'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'amount',
    label: t('settings.tax_types.amount'),
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

const salesTaxEnabled = computed<boolean>(() => {
  return companyStore.selectedCompanySettings.sales_tax_us_enabled === 'YES'
})

const taxPerItemField = computed<boolean>({
  get: () => taxPerItemSetting.value === 'YES',
  set: async (newValue: boolean) => {
    const value = newValue ? 'YES' : 'NO'
    taxPerItemSetting.value = value

    await companyStore.updateCompanySettings({
      data: { settings: { tax_per_item: value } },
      message: 'general.setting_updated',
    })
  },
})

const taxIncludedSettings = reactive<{ tax_included: string; tax_included_by_default: string }>({
  tax_included: companyStore.selectedCompanySettings.tax_included ?? 'NO',
  tax_included_by_default: companyStore.selectedCompanySettings.tax_included_by_default ?? 'NO',
})

const taxIncludedField = computed<boolean>({
  get: () => taxIncludedSettings.tax_included === 'YES',
  set: async (newValue: boolean) => {
    const value = newValue ? 'YES' : 'NO'
    taxIncludedSettings.tax_included = value

    if (!newValue) {
      taxIncludedSettings.tax_included_by_default = 'NO'
    }

    await companyStore.updateCompanySettings({
      data: { settings: { ...taxIncludedSettings } },
      message: 'general.setting_updated',
    })
  },
})

const taxIncludedByDefaultField = computed<boolean>({
  get: () => taxIncludedSettings.tax_included_by_default === 'YES',
  set: async (newValue: boolean) => {
    const value = newValue ? 'YES' : 'NO'
    taxIncludedSettings.tax_included_by_default = value

    await companyStore.updateCompanySettings({
      data: { settings: { tax_included_by_default: value } },
      message: 'general.setting_updated',
    })
  },
})

function hasAtleastOneAbility(): boolean {
  return userStore.hasAbilities([ABILITIES.DELETE_TAX_TYPE, ABILITIES.EDIT_TAX_TYPE])
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  const response = await taxTypeService.list(data)

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

function refreshTable(): void {
  table.value?.refresh()
}

function openTaxModal(): void {
  modalStore.openModal({
    title: t('settings.tax_types.add_tax'),
    componentName: 'TaxTypeModal',
    size: 'sm',
    refreshData: table.value?.refresh,
  })
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.tax_types.title')"
    :description="$t('settings.tax_types.description')"
  >
    <TaxTypeModal />

    <template v-if="userStore.hasAbilities(ABILITIES.CREATE_TAX_TYPE)" #action>
      <BaseButton type="submit" variant="primary-outline" @click="openTaxModal">
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
        </template>
        {{ $t('settings.tax_types.add_new_tax') }}
      </BaseButton>
    </template>

    <BaseTable
      ref="table"
      class="mt-16"
      :data="fetchData"
      :columns="taxTypeColumns"
    >
      <template #cell-calculation_type="{ row }">
        {{ $t(`settings.tax_types.${row.data.calculation_type}`) }}
      </template>

      <template #cell-amount="{ row }">
        <template v-if="row.data.calculation_type === 'percentage'">
          {{ row.data.percent }} %
        </template>
        <template v-else-if="row.data.calculation_type === 'fixed'">
          <BaseFormatMoney :amount="row.data.fixed_amount" :currency="defaultCurrency" />
        </template>
        <template v-else> - </template>
      </template>

      <template v-if="hasAtleastOneAbility()" #cell-actions="{ row }">
        <TaxTypeDropdown
          :row="row.data"
          :table="table"
          :load-data="refreshTable"
        />
      </template>
    </BaseTable>

    <div v-if="userStore.currentUser?.is_owner">
      <BaseDivider class="mt-8 mb-2" />

      <BaseSwitchSection
        v-model="taxPerItemField"
        :disabled="salesTaxEnabled"
        :title="$t('settings.tax_types.tax_per_item')"
        :description="$t('settings.tax_types.tax_setting_description')"
      />

      <BaseDivider class="mt-8 mb-2" />

      <BaseSwitchSection
        v-model="taxIncludedField"
        :title="$t('settings.tax_types.tax_included')"
        :description="$t('settings.tax_types.tax_included_description')"
      />

      <BaseSwitchSection
        v-if="taxIncludedField"
        v-model="taxIncludedByDefaultField"
        :title="$t('settings.tax_types.tax_included_by_default')"
        :description="$t('settings.tax_types.tax_included_by_default_description')"
      />
    </div>
  </BaseSettingCard>
</template>
