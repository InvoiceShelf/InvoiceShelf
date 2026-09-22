<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../../../stores/user.store'
import { useDashboardStore } from '../store'
import { ABILITIES } from '@/scripts/config/abilities'
import ReceivablesHero from '../components/ReceivablesHero.vue'
import DashboardChart from '../components/DashboardChart.vue'
import DashboardTable from '../components/DashboardTable.vue'
import SendInvoiceModal from '@/scripts/features/company/invoices/components/SendInvoiceModal.vue'
import CreditNoteModal from '@/scripts/features/company/invoices/components/CreditNoteModal.vue'
import SendEstimateModal from '@/scripts/features/company/estimates/components/SendEstimateModal.vue'

type Period = 'this_year' | 'previous_year'

interface Count {
  key: string
  to: string
  value: number
  label: string
}

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const dashboardStore = useDashboardStore()
const { t } = useI18n()

const period = ref<Period>('this_year')

const periods = computed(() => [
  { value: 'this_year' as Period, label: t('dateRange.this_year') },
  { value: 'previous_year' as Period, label: t('dateRange.previous_year') },
])

// The customer, invoice and estimate counts: context, so they stay quiet
const counts = computed<Count[]>(() => {
  const stats = dashboardStore.stats
  const list: Count[] = []

  if (userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER)) {
    list.push({ key: 'customers', to: '/admin/customers', value: stats.totalCustomerCount, label: t('dashboard.counts.customers', stats.totalCustomerCount) })
  }
  if (userStore.hasAbilities(ABILITIES.VIEW_INVOICE)) {
    list.push({ key: 'invoices', to: '/admin/invoices', value: stats.totalInvoiceCount, label: t('dashboard.counts.invoices', stats.totalInvoiceCount) })
  }
  if (userStore.hasAbilities(ABILITIES.VIEW_ESTIMATE)) {
    list.push({ key: 'estimates', to: '/admin/estimates', value: stats.totalEstimateCount, label: t('dashboard.counts.estimates', stats.totalEstimateCount) })
  }

  return list
})

watch(
  period,
  (value) => {
    if (!userStore.hasAbilities('dashboard')) {
      return
    }

    void dashboardStore.loadData(value === 'previous_year' ? { previous_year: 1 } : undefined)
  },
  { immediate: true },
)

onMounted(() => {
  const meta = route.meta as { ability?: string; isOwner?: boolean }

  if (meta.ability && !userStore.hasAbilities(meta.ability)) {
    router.push({ name: 'settings.account' })
  } else if (meta.isOwner && !userStore.isOwner) {
    router.push({ name: 'settings.account' })
  }
})
</script>

<template>
  <BasePage>
    <BasePageHeader :title="$t('navigation.dashboard')" phone-actions="inline">
      <template #actions>
        <div
          class="inline-flex p-0.5 border rounded-lg bg-surface border-line-default"
          role="radiogroup"
          :aria-label="$t('dashboard.select_year')"
        >
          <button
            v-for="option in periods"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="period === option.value"
            :class="[
              'h-9 md:h-8 px-3 rounded-md text-sm font-medium transition-colors',
              period === option.value
                ? 'bg-surface-muted text-heading'
                : 'text-muted hover:text-heading',
            ]"
            @click="period = option.value"
          >
            {{ option.label }}
          </button>
        </div>
      </template>
    </BasePageHeader>

    <div class="flex flex-col gap-5 md:gap-6">
      <ReceivablesHero v-if="userStore.hasAbilities(ABILITIES.VIEW_INVOICE)" />

      <div
        v-if="counts.length && dashboardStore.isDashboardDataLoaded"
        class="flex flex-wrap items-center -mt-1 gap-x-6 gap-y-2 md:-mt-2"
      >
        <router-link
          v-for="count in counts"
          :key="count.key"
          :to="count.to"
          class="text-sm rounded-md text-muted hover:text-heading focus-visible:outline-2"
        >
          <span class="font-semibold tabular text-heading">{{ count.value }}</span>
          {{ count.label }}
        </router-link>
      </div>

      <DashboardChart />
      <DashboardTable />
    </div>
  </BasePage>

  <SendInvoiceModal />
  <CreditNoteModal />
  <SendEstimateModal />
</template>
