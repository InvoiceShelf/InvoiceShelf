<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '../../../../stores/global.store'
import SalesReportView from './SalesReportView.vue'
import ProfitLossReportView from './ProfitLossReportView.vue'
import ExpensesReportView from './ExpensesReportView.vue'
import TaxReportView from './TaxReportView.vue'

const globalStore = useGlobalStore()
const { t } = useI18n()
const reportHelp = ref('sales_report')
function selectReportHelp(tab: { title: string }) {
  const helpByTitle: Record<string, string> = {
    [t('reports.sales.sales')]: 'sales_report',
    [t('reports.profit_loss.profit_loss')]: 'profit_loss_report',
    [t('reports.expenses.expenses')]: 'expenses_report',
    [t('reports.taxes.taxes')]: 'tax_report',
  }
  reportHelp.value = helpByTitle[tab.title] || 'reports'
}

function onDownload(): void {
  if (globalStore.downloadReport) {
    globalStore.downloadReport()
  }
}
</script>

<template>
  <BasePage>
    <BasePageHeader :help="$t(`page_help.${reportHelp}`)" :title="$t('reports.report', 2)">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          to="/admin/dashboard"
        />
        <BaseBreadcrumbItem
          :title="$t('reports.report', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton variant="primary" class="ms-4" @click="onDownload">
          <template #left="slotProps">
            <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
          </template>
          {{ $t('reports.download_pdf') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <router-link to="/admin/reports/purchases" class="mb-4 inline-block text-sm text-primary-600">{{ $t('purchases.report') }}</router-link>

    <BaseTabGroup class="p-2" @change="selectReportHelp">
      <BaseTab
        :title="$t('reports.sales.sales')"
        tab-panel-container="px-0 py-0"
      >
        <SalesReportView />
      </BaseTab>

      <BaseTab
        :title="$t('reports.profit_loss.profit_loss')"
        tab-panel-container="px-0 py-0"
      >
        <ProfitLossReportView />
      </BaseTab>

      <BaseTab
        :title="$t('reports.expenses.expenses')"
        tab-panel-container="px-0 py-0"
      >
        <ExpensesReportView />
      </BaseTab>

      <BaseTab
        :title="$t('reports.taxes.taxes')"
        tab-panel-container="px-0 py-0"
      >
        <TaxReportView />
      </BaseTab>
    </BaseTabGroup>
  </BasePage>
</template>
