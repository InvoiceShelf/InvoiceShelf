<script setup lang="ts">
import { provide } from 'vue'
import InvoicesTab from '@/scripts/features/company/settings/components/InvoicesTab.vue'
import EstimatesTab from '@/scripts/features/company/settings/components/EstimatesTab.vue'
import PaymentsTab from '@/scripts/features/company/settings/components/PaymentsTab.vue'
import ItemsTab from '@/scripts/features/company/settings/components/ItemsTab.vue'

provide('utils', {
  mergeSettings(target: Record<string, unknown>, source: Record<string, unknown>): void {
    Object.keys(source).forEach((key) => {
      if (key in target) {
        target[key] = source[key]
      }
    })
  },
})
</script>

<template>
  <div class="relative">
    <BaseCard container-class="px-4 py-5 sm:px-8 sm:py-2">
      <BaseTabGroup>
        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.invoices.title')"
        >
          <InvoicesTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.estimates.title')"
        >
          <EstimatesTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.payments.title')"
        >
          <PaymentsTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.customization.items.title')"
        >
          <ItemsTab />
        </BaseTab>
      </BaseTabGroup>
    </BaseCard>
  </div>
</template>
