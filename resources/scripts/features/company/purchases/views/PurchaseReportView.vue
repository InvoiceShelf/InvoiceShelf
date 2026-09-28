<template>
  <BasePage>
    <BasePageHeader
      :help="$t('page_help.purchases_report')"
      :title="$t('purchases.report')"
    >
      <BaseBreadcrumb
        ><BaseBreadcrumbItem
          :title="$t('navigation.reports')"
          to="/admin/reports" /><BaseBreadcrumbItem
          :title="$t('purchases.report')"
          to="#"
          active
      /></BaseBreadcrumb>
    </BasePageHeader>
    <BaseCard container-class="p-4 md:p-5">
      <form class="flex flex-wrap items-end gap-4" @submit.prevent="load">
        <BaseInputGroup
          :label="$t('purchases.from_date')"
          class="max-w-xs"
          required
          ><BaseDatePicker v-model="from" required
        /></BaseInputGroup>
        <BaseInputGroup
          :label="$t('purchases.to_date')"
          class="max-w-xs"
          required
          ><BaseDatePicker v-model="to" required
        /></BaseInputGroup>
        <BaseButton type="submit" :loading="loading">{{
          $t('purchases.update_report')
        }}</BaseButton>
      </form>
    </BaseCard>
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
          <h2 class="font-semibold text-section text-heading">
            {{ $t('purchases.payables_today') }}
          </h2>
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
  </BasePage>
</template>
<script setup lang="ts">
import BaseHelpPopover from '@/scripts/components/base/BaseHelpPopover.vue'
import PurchaseDate from '../components/PurchaseDate.vue'
import { ref, onMounted, computed } from 'vue'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type { PurchaseReport } from '@/scripts/types/domain/purchase'
import { localDate, purchaseError } from '../helpers'
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
const today = localDate(),
  from = ref(`${today.slice(0, 4)}-01-01`),
  to = ref(today),
  report = ref<PurchaseReport | null>(null),
  loading = ref(false),
  error = ref('')
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
async function load() {
  loading.value = true
  error.value = ''
  try {
    report.value = await purchaseService.report(from.value, to.value)
  } catch (e) {
    error.value = purchaseError(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>
