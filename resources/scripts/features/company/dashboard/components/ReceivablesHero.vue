<template>
  <section
    class="
      relative p-5 overflow-hidden shadow-card rounded-2xl md:p-7 isolate
      bg-linear-to-br from-hero-from to-hero-to text-chrome-fg
    "
    :aria-labelledby="headingId"
  >
    <!-- A soft light source in the corner, so the panel reads as a surface rather than a flat fill -->
    <div
      class="absolute rounded-full pointer-events-none -z-10 -right-28 -top-32 w-96 h-96 bg-chrome-fg/10 blur-3xl"
      aria-hidden="true"
    />

    <template v-if="loaded">
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <h2 :id="headingId" class="flex items-center gap-2.5 text-sm font-medium text-chrome-fg/80">
            <span
              class="flex items-center justify-center w-9 h-9 rounded-xl bg-chrome-fg/12 ring-1 ring-inset ring-chrome-fg/15 text-chrome-fg"
              aria-hidden="true"
            >
              <BaseIcon name="WalletIcon" class="w-5 h-5" />
            </span>
            {{ $t('dashboard.receivables.title') }}
          </h2>
          <p class="mt-4 font-semibold leading-none tracking-tight text-[2.5rem] md:text-[3.25rem]">
            <BaseFormatMoney
              :amount="summary.outstanding"
              :currency="companyStore.selectedCompanyCurrency"
              proportional
            />
          </p>
          <p v-if="summary.outstanding > 0" class="mt-3 text-sm text-chrome-fg/75">
            {{ $t('dashboard.receivables.on_invoices', { count: summary.outstanding_count }, summary.outstanding_count) }}
          </p>
          <p v-else class="mt-3 text-sm text-chrome-fg/75">
            {{ $t('dashboard.receivables.nothing_owed') }}
          </p>
        </div>

        <router-link
          v-if="canViewInvoices"
          to="/admin/invoices"
          class="
            inline-flex items-center self-start h-9 px-3.5 text-sm font-medium transition-colors border rounded-lg shrink-0
            border-chrome-fg/20 bg-chrome-fg/10 hover:bg-chrome-fg/15 focus-visible:outline-2 focus-visible:outline-chrome-fg
          "
        >
          {{ $t('dashboard.receivables.view_invoices') }}
        </router-link>
      </div>

      <template v-if="summary.outstanding > 0">
        <!-- The aging bar: each segment's width is its share of what is owed -->
        <div
          class="flex w-full h-3 gap-0.5 mt-6 overflow-hidden rounded-full"
          role="img"
          :aria-label="barLabel"
        >
          <div
            v-for="bucket in visibleBuckets"
            :key="bucket.key"
            :class="bucket.fill"
            class="h-full min-w-1 first:rounded-l-full last:rounded-r-full"
            :style="{ width: `${bucket.share}%` }"
          />
        </div>

        <dl class="grid gap-3 mt-5 sm:grid-cols-3 sm:gap-6">
          <div
            v-for="bucket in buckets"
            :key="bucket.key"
            class="flex items-center justify-between gap-3 sm:block"
          >
            <dt class="flex items-center gap-2 text-sm text-chrome-fg/75">
              <span :class="bucket.fill" class="w-2.5 h-2.5 rounded-full shrink-0" aria-hidden="true" />
              {{ bucket.label }}
            </dt>
            <dd class="text-sm font-semibold sm:mt-1 sm:pl-4.5 sm:text-base">
              <BaseFormatMoney
                :amount="bucket.amount"
                :currency="companyStore.selectedCompanyCurrency"
              />
              <span
                v-if="bucket.count !== null && bucket.amount > 0"
                class="ml-1.5 text-sm font-normal text-chrome-fg/70"
              >
                {{ $t('dashboard.receivables.invoice_count', { count: bucket.count }, bucket.count) }}
              </span>
            </dd>
          </div>
        </dl>
      </template>
    </template>

    <BaseContentPlaceholders v-else :rounded="true" class="opacity-40">
      <BaseContentPlaceholdersText class="w-24 h-4" :lines="1" />
      <BaseContentPlaceholdersText class="w-64 h-12 mt-3" :lines="1" />
      <BaseContentPlaceholdersText class="w-full h-3 mt-8" :lines="1" />
    </BaseContentPlaceholders>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '../store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { formatMoney } from '@/scripts/utils/format-money'
import { ABILITIES } from '@/scripts/config/abilities'

interface Bucket {
  key: string
  label: string
  amount: number
  count: number | null
  fill: string
  share: number
}

const headingId = 'dashboard-receivables'

const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()
const userStore = useUserStore()
const { t } = useI18n()

const loaded = computed<boolean>(() => dashboardStore.isDashboardDataLoaded)
const summary = computed(() => dashboardStore.receivables)
const canViewInvoices = computed<boolean>(() => userStore.hasAbilities(ABILITIES.VIEW_INVOICE))

const buckets = computed<Bucket[]>(() => {
  const total = summary.value.outstanding || 1
  const share = (amount: number): number => (amount / total) * 100

  return [
    {
      key: 'overdue',
      label: t('dashboard.receivables.overdue'),
      amount: summary.value.overdue,
      count: summary.value.overdue_count,
      fill: 'bg-status-red-fill',
      share: share(summary.value.overdue),
    },
    {
      key: 'due_soon',
      label: t('dashboard.receivables.due_soon'),
      amount: summary.value.due_soon,
      count: null,
      fill: 'bg-status-yellow-fill',
      share: share(summary.value.due_soon),
    },
    {
      key: 'due_later',
      label: t('dashboard.receivables.due_later'),
      amount: summary.value.due_later,
      count: null,
      fill: 'bg-chrome-fg/80',
      share: share(summary.value.due_later),
    },
  ]
})

const visibleBuckets = computed<Bucket[]>(() => buckets.value.filter((bucket) => bucket.amount > 0))

// Screen readers get the bar as a sentence
const barLabel = computed<string>(() => {
  const currency = companyStore.selectedCompanyCurrency ?? undefined

  return buckets.value
    .map((bucket) => `${bucket.label}: ${formatMoney(bucket.amount, currency)}`)
    .join(', ')
})
</script>
