<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '../store'
import { useUserStore } from '../../../../stores/user.store'
import InvoiceDropdown from '@/scripts/features/company/invoices/components/InvoiceDropdown.vue'
import EstimateDropdown from '@/scripts/features/company/estimates/components/EstimateDropdown.vue'

import type { ColumnDef } from '@/scripts/components/table/DataTable.vue'

type TableColumn = Omit<ColumnDef, 'label'> & { label?: string }

const ABILITIES = {
  VIEW_INVOICE: 'view-invoice',
  CREATE_INVOICE: 'create-invoice',
  EDIT_INVOICE: 'edit-invoice',
  DELETE_INVOICE: 'delete-invoice',
  SEND_INVOICE: 'send-invoice',
  CREATE_PAYMENT: 'create-payment',
  VIEW_ESTIMATE: 'view-estimate',
  CREATE_ESTIMATE: 'create-estimate',
  EDIT_ESTIMATE: 'edit-estimate',
  DELETE_ESTIMATE: 'delete-estimate',
  SEND_ESTIMATE: 'send-estimate',
} as const

const dashboardStore = useDashboardStore()
const { t } = useI18n()
const userStore = useUserStore()

const invoiceTableComponent = ref<InstanceType<typeof Object> | null>(null)
const estimateTableComponent = ref<InstanceType<typeof Object> | null>(null)

const dueInvoiceColumns = computed<TableColumn[]>(() => [
  {
    key: 'user',
    label: t('dashboard.recent_invoices_card.customer'),
    mobile: 'title',
  },
  {
    key: 'formattedDueDate',
    label: t('dashboard.recent_invoices_card.due_on'),
    mobile: 'subtitle',
  },
  {
    key: 'due_amount',
    label: t('dashboard.recent_invoices_card.amount_due'),
    align: 'end',
    mobile: 'trailing',
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium w-12',
    thClass: 'text-right',
    sortable: false,
    mobile: 'actions',
  },
])

const recentEstimateColumns = computed<TableColumn[]>(() => [
  {
    key: 'user',
    label: t('dashboard.recent_estimate_card.customer'),
    mobile: 'title',
  },
  {
    key: 'formattedEstimateDate',
    label: t('dashboard.recent_estimate_card.date'),
    mobile: 'subtitle',
  },
  {
    key: 'total',
    label: t('dashboard.recent_estimate_card.amount_due'),
    align: 'end',
    mobile: 'trailing',
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium w-12',
    thClass: 'text-right',
    sortable: false,
    mobile: 'actions',
  },
])

function invoiceLink(row: { id?: number | string }): string {
  return `/admin/invoices/${row.id}/view`
}

function estimateLink(row: { id?: number | string }): string {
  return `/admin/estimates/${row.id}/view`
}

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

// Invoice ability props
const canViewInvoice = computed(() => userStore.hasAbilities(ABILITIES.VIEW_INVOICE))
const canCreateInvoice = computed(() => userStore.hasAbilities(ABILITIES.CREATE_INVOICE))
const canEditInvoice = computed(() => userStore.hasAbilities(ABILITIES.EDIT_INVOICE))
const canDeleteInvoice = computed(() => userStore.hasAbilities(ABILITIES.DELETE_INVOICE))
const canSendInvoice = computed(() => userStore.hasAbilities(ABILITIES.SEND_INVOICE))
const canCreatePayment = computed(() => userStore.hasAbilities(ABILITIES.CREATE_PAYMENT))

// Estimate ability props
const canViewEstimate = computed(() => userStore.hasAbilities(ABILITIES.VIEW_ESTIMATE))
const canCreateEstimate = computed(() => userStore.hasAbilities(ABILITIES.CREATE_ESTIMATE))
const canEditEstimate = computed(() => userStore.hasAbilities(ABILITIES.EDIT_ESTIMATE))
const canDeleteEstimate = computed(() => userStore.hasAbilities(ABILITIES.DELETE_ESTIMATE))
const canSendEstimate = computed(() => userStore.hasAbilities(ABILITIES.SEND_ESTIMATE))
const canCreateInvoiceFromEstimate = computed(() => userStore.hasAbilities(ABILITIES.CREATE_INVOICE))
</script>

<template>
  <div>
    <div class="grid grid-cols-1 gap-5 md:gap-6 xl:grid-cols-2">
      <!-- Due Invoices -->
      <div
        v-if="userStore.hasAbilities(ABILITIES.VIEW_INVOICE)"
        class="due-invoices"
      >
        <div class="flex items-center justify-between mb-3">
          <h2 class="flex items-center gap-2.5 font-semibold text-section text-heading">
            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
              <BaseIcon name="DocumentTextIcon" class="w-4.5 h-4.5" />
            </span>
            {{ $t('dashboard.recent_invoices_card.title') }}
          </h2>

          <router-link
            to="/admin/invoices"
            class="text-sm font-medium rounded-md text-primary-600 hover:text-primary-700 focus-visible:outline-2"
          >
            {{ $t('dashboard.recent_invoices_card.view_all') }}
          </router-link>
        </div>

        <BaseTable
          :data="dashboardStore.recentDueInvoices"
          :columns="dueInvoiceColumns"
          :loading="!dashboardStore.isDashboardDataLoaded"
          :row-to="invoiceLink"
        >
          <template #cell-user="{ row }">
            <router-link
              :to="{ path: `invoices/${row.data.id}/view` }"
              class="font-medium text-heading hover:text-primary-600"
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
            <InvoiceDropdown
              :row="row.data"
              :table="invoiceTableComponent"
              :can-edit="canEditInvoice"
              :can-view="canViewInvoice"
              :can-create="canCreateInvoice"
              :can-delete="canDeleteInvoice"
              :can-send="canSendInvoice"
              :can-create-payment="canCreatePayment"
              :can-create-estimate="canCreateEstimate"
            />
          </template>
        </BaseTable>
      </div>

      <!-- Recent Estimates -->
      <div
        v-if="userStore.hasAbilities(ABILITIES.VIEW_ESTIMATE)"
        class="recent-estimates"
      >
        <div class="flex items-center justify-between mb-3">
          <h2 class="flex items-center gap-2.5 font-semibold text-section text-heading">
            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
              <BaseIcon name="DocumentIcon" class="w-4.5 h-4.5" />
            </span>
            {{ $t('dashboard.recent_estimate_card.title') }}
          </h2>

          <router-link
            to="/admin/estimates"
            class="text-sm font-medium rounded-md text-primary-600 hover:text-primary-700 focus-visible:outline-2"
          >
            {{ $t('dashboard.recent_estimate_card.view_all') }}
          </router-link>
        </div>

        <BaseTable
          :data="dashboardStore.recentEstimates"
          :columns="recentEstimateColumns"
          :loading="!dashboardStore.isDashboardDataLoaded"
          :row-to="estimateLink"
        >
          <template #cell-user="{ row }">
            <router-link
              :to="{ path: `estimates/${row.data.id}/view` }"
              class="font-medium text-heading hover:text-primary-600"
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
            <EstimateDropdown
              :row="row.data"
              :table="estimateTableComponent"
              :can-edit="canEditEstimate"
              :can-view="canViewEstimate"
              :can-create="canCreateEstimate"
              :can-delete="canDeleteEstimate"
              :can-send="canSendEstimate"
              :can-create-invoice="canCreateInvoiceFromEstimate"
            />
          </template>
        </BaseTable>
      </div>
    </div>
  </div>
</template>
