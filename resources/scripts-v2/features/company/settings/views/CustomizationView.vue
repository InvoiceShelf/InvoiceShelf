<script setup lang="ts">
import InvoicesTab from '@v2/features/company/settings/components/InvoicesTab.vue'
import EstimatesTab from '@v2/features/company/settings/components/EstimatesTab.vue'
import PaymentsTab from '@v2/features/company/settings/components/PaymentsTab.vue'
import ItemsTab from '@v2/features/company/settings/components/ItemsTab.vue'
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
