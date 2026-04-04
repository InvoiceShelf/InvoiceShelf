<template>
  <BasePage class="relative invoice-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <BaseBreadcrumbItem
            :title="$t('recurring_invoices.title', 2)"
            to="/admin/recurring-invoices"
          />
          <BaseBreadcrumbItem :title="pageTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                name="ArrowDownOnSquareIcon"
                :class="slotProps.class"
              />
            </template>
            {{ $t('recurring_invoices.save_invoice') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields -->
      <div class="grid-cols-12 gap-8 mt-6 mb-8 lg:grid">
        <RecurringInvoiceBasicFields
          :is-loading="isLoadingContent"
          :is-edit="isEdit"
        />
      </div>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useRecurringInvoiceStore } from '../store'
import RecurringInvoiceBasicFields from '../components/RecurringInvoiceBasicFields.vue'

const recurringInvoiceStore = useRecurringInvoiceStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isSaving = ref<boolean>(false)

const isLoadingContent = computed<boolean>(
  () =>
    recurringInvoiceStore.isFetchingInvoice ||
    recurringInvoiceStore.isFetchingInitialSettings,
)

const pageTitle = computed<string>(() =>
  isEdit.value
    ? t('recurring_invoices.edit_invoice')
    : t('recurring_invoices.new_invoice'),
)

const isEdit = computed<boolean>(
  () => route.name === 'recurring-invoices.edit',
)

// Initialize frequencies
recurringInvoiceStore.initFrequencies(t)

// Reset state
recurringInvoiceStore.resetCurrentRecurringInvoice()
recurringInvoiceStore.fetchRecurringInvoiceInitialSettings(isEdit.value, {
  id: route.params.id as string | undefined,
  query: route.query as Record<string, string>,
})

watch(
  () => recurringInvoiceStore.newRecurringInvoice.customer,
  (newVal) => {
    if (newVal && (newVal as Record<string, unknown>).currency) {
      recurringInvoiceStore.newRecurringInvoice.currency = (
        newVal as Record<string, unknown>
      ).currency as typeof recurringInvoiceStore.newRecurringInvoice.currency
    }
  },
)

async function submitForm(): Promise<void> {
  isSaving.value = true

  const data: Record<string, unknown> = {
    ...recurringInvoiceStore.newRecurringInvoice,
    sub_total: recurringInvoiceStore.getSubTotal,
    total: recurringInvoiceStore.getTotal,
    tax: recurringInvoiceStore.getTotalTax,
  }

  try {
    if (route.params.id) {
      const res = await recurringInvoiceStore.updateRecurringInvoice(data)
      if (res.data.data) {
        router.push(
          `/admin/recurring-invoices/${res.data.data.id}/view`,
        )
      }
    } else {
      const res = await recurringInvoiceStore.addRecurringInvoice(data)
      if (res.data.data) {
        router.push(
          `/admin/recurring-invoices/${res.data.data.id}/view`,
        )
      }
    }
  } catch {
    // Error handled in store
  } finally {
    isSaving.value = false
  }
}
</script>
