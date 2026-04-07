<template>
  <BasePage>
    <!-- Stats Cards -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 xl:gap-8">
      <router-link
        :to="{ name: 'customer-portal.invoices' }"
        class="p-6 bg-surface border border-line-default rounded-lg hover:shadow-md transition-shadow"
      >
        <p class="text-sm font-medium text-muted">
          {{ $t('dashboard.cards.due_amount') }}
        </p>
        <div class="mt-2 text-2xl font-semibold text-heading">
          <BaseContentPlaceholdersText
            v-if="!store.getDashboardDataLoaded"
            :lines="1"
            class="w-24"
          />
          <BaseFormatMoney
            v-else
            :amount="store.totalDueAmount"
            :currency="store.currency"
          />
        </div>
      </router-link>

      <router-link
        :to="{ name: 'customer-portal.invoices' }"
        class="p-6 bg-surface border border-line-default rounded-lg hover:shadow-md transition-shadow"
      >
        <p class="text-sm font-medium text-muted">
          {{ store.invoiceCount <= 1 ? $t('dashboard.cards.invoices', 1) : $t('dashboard.cards.invoices', 2) }}
        </p>
        <div class="mt-2 text-2xl font-semibold text-heading">
          <BaseContentPlaceholdersText
            v-if="!store.getDashboardDataLoaded"
            :lines="1"
            class="w-16"
          />
          <span v-else>{{ store.invoiceCount }}</span>
        </div>
      </router-link>

      <router-link
        :to="{ name: 'customer-portal.estimates' }"
        class="p-6 bg-surface border border-line-default rounded-lg hover:shadow-md transition-shadow"
      >
        <p class="text-sm font-medium text-muted">
          {{ store.estimateCount <= 1 ? $t('dashboard.cards.estimates', 1) : $t('dashboard.cards.estimates', 2) }}
        </p>
        <div class="mt-2 text-2xl font-semibold text-heading">
          <BaseContentPlaceholdersText
            v-if="!store.getDashboardDataLoaded"
            :lines="1"
            class="w-16"
          />
          <span v-else>{{ store.estimateCount }}</span>
        </div>
      </router-link>

      <router-link
        :to="{ name: 'customer-portal.payments' }"
        class="p-6 bg-surface border border-line-default rounded-lg hover:shadow-md transition-shadow"
      >
        <p class="text-sm font-medium text-muted">
          {{ store.paymentCount <= 1 ? $t('dashboard.cards.payments', 1) : $t('dashboard.cards.payments', 2) }}
        </p>
        <div class="mt-2 text-2xl font-semibold text-heading">
          <BaseContentPlaceholdersText
            v-if="!store.getDashboardDataLoaded"
            :lines="1"
            class="w-16"
          />
          <span v-else>{{ store.paymentCount }}</span>
        </div>
      </router-link>
    </div>

    <!-- Recent Tables -->
    <div class="grid grid-cols-1 gap-6 mt-10 xl:grid-cols-2">
      <!-- Recent Invoices -->
      <div>
        <div class="relative z-10 flex items-center justify-between mb-3">
          <h6 class="mb-0 text-xl font-semibold leading-normal">
            {{ $t('dashboard.recent_invoices_card.title') }}
          </h6>
          <BaseButton
            size="sm"
            variant="primary-outline"
            @click="$router.push({ name: 'customer-portal.invoices' })"
          >
            {{ $t('dashboard.recent_invoices_card.view_all') }}
          </BaseButton>
        </div>

        <BaseTable
          :data="store.recentInvoices"
          :columns="dueInvoiceColumns"
          :loading="!store.getDashboardDataLoaded"
        >
          <template #cell-invoice_number="{ row }">
            <router-link
              :to="`/${store.companySlug}/customer/invoices/${row.data.id}/view`"
              class="font-medium text-primary-500"
            >
              {{ row.data.invoice_number }}
            </router-link>
          </template>

          <template #cell-paid_status="{ row }">
            <BasePaidStatusBadge :status="row.data.paid_status">
              <BaseInvoiceStatusLabel :status="row.data.paid_status" />
            </BasePaidStatusBadge>
          </template>

          <template #cell-due_amount="{ row }">
            <BaseFormatMoney
              :amount="row.data.due_amount"
              :currency="store.currency"
            />
          </template>
        </BaseTable>
      </div>

      <!-- Recent Estimates -->
      <div>
        <div class="relative z-10 flex items-center justify-between mb-3">
          <h6 class="mb-0 text-xl font-semibold leading-normal">
            {{ $t('dashboard.recent_estimate_card.title') }}
          </h6>
          <BaseButton
            variant="primary-outline"
            size="sm"
            @click="$router.push({ name: 'customer-portal.estimates' })"
          >
            {{ $t('dashboard.recent_estimate_card.view_all') }}
          </BaseButton>
        </div>

        <BaseTable
          :data="store.recentEstimates"
          :columns="recentEstimateColumns"
          :loading="!store.getDashboardDataLoaded"
        >
          <template #cell-estimate_number="{ row }">
            <router-link
              :to="`/${store.companySlug}/customer/estimates/${row.data.id}/view`"
              class="font-medium text-primary-500"
            >
              {{ row.data.estimate_number }}
            </router-link>
          </template>

          <template #cell-status="{ row }">
            <BaseEstimateStatusBadge :status="row.data.status" class="px-3 py-1">
              <BaseEstimateStatusLabel :status="row.data.status" />
            </BaseEstimateStatusBadge>
          </template>

          <template #cell-total="{ row }">
            <BaseFormatMoney
              :amount="row.data.total"
              :currency="store.currency"
            />
          </template>
        </BaseTable>
      </div>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCustomerPortalStore } from '../store'

const store = useCustomerPortalStore()
const { t } = useI18n()

onMounted(() => {
  store.loadDashboard()
})

interface TableColumn {
  key: string
  label: string
}

const dueInvoiceColumns = computed<TableColumn[]>(() => [
  {
    key: 'formattedDueDate',
    label: t('dashboard.recent_invoices_card.due_on'),
  },
  {
    key: 'invoice_number',
    label: t('invoices.number'),
  },
  { key: 'paid_status', label: t('invoices.status') },
  {
    key: 'due_amount',
    label: t('dashboard.recent_invoices_card.amount_due'),
  },
])

const recentEstimateColumns = computed<TableColumn[]>(() => [
  {
    key: 'formattedEstimateDate',
    label: t('dashboard.recent_estimate_card.date'),
  },
  {
    key: 'estimate_number',
    label: t('estimates.number'),
  },
  { key: 'status', label: t('estimates.status') },
  {
    key: 'total',
    label: t('dashboard.recent_estimate_card.amount_due'),
  },
])
</script>
