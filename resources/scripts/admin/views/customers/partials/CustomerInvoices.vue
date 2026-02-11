<template>
  <BaseCard class="mt-6">
    <div class="flex items-center justify-between mb-3">
      <h6 class="text-xl font-semibold leading-normal">
        {{ $t('invoices.invoice', 2) }}
      </h6>
    </div>

    <BaseTable ref="table" :data="fetchData" :columns="invoiceColumns">
      <template #cell-invoice_number="{ row }">
        <router-link
          v-if="canViewInvoice"
          :to="`/admin/invoices/${row.data.id}/view`"
          class="font-medium text-primary-500"
        >
          {{ row.data.invoice_number }}
        </router-link>
        <span v-else>
          {{ row.data.invoice_number }}
        </span>
      </template>

      <template #cell-formatted_invoice_date="{ row }">
        {{ row.data.formatted_invoice_date }}
      </template>

      <template #cell-status="{ row }">
        <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
          <BaseInvoiceStatusLabel :status="row.data.status" />
        </BaseInvoiceStatusBadge>
      </template>

      <template #cell-due_amount="{ row }">
        <div class="flex items-center justify-between">
          <BaseFormatMoney
            :amount="row.data.due_amount"
            :currency="row.data.currency"
          />

          <BasePaidStatusBadge
            v-if="row.data.overdue"
            status="OVERDUE"
            class="px-1 py-0.5 ml-2"
          >
            {{ $t('invoices.overdue') }}
          </BasePaidStatusBadge>

          <BasePaidStatusBadge
            :status="row.data.paid_status"
            class="px-1 py-0.5 ml-2"
          >
            <BaseInvoiceStatusLabel :status="row.data.paid_status" />
          </BasePaidStatusBadge>
        </div>
      </template>

      <template #cell-total="{ row }">
        <BaseFormatMoney
          :amount="row.data.total"
          :currency="row.data.currency"
        />
      </template>

      <template v-if="canEditInvoice" #cell-actions="{ row }">
        <router-link :to="`/admin/invoices/${row.data.id}/edit`">
          <BaseButton size="sm" variant="primary-outline">
            {{ $t('general.edit') }}
          </BaseButton>
        </router-link>
      </template>
    </BaseTable>
  </BaseCard>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'

const { t } = useI18n()
const route = useRoute()
const invoiceStore = useInvoiceStore()
const userStore = useUserStore()

const table = ref(null)
const pageSize = 10

const invoiceColumns = computed(() => {
  const columns = [
    { key: 'invoice_number', label: t('invoices.number') },
    { key: 'formatted_invoice_date', label: t('invoices.date') },
    { key: 'status', label: t('invoices.status') },
    {
      key: 'due_amount',
      label: t('dashboard.recent_invoices_card.amount_due'),
    },
    { key: 'total', label: t('invoices.total') },
  ]

  if (canEditInvoice.value) {
    columns.push({
      key: 'actions',
      label: t('invoices.action'),
      tdClass: 'text-right text-sm font-medium',
      thClass: 'text-right',
      sortable: false,
    })
  }

  return columns
})

const canViewInvoice = computed(() =>
  userStore.hasAbilities([abilities.VIEW_INVOICE])
)

const canEditInvoice = computed(() =>
  userStore.hasAbilities([abilities.EDIT_INVOICE])
)

async function fetchData({ page, sort }) {
  const customerId = route.params.id
  if (!customerId) {
    return {
      data: [],
      pagination: {
        totalPages: 1,
        currentPage: page,
        totalCount: 0,
        limit: pageSize,
      },
    }
  }

  const response = await invoiceStore.fetchInvoices({
    customer_id: customerId,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  })

  return {
    data: response?.data?.data || [],
    pagination: {
      totalPages: response?.data?.meta?.last_page || 1,
      currentPage: page,
      totalCount: response?.data?.meta?.total || 0,
      limit: pageSize,
    },
  }
}

watch(
  () => route.params.id,
  () => {
    table.value && table.value.refresh()
  },
  { immediate: true }
)
</script>
