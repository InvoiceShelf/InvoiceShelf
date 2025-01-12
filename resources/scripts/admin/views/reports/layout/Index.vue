<template>
  <BasePage>
    <BasePageHeader :title="$t('reports.report', 2)">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem
          :title="$t('reports.report', 2)"
          to="/admin/reports"
          active
        />
      </BaseBreadcrumb>
      <template #actions>
        <BaseButton variant="primary" class="ml-4" @click="onDownload">
          <template #left="slotProps">
            <BaseIcon name="DownloadIcon" :class="slotProps.class" />
          </template>
          {{ $t('reports.download_pdf') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Tabs -->
    <BaseTabGroup class="p-2">
      <BaseTab
        :title="$t('reports.sales.sales')"
        tab-panel-container="px-0 py-0"
      >
        <SalesReport ref="report" />
      </BaseTab>
      <BaseTab
        :title="$t('reports.profit_loss.profit_loss')"
        tab-panel-container="px-0 py-0"
      >
        <ProfitLossReport ref="report" />
      </BaseTab>
      <BaseTab
        :title="$t('reports.expenses.expenses')"
        tab-panel-container="px-0 py-0"
      >
        <ExpenseReport ref="report" />
      </BaseTab>
      <BaseTab
        :title="$t('reports.taxes.taxes')"
        tab-panel-container="px-0 py-0"
      >
        <TaxReport ref="report" />
      </BaseTab>
    </BaseTabGroup>
  </BasePage>
</template>

<script setup>
import SalesReport from '../SalesReports.vue'
import ExpenseReport from '../ExpensesReport.vue'
import ProfitLossReport from '../ProfitLossReport.vue'
import TaxReport from '../TaxReport.vue'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import BaseTab from '@/scripts/components/base/BaseTab.vue'
import BaseTabGroup from '@/scripts/components/base/BaseTabGroup.vue'
import BaseIcon from '@/scripts/components/base/BaseIcon.vue'
import BaseButton from '@/scripts/components/base/BaseButton.vue'
import BaseBreadcrumbItem from '@/scripts/components/base/BaseBreadcrumbItem.vue'
import BaseBreadcrumb from '@/scripts/components/base/BaseBreadcrumb.vue'
import BasePageHeader from '@/scripts/components/base/BasePageHeader.vue'
import BasePage from '@/scripts/components/base/BasePage.vue'

const globalStore = useGlobalStore()

function onDownload() {
  globalStore.downloadReport()
}
</script>
