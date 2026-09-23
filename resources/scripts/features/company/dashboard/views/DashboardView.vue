<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../../../stores/user.store'
import { useDashboardStore } from '../store'
import { ABILITIES } from '@/scripts/config/abilities'
import { formatPeriodRange, periodParams, yearPresets } from '@/scripts/utils/period'
import ReceivablesHero from '../components/ReceivablesHero.vue'
import DashboardChart from '../components/DashboardChart.vue'
import DashboardTable from '../components/DashboardTable.vue'
import SendInvoiceModal from '@/scripts/features/company/invoices/components/SendInvoiceModal.vue'
import CreditNoteModal from '@/scripts/features/company/invoices/components/CreditNoteModal.vue'
import SendEstimateModal from '@/scripts/features/company/estimates/components/SendEstimateModal.vue'

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

const presets = computed(() => yearPresets(t))

// The dates behind the current choice, e.g. what "This year" means for a
// fiscal year that opens in April
const periodSummary = computed<string>(() => {
  const resolved = dashboardStore.resolvedPeriod

  return resolved
    ? formatPeriodRange(resolved.from, resolved.to, userStore.currentUserSettings.language)
    : ''
})

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

function load(): void {
  if (userStore.hasAbilities('dashboard')) {
    void dashboardStore.loadData(periodParams(dashboardStore.period))
  }
}

watch(() => dashboardStore.period, load, { immediate: true })

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
        <BasePeriodPicker
          v-model="dashboardStore.period"
          :presets="presets"
          :summary="periodSummary"
        />
      </template>
    </BasePageHeader>

    <div class="flex flex-col gap-5 md:gap-6">
      <!-- A failed load says so, instead of leaving the placeholders up -->
      <div
        v-if="dashboardStore.loadError"
        class="flex flex-wrap items-center justify-between gap-3 p-4 border rounded-xl border-line-light bg-surface"
        role="alert"
      >
        <span class="text-sm text-body">{{ $t('dashboard.load_failed') }}</span>
        <BaseButton size="sm" variant="primary-outline" @click="load">
          {{ $t('general.retry') }}
        </BaseButton>
      </div>

      <template v-if="dashboardStore.isDashboardDataLoaded || !dashboardStore.loadError">
        <ReceivablesHero v-if="userStore.hasAbilities(ABILITIES.VIEW_INVOICE)" />

        <div
          v-if="counts.some((count) => count.value > 0) && dashboardStore.isDashboardDataLoaded"
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
      </template>
    </div>
  </BasePage>

  <SendInvoiceModal />
  <CreditNoteModal />
  <SendEstimateModal />
</template>
