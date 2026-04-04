<script setup lang="ts">
import type { Invoice } from '@v2/types/domain'
import type { Currency } from '@v2/types/domain'
import type { Company } from '@v2/types/domain'
import type { Customer } from '@v2/types/domain'

interface InvoiceInfo {
  paid_status: string
  total: number
  formatted_notes?: string | null
  currency?: Currency
  company?: Pick<Company, 'name'>
  customer?: Pick<Customer, 'name'>
}

interface Props {
  invoice: InvoiceInfo | null
}

defineProps<Props>()
</script>

<template>
  <div class="bg-surface shadow overflow-hidden rounded-lg mt-6">
    <div class="px-4 py-5 sm:px-6">
      <h3 class="text-lg leading-6 font-medium text-heading">
        {{ $t('invoices.invoice_information') }}
      </h3>
    </div>
    <div v-if="invoice" class="border-t border-line-default px-4 py-5 sm:p-0">
      <dl class="sm:divide-y sm:divide-line-default">
        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
          <dt class="text-sm font-medium text-muted">
            {{ $t('general.from') }}
          </dt>
          <dd class="mt-1 text-sm text-heading sm:mt-0 sm:col-span-2">
            {{ invoice.company?.name }}
          </dd>
        </div>
        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
          <dt class="text-sm font-medium text-muted">
            {{ $t('general.to') }}
          </dt>
          <dd class="mt-1 text-sm text-heading sm:mt-0 sm:col-span-2">
            {{ invoice.customer?.name }}
          </dd>
        </div>
        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
          <dt class="text-sm font-medium text-muted capitalize">
            {{ $t('invoices.paid_status').toLowerCase() }}
          </dt>
          <dd class="mt-1 text-sm text-heading sm:mt-0 sm:col-span-2">
            <BaseInvoiceStatusBadge
              :status="invoice.paid_status"
              class="px-3 py-1"
            >
              <BaseInvoiceStatusLabel :status="invoice.paid_status" />
            </BaseInvoiceStatusBadge>
          </dd>
        </div>
        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
          <dt class="text-sm font-medium text-muted">
            {{ $t('invoices.total') }}
          </dt>
          <dd class="mt-1 text-sm text-heading sm:mt-0 sm:col-span-2">
            <BaseFormatMoney
              :currency="invoice.currency"
              :amount="invoice.total"
            />
          </dd>
        </div>
        <div
          v-if="invoice.formatted_notes"
          class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
        >
          <dt class="text-sm font-medium text-muted">
            {{ $t('invoices.notes') }}
          </dt>
          <dd class="mt-1 text-sm text-heading sm:mt-0 sm:col-span-2">
            <span v-html="invoice.formatted_notes"></span>
          </dd>
        </div>
      </dl>
    </div>
    <div v-else class="w-full flex items-center justify-center p-5">
      <BaseSpinner class="text-primary-500 h-10 w-10" />
    </div>
  </div>
</template>
