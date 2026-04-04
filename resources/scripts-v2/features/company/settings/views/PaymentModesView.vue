<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { paymentService } from '../../../../api/services/payment.service'
import PaymentModeModal from '@/scripts/admin/components/modal-components/PaymentModeModal.vue'
import PaymentModeDropdown from '@/scripts/admin/components/dropdowns/PaymentModeIndexDropdown.vue'

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

const modalStore = useModalStore()
const { t } = useI18n()

const table = ref<{ refresh: () => void } | null>(null)

const paymentColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('settings.payment_modes.mode_name'),
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

async function refreshTable(): Promise<void> {
  table.value?.refresh()
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  const response = await paymentService.listMethods(data)

  return {
    data: (response as Record<string, unknown>).data as unknown[],
    pagination: {
      totalPages: ((response as Record<string, unknown>).meta as Record<string, number>)?.last_page ?? 1,
      currentPage: page,
      totalCount: ((response as Record<string, unknown>).meta as Record<string, number>)?.total ?? 0,
      limit: 5,
    },
  }
}

function addPaymentMode(): void {
  modalStore.openModal({
    title: t('settings.payment_modes.add_payment_mode'),
    componentName: 'PaymentModeModal',
    refreshData: table.value?.refresh,
    size: 'sm',
  })
}
</script>

<template>
  <PaymentModeModal />

  <BaseSettingCard
    :title="$t('settings.payment_modes.title')"
    :description="$t('settings.payment_modes.description')"
  >
    <template #action>
      <BaseButton
        type="submit"
        variant="primary-outline"
        @click="addPaymentMode"
      >
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
        </template>
        {{ $t('settings.payment_modes.add_payment_mode') }}
      </BaseButton>
    </template>

    <BaseTable
      ref="table"
      :data="fetchData"
      :columns="paymentColumns"
      class="mt-16"
    >
      <template #cell-actions="{ row }">
        <PaymentModeDropdown
          :row="row.data"
          :table="table"
          :load-data="refreshTable"
        />
      </template>
    </BaseTable>
  </BaseSettingCard>
</template>
