<template>
  <div class="space-y-6 pt-6">
    <BaseCard container-class="p-4 md:p-5">
      <form class="flex flex-wrap items-end gap-4" @submit.prevent="load()">
        <BaseInputGroup
          :label="$t('reports.sales.date_range')"
          class="w-full md:w-72"
        >
          <BasePeriodPicker
            v-model="period"
            :presets="presets"
            block
            position="bottom-start"
          />
        </BaseInputGroup>
        <BaseInputGroup
          v-if="canViewSuppliers"
          :label="$t('purchases.supplier')"
          class="w-full md:w-72"
        >
          <BaseMultiselect
            v-model="supplierId"
            :options="searchSuppliers"
            value-prop="id"
            label="name"
            searchable
            :filter-results="false"
            resolve-on-load
            :delay="250"
            :placeholder="$t('purchases.all_suppliers')"
          />
        </BaseInputGroup>
        <BaseButton type="submit" :loading="loading">{{
          $t('reports.update_report')
        }}</BaseButton>
        <BaseButton
          class="md:hidden"
          type="button"
          variant="white"
          @click="viewPdf"
          >{{ $t('reports.view_pdf') }}</BaseButton
        >
      </form>
      <p
        v-if="!canViewSuppliers && report?.supplier"
        class="mt-3 text-sm text-muted"
      >
        {{ $t('purchases.supplier') }}: {{ report.supplier.name }}
        <button
          type="button"
          class="ms-3 text-primary-600"
          @click="clearSupplier"
        >
          {{ $t('purchases.all_suppliers') }}
        </button>
      </p>
    </BaseCard>
    <p v-if="loading" role="status" class="text-sm text-muted">
      {{ $t('general.loading') }}
    </p>
    <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>
    <template v-if="report">
      <section class="space-y-4">
        <div class="flex items-center gap-2">
          <h2 class="font-semibold text-section text-heading">
            {{ $t('purchases.cash_movement') }}
          </h2>
          <BaseHelpPopover
            :title="$t('purchases.cash_movement')"
            :text="$t('purchases.cash_help')"
          />
        </div>
        <BaseStatStrip :columns="4"
          ><BaseStat
            v-for="field in cashFields"
            :key="field"
            :label="$t(`purchases.${field}`)"
            :emphasis="field === 'net_cash_out'"
            ><BaseFormatMoney
              :amount="report.cash[field]"
              :currency="report.currency" /></BaseStat
        ></BaseStatStrip>
      </section>
      <section class="space-y-4">
        <div class="flex items-center gap-2">
          <h2 class="font-semibold text-section text-heading">
            {{ $t('purchases.purchase_costs') }}
          </h2>
          <BaseHelpPopover
            :title="$t('purchases.purchase_costs')"
            :text="$t('purchases.cost_help')"
          />
        </div>
        <BaseStatStrip :columns="3"
          ><BaseStat
            v-for="field in costFields"
            :key="field"
            :label="$t(`purchases.cost_${field}`)"
            :emphasis="field === 'gross'"
            ><BaseFormatMoney
              :amount="report.purchases[field]"
              :currency="report.currency" /></BaseStat
        ></BaseStatStrip>
        <BaseTable :data="report.categories" :columns="categoryColumns">
          <template #cell-net="{ row }"
            ><BaseFormatMoney
              :amount="row.data.net"
              :currency="report.currency"
          /></template>
          <template #cell-tax="{ row }"
            ><BaseFormatMoney
              :amount="row.data.tax"
              :currency="report.currency"
          /></template>
          <template #cell-gross="{ row }"
            ><BaseFormatMoney
              :amount="row.data.gross"
              :currency="report.currency"
          /></template>
        </BaseTable>
      </section>
      <section class="space-y-4">
        <div class="flex items-center gap-2">
          <i18n-t
            keypath="purchases.payables_as_of"
            tag="h2"
            class="font-semibold text-section text-heading"
            ><template #date
              ><PurchaseDate :value="report.payables.as_of_date" /></template
          ></i18n-t>
          <BaseHelpPopover
            :title="$t('purchases.payables_today')"
            :text="$t('purchases.payables_help')"
          />
        </div>
        <BaseStatStrip :columns="3"
          ><BaseStat
            v-for="field in payableFields"
            :key="field"
            :label="$t(`purchases.${field}`)"
            ><BaseFormatMoney
              :amount="report.payables[field]"
              :currency="report.currency" /></BaseStat
        ></BaseStatStrip>
        <BaseTable
          :data="report.aging"
          :columns="agingColumns"
          :row-to="billLink"
        >
          <template #cell-due_date="{ row }"
            ><PurchaseDate :value="row.data.due_date"
          /></template>
          <template #cell-number="{ row }"
            ><router-link
              :to="`/admin/bills/${row.data.id}/view`"
              class="text-primary-600"
              >{{ row.data.number }}</router-link
            ></template
          >
          <template #cell-due_amount="{ row }"
            ><BaseFormatMoney
              :amount="row.data.due_amount || 0"
              :currency="row.data.currency"
          /></template>
        </BaseTable>
      </section>
      <section class="space-y-4">
        <div class="flex items-center gap-2">
          <h2 class="font-semibold text-section text-heading">
            {{ $t('purchases.purchase_taxes') }}
          </h2>
          <BaseHelpPopover
            :title="$t('purchases.purchase_taxes')"
            :text="$t('purchases.tax_basis_help')"
          />
        </div>
        <BaseTable :data="report.taxes" :columns="taxColumns"
          ><template #cell-amount="{ row }"
            ><BaseFormatMoney
              :amount="row.data.amount"
              :currency="report.currency" /></template
        ></BaseTable>
      </section>
    </template>
    <ReportPdfPane ref="pdfPane" :path="null" class="hidden" />
  </div>
</template>
<script setup lang="ts">
import BaseHelpPopover from '@/scripts/components/base/BaseHelpPopover.vue'
import PurchaseDate from '../components/PurchaseDate.vue'
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useReportPeriod } from '../../reports/use-report-period'
import { useReportDownload } from '../../reports/useReportDownload'
import ReportPdfPane from '../../reports/components/ReportPdfPane.vue'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type { PurchaseReport } from '@/scripts/types/domain/purchase'
import { purchaseError } from '../helpers'
import { useI18n } from 'vue-i18n'
import type {
  ColumnDef,
  RowData,
} from '@/scripts/components/table/DataTable.vue'
const { t } = useI18n()
function billLink(row: RowData) {
  return `/admin/bills/${row.id}/view`
}
const categoryColumns = computed<ColumnDef[]>(() => [
  { key: 'name', label: t('purchases.category'), mobile: 'title' },
  ...(['net', 'tax', 'gross'] as const).map((field) => ({
    key: field,
    label: t(`purchases.cost_${field}`),
    align: 'end' as const,
    mobile: field === 'gross' ? ('trailing' as const) : (false as const),
  })),
])
const agingColumns = computed<ColumnDef[]>(() => [
  { key: 'number', label: t('purchases.bills'), mobile: 'title' },
  { key: 'supplier.name', label: t('purchases.supplier'), mobile: 'subtitle' },
  { key: 'due_date', label: t('purchases.due_date'), mobile: 'subtitle' },
  {
    key: 'due_amount',
    label: t('purchases.due'),
    mobile: 'trailing',
    align: 'end',
  },
])
const taxColumns = computed<ColumnDef[]>(() => [
  { key: 'name', label: t('purchases.purchase_taxes'), mobile: 'title' },
  {
    key: 'amount',
    label: t('purchases.amount'),
    mobile: 'trailing',
    align: 'end',
  },
])
const route = useRoute(),
  company = useCompanyStore(),
  user = useUserStore(),
  global = useGlobalStore()
const { period, presets, formData, syncUrl } = useReportPeriod()
const supplierId = ref<number | null>(Number(route.query.supplier_id) || null)
const canViewSuppliers = computed(() => user.hasAbilities('view-supplier'))
const report = ref<PurchaseReport | null>(null),
  loading = ref(false),
  error = ref('')
const pdfPane = ref<InstanceType<typeof ReportPdfPane> | null>(null)
let request = 0,
  requestedKey = ''
async function searchSuppliers(search: string) {
  const companyId = company.selectedCompany?.id
  const rows = await purchaseService.suppliers(search)
  if (supplierId.value && !rows.some((row) => row.id === supplierId.value))
    rows.unshift(await purchaseService.get('suppliers', supplierId.value))
  return companyId === company.selectedCompany?.id ? rows : []
}
function pdfPath() {
  const hash = company.selectedCompany?.unique_hash
  if (!hash) return null
  const params = new URLSearchParams({
    from_date: formData.from_date,
    to_date: formData.to_date,
  })
  if (supplierId.value) params.set('supplier_id', String(supplierId.value))
  return `/reports/purchases/${hash}?${params}`
}
global.downloadReport = useReportDownload(() => {
  void load()
  return pdfPath()
})
function viewPdf() {
  void load()
  void pdfPane.value?.view(pdfPath())
}
function clearSupplier() {
  supplierId.value = null
  void load()
}
const cashFields = [
  'direct_expenses',
  'supplier_payments',
  'supplier_refunds',
  'net_cash_out',
] as const
const costFields = ['net', 'tax', 'gross'] as const
const payableFields = [
  'outstanding',
  'overdue',
  'due_soon',
  'due_later',
  'available_advances',
  'available_credits',
] as const
async function load(persist = true, force = true) {
  const companyId = company.selectedCompany?.id
  if (!companyId) return
  const params = {
    from: formData.from_date,
    to: formData.to_date,
    supplier: supplierId.value || undefined,
  }
  const key = JSON.stringify([companyId, params])
  if (!force && key === requestedKey) return
  requestedKey = key
  const ticket = ++request
  loading.value = true
  report.value = null
  error.value = ''
  if (persist)
    void syncUrl({
      supplier_id: params.supplier ? String(params.supplier) : undefined,
    })
  try {
    const data = await purchaseService.report(
      params.from,
      params.to,
      params.supplier,
    )
    if (ticket === request && companyId === company.selectedCompany?.id)
      report.value = data
  } catch (e) {
    if (ticket === request) error.value = purchaseError(e)
  } finally {
    if (ticket === request) loading.value = false
  }
}
watch(
  () => [
    route.query.from_date,
    route.query.to_date,
    route.query.supplier_id,
    company.selectedCompany?.id,
  ],
  () => {
    supplierId.value = Number(route.query.supplier_id) || null
    void load(false, false)
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  request++
})
</script>
