<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/stores/global.store'
import SalesReportView from './SalesReportView.vue'
import ProfitLossReportView from './ProfitLossReportView.vue'
import ExpensesReportView from './ExpensesReportView.vue'
import TaxReportView from './TaxReportView.vue'
import PurchaseReportView from '../../purchases/views/PurchaseReportView.vue'
const globalStore = useGlobalStore(),
  route = useRoute(),
  router = useRouter(),
  { t } = useI18n()
const keys = ['sales', 'profit-loss', 'expenses', 'purchases', 'taxes'] as const
const titles = computed(() => [
  t('reports.sales.sales'),
  t('reports.profit_loss.profit_loss'),
  t('reports.expenses.expenses'),
  t('reports.purchases'),
  t('reports.taxes.taxes'),
])
const selected = computed(() =>
  keys.includes(route.query.report as (typeof keys)[number])
    ? String(route.query.report)
    : 'sales',
)
const index = computed(() =>
  keys.indexOf(selected.value as (typeof keys)[number]),
)
const reportHelp = computed(
  () =>
    [
      'sales_report',
      'profit_loss_report',
      'expenses_report',
      'purchases_report',
      'tax_report',
    ][index.value],
)
function selectReport(tab: { title: string }) {
  const report = keys[titles.value.indexOf(tab.title)]
  if (report && report !== selected.value)
    router.push({ query: { ...route.query, report } })
}
watch(
  selected,
  () => {
    globalStore.downloadReport = null
  },
  { flush: 'sync' },
)
function onDownload() {
  globalStore.downloadReport?.()
}
</script>

<template>
  <BasePage>
    <BasePageHeader
      :help="$t(`page_help.${reportHelp}`)"
      :title="$t('reports.report', 2)"
    >
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('reports.report', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          variant="primary"
          class="ms-4"
          :disabled="!globalStore.downloadReport"
          @click="onDownload"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
          </template>
          {{ $t('reports.download_pdf') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseTabGroup
      :key="selected"
      :default-index="index"
      class="p-2"
      @change="selectReport"
    >
      <BaseTab
        :title="$t('reports.sales.sales')"
        tab-panel-container="px-0 py-0"
      >
        <SalesReportView v-if="selected === 'sales'" />
      </BaseTab>

      <BaseTab
        :title="$t('reports.profit_loss.profit_loss')"
        tab-panel-container="px-0 py-0"
      >
        <ProfitLossReportView v-if="selected === 'profit-loss'" />
      </BaseTab>

      <BaseTab
        :title="$t('reports.expenses.expenses')"
        tab-panel-container="px-0 py-0"
      >
        <ExpensesReportView v-if="selected === 'expenses'" />
      </BaseTab>

      <BaseTab
        :title="$t('reports.purchases')"
        tab-panel-container="px-0 py-0"
      >
        <PurchaseReportView v-if="selected === 'purchases'" />
      </BaseTab>
      <BaseTab
        :title="$t('reports.taxes.taxes')"
        tab-panel-container="px-0 py-0"
      >
        <TaxReportView v-if="selected === 'taxes'" />
      </BaseTab>
    </BaseTabGroup>
  </BasePage>
</template>
