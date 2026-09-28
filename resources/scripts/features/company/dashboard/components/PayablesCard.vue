<template>
  <BaseCard v-if="summary" container-class="p-5 md:p-6">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-2">
        <i18n-t
          keypath="purchases.payables_as_of"
          tag="h2"
          class="font-semibold text-section text-heading"
          ><template #date
            ><PurchaseDate :value="summary.as_of_date" /></template
        ></i18n-t>
        <BaseHelpPopover
          :title="$t('purchases.payables_today')"
          :text="$t('purchases.payables_help')"
        />
      </div>
      <div class="flex flex-wrap gap-4 text-sm font-medium text-primary-600">
        <router-link to="/admin/bills">{{ $t('purchases.bills') }}</router-link>
        <router-link
          v-if="user.hasAbilities('view-financial-reports')"
          :to="{ path: '/admin/reports', query: { report: 'purchases' } }"
          >{{ $t('purchases.report') }}</router-link
        >
      </div>
    </div>
    <dl class="grid grid-cols-2 gap-5 lg:grid-cols-4">
      <div v-for="field in balances" :key="field">
        <dt class="text-sm text-muted">{{ $t(`purchases.${field}`) }}</dt>
        <dd class="mt-1 text-xl font-semibold tabular text-heading">
          <BaseFormatMoney
            :amount="summary[field]"
            :currency="company.selectedCompanyCurrency"
          />
        </dd>
      </div>
    </dl>
    <dl
      class="mt-5 flex flex-wrap gap-x-8 gap-y-3 border-t border-line-light pt-4 text-sm"
    >
      <div
        v-for="field in available"
        :key="field"
        class="flex flex-wrap gap-x-2"
      >
        <dt class="text-muted">{{ $t(`purchases.${field}`) }}</dt>
        <dd class="font-medium text-heading">
          <BaseFormatMoney
            :amount="summary[field]"
            :currency="company.selectedCompanyCurrency"
          />
        </dd>
      </div>
    </dl>
  </BaseCard>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useDashboardStore } from '../store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useUserStore } from '@/scripts/stores/user.store'
import BaseHelpPopover from '@/scripts/components/base/BaseHelpPopover.vue'
import PurchaseDate from '../../purchases/components/PurchaseDate.vue'
const dashboard = useDashboardStore(),
  company = useCompanyStore(),
  user = useUserStore()
const summary = computed(() => dashboard.payables)
const balances = ['outstanding', 'overdue', 'due_soon', 'due_later'] as const
const available = ['available_advances', 'available_credits'] as const
</script>
