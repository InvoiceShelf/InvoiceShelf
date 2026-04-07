<script setup lang="ts">
import { useGlobalStore } from '../../../../stores/global.store'
import SalesReportView from './SalesReportView.vue'
import ProfitLossReportView from './ProfitLossReportView.vue'
import ExpensesReportView from './ExpensesReportView.vue'
import TaxReportView from './TaxReportView.vue'

const globalStore = useGlobalStore()

function onDownload(): void {
  if (globalStore.downloadReport) {
    globalStore.downloadReport()
  }
}
</script>

<template>
  <BasePage>
    <BasePageHeader :title="$t('reports.report', 2)">
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
        <BaseButton variant="primary" class="ml-4" @click="onDownload">
          <template #left="slotProps">
            <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
          </template>
          {{ $t('reports.download_pdf') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseTabGroup class="p-2">
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
