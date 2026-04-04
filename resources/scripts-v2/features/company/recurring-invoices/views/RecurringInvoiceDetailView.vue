<template>
  <BasePage class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <RecurringInvoiceDropdown
          v-if="hasAtLeastOneAbility"
          :row="recurringInvoiceStore.newRecurringInvoice"
          :can-edit="canEdit"
          :can-view="canView"
          :can-delete="canDelete"
        />
      </template>
    </BasePageHeader>

    <!-- Content loaded from partials / child components would go here -->
    <div class="mt-8">
      <div
        v-if="recurringInvoiceStore.isFetchingViewData"
        class="flex justify-center p-12"
      >
        <LoadingIcon class="h-8 animate-spin text-primary-400" />
      </div>

      <div v-else>
        <!-- Invoice details info would be rendered here -->
        <div class="bg-surface rounded-xl border border-line-default p-6">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-muted">{{ $t('recurring_invoices.starts_at') }}:</span>
              <span class="ml-2 text-heading">
                {{ recurringInvoiceStore.newRecurringInvoice.starts_at }}
              </span>
            </div>
            <div>
              <span class="text-muted">{{ $t('recurring_invoices.next_invoice_date') }}:</span>
              <span class="ml-2 text-heading">
                {{ recurringInvoiceStore.newRecurringInvoice.next_invoice_at }}
              </span>
            </div>
            <div>
              <span class="text-muted">{{ $t('recurring_invoices.frequency.title') }}:</span>
              <span class="ml-2 text-heading">
                {{ recurringInvoiceStore.newRecurringInvoice.frequency }}
              </span>
            </div>
            <div>
              <span class="text-muted">{{ $t('recurring_invoices.status') }}:</span>
              <span class="ml-2">
                <BaseRecurringInvoiceStatusBadge
                  :status="recurringInvoiceStore.newRecurringInvoice.status"
                  class="px-2 py-0.5"
                >
                  {{ recurringInvoiceStore.newRecurringInvoice.status }}
                </BaseRecurringInvoiceStatusBadge>
              </span>
            </div>
            <div>
              <span class="text-muted">{{ $t('recurring_invoices.limit_by') }}:</span>
              <span class="ml-2 text-heading">
                {{ recurringInvoiceStore.newRecurringInvoice.limit_by }}
              </span>
            </div>
            <div v-if="recurringInvoiceStore.newRecurringInvoice.limit_by === 'COUNT'">
              <span class="text-muted">{{ $t('recurring_invoices.count') }}:</span>
              <span class="ml-2 text-heading">
                {{ recurringInvoiceStore.newRecurringInvoice.limit_count }}
              </span>
            </div>
            <div v-if="recurringInvoiceStore.newRecurringInvoice.limit_by === 'DATE'">
              <span class="text-muted">{{ $t('recurring_invoices.limit_date') }}:</span>
              <span class="ml-2 text-heading">
                {{ recurringInvoiceStore.newRecurringInvoice.limit_date }}
              </span>
            </div>
            <div>
              <span class="text-muted">{{ $t('recurring_invoices.send_automatically') }}:</span>
              <span class="ml-2 text-heading">
                {{ recurringInvoiceStore.newRecurringInvoice.send_automatically ? $t('general.yes') : $t('general.no') }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useRecurringInvoiceStore } from '../store'
import RecurringInvoiceDropdown from '../components/RecurringInvoiceDropdown.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'

interface Props {
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canEdit: false,
  canView: false,
  canDelete: false,
})

const recurringInvoiceStore = useRecurringInvoiceStore()
const { t } = useI18n()
const route = useRoute()

const pageTitle = computed<string>(() => {
  return recurringInvoiceStore.newRecurringInvoice?.customer?.name ?? ''
})

const hasAtLeastOneAbility = computed<boolean>(() => {
  return props.canDelete || props.canEdit
})

// Initialize frequencies
recurringInvoiceStore.initFrequencies(t)

// Load the recurring invoice
if (route.params.id) {
  recurringInvoiceStore.fetchRecurringInvoice(Number(route.params.id))
}
</script>
