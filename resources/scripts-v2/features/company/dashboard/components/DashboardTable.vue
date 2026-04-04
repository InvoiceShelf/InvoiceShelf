<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '../store'
import { useUserStore } from '../../../../stores/user.store'
import InvoiceDropdown from '@v2/features/company/invoices/components/InvoiceDropdown.vue'
import EstimateDropdown from '@v2/features/company/estimates/components/EstimateDropdown.vue'

interface TableColumn {
  key: string
  label?: string
  tdClass?: string
  thClass?: string
  sortable?: boolean
}

const ABILITIES = {
  VIEW_INVOICE: 'view-invoice',
  VIEW_ESTIMATE: 'view-estimate',
  DELETE_INVOICE: 'delete-invoice',
  EDIT_INVOICE: 'edit-invoice',
  SEND_INVOICE: 'send-invoice',
  CREATE_ESTIMATE: 'create-estimate',
  EDIT_ESTIMATE: 'edit-estimate',
  SEND_ESTIMATE: 'send-estimate',
} as const

const dashboardStore = useDashboardStore()
const { t } = useI18n()
const userStore = useUserStore()

const invoiceTableComponent = ref<InstanceType<typeof Object> | null>(null)
const estimateTableComponent = ref<InstanceType<typeof Object> | null>(null)

const dueInvoiceColumns = computed<TableColumn[]>(() => [
  {
    key: 'formattedDueDate',
    label: t('dashboard.recent_invoices_card.due_on'),
  },
  {
    key: 'user',
    label: t('dashboard.recent_invoices_card.customer'),
  },
  {
    key: 'due_amount',
    label: t('dashboard.recent_invoices_card.amount_due'),
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium pl-0',
    thClass: 'text-right pl-0',
    sortable: false,
  },
])

const recentEstimateColumns = computed<TableColumn[]>(() => [
  {
    key: 'formattedEstimateDate',
    label: t('dashboard.recent_estimate_card.date'),
  },
  {
    key: 'user',
    label: t('dashboard.recent_estimate_card.customer'),
  },
  {
    key: 'total',
    label: t('dashboard.recent_estimate_card.amount_due'),
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium pl-0',
    thClass: 'text-right pl-0',
    sortable: false,
  },
])

function hasAtleastOneInvoiceAbility(): boolean {
  return userStore.hasAbilities([
    ABILITIES.DELETE_INVOICE,
    ABILITIES.EDIT_INVOICE,
    ABILITIES.VIEW_INVOICE,
    ABILITIES.SEND_INVOICE,
  ])
}

function hasAtleastOneEstimateAbility(): boolean {
  return userStore.hasAbilities([
    ABILITIES.CREATE_ESTIMATE,
    ABILITIES.EDIT_ESTIMATE,
    ABILITIES.VIEW_ESTIMATE,
    ABILITIES.SEND_ESTIMATE,
  ])
}
</script>

<template>
  <div>
    <div class="grid grid-cols-1 gap-6 mt-10 xl:grid-cols-2">
      <!-- Due Invoices -->
      <div
        v-if="userStore.hasAbilities(ABILITIES.VIEW_INVOICE)"
        class="due-invoices"
      >
        <div class="relative z-10 flex items-center justify-between mb-3">
          <h6 class="mb-0 text-lg font-semibold leading-normal text-heading">
            {{ $t('dashboard.recent_invoices_card.title') }}
          </h6>

          <BaseButton
            size="sm"
            variant="primary-outline"
            @click="$router.push('/admin/invoices')"
          >
            {{ $t('dashboard.recent_invoices_card.view_all') }}
          </BaseButton>
        </div>

        <BaseTable
          :data="dashboardStore.recentDueInvoices"
          :columns="dueInvoiceColumns"
          :loading="!dashboardStore.isDashboardDataLoaded"
        >
          <template #cell-user="{ row }">
            <router-link
              :to="{ path: `invoices/${row.data.id}/view` }"
              class="font-medium text-primary-500"
            >
              {{ row.data.customer.name }}
            </router-link>
          </template>

          <template #cell-due_amount="{ row }">
            <BaseFormatMoney
              :amount="row.data.due_amount"
              :currency="row.data.customer.currency"
            />
          </template>

          <template
            v-if="hasAtleastOneInvoiceAbility()"
            #cell-actions="{ row }"
          >
            <InvoiceDropdown :row="row.data" :table="invoiceTableComponent" />
          </template>
        </BaseTable>
      </div>

      <!-- Recent Estimates -->
      <div
        v-if="userStore.hasAbilities(ABILITIES.VIEW_ESTIMATE)"
        class="recent-estimates"
      >
        <div class="relative z-10 flex items-center justify-between mb-3">
          <h6 class="mb-0 text-lg font-semibold leading-normal text-heading">
            {{ $t('dashboard.recent_estimate_card.title') }}
          </h6>

          <BaseButton
            variant="primary-outline"
            size="sm"
            @click="$router.push('/admin/estimates')"
          >
            {{ $t('dashboard.recent_estimate_card.view_all') }}
          </BaseButton>
        </div>

        <BaseTable
          :data="dashboardStore.recentEstimates"
          :columns="recentEstimateColumns"
          :loading="!dashboardStore.isDashboardDataLoaded"
        >
          <template #cell-user="{ row }">
            <router-link
              :to="{ path: `estimates/${row.data.id}/view` }"
              class="font-medium text-primary-500"
            >
              {{ row.data.customer.name }}
            </router-link>
          </template>

          <template #cell-total="{ row }">
            <BaseFormatMoney
              :amount="row.data.total"
              :currency="row.data.customer.currency"
            />
          </template>

          <template
            v-if="hasAtleastOneEstimateAbility()"
            #cell-actions="{ row }"
          >
            <EstimateDropdown :row="row.data" :table="estimateTableComponent" />
          </template>
        </BaseTable>
      </div>
    </div>
  </div>
</template>
