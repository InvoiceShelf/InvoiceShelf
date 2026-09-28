<template>
  <div
    v-if="record.last_error"
    role="alert"
    class="flex gap-3 rounded-lg bg-alert-error-bg p-4 text-sm text-alert-error-text"
  >
    <BaseIcon
      name="ExclamationTriangleIcon"
      class="h-5 w-5 shrink-0"
      aria-hidden="true"
    />
    <div>
      <p class="font-medium">{{ $t('purchases.last_run_failed') }}</p>
      <p class="mt-1">{{ lastError }}</p>
    </div>
  </div>
  <BaseStatStrip :columns="3">
    <BaseStat :label="$t('purchases.amount_per_run')" emphasis
      ><BaseFormatMoney :amount="amount.amount" :currency="currency" /><span
        v-if="amount.plusTax"
        class="ms-1 text-xs text-muted"
        >{{ $t('purchases.plus_tax') }}</span
      ></BaseStat
    >
    <BaseStat :label="$t('purchases.next_run')"
      ><PurchaseDate
        :value="
          record.status === 'COMPLETED' ? null : scheduleDate(record.next_run_at)
        "
    /></BaseStat>
    <BaseStat :label="$t('purchases.status')"
      ><PurchaseStatus kind="recurring-costs" :record="record"
    /></BaseStat>
  </BaseStatStrip>
  <div class="grid items-start gap-5 xl:grid-cols-3">
    <div class="space-y-5 xl:col-span-2">
      <BaseCard container-class="p-4 md:p-5">
        <h2 class="mb-4 font-semibold text-section text-heading">
          {{ $t('purchases.schedule') }}
        </h2>
        <dl class="grid gap-5 text-sm sm:grid-cols-2">
          <div v-if="record.supplier">
            <dt class="text-muted">{{ $t('purchases.supplier') }}</dt>
            <dd class="mt-1">
              <router-link
                :to="`/admin/suppliers/${record.supplier.id}/view`"
                class="font-medium text-primary-600"
                >{{ record.supplier.name }}</router-link
              >
            </dd>
          </div>
          <div>
            <dt class="text-muted">{{ $t('purchases.generate') }}</dt>
            <dd class="mt-1 text-heading">
              {{
                $t(
                  record.mode === 'EXPENSE'
                    ? 'purchases.paid_expense'
                    : 'purchases.unpaid_bill',
                )
              }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">{{ $t('purchases.frequency') }}</dt>
            <dd class="mt-1 text-heading">
              {{ frequencyLabel(record.frequency) }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">{{ $t('purchases.starts_at') }}</dt>
            <dd class="mt-1 text-heading">
              <PurchaseDate :value="record.starts_at" />
            </dd>
          </div>
          <div>
            <dt class="text-muted">{{ $t('purchases.ends') }}</dt>
            <dd class="mt-1 text-heading">
              <PurchaseDate
                v-if="record.limit_by === 'DATE'"
                :value="record.limit_date"
              />
              <template v-else-if="record.limit_by === 'COUNT'">{{
                $t('purchases.ends_after', {
                  count: record.limit_count ?? 0,
                })
              }}</template>
              <template v-else>{{ $t('purchases.ends_never') }}</template>
            </dd>
          </div>
          <div v-if="record.mode === 'BILL'">
            <dt class="text-muted">{{ $t('purchases.due_days') }}</dt>
            <dd class="mt-1 text-heading">{{ record.due_days ?? 0 }}</dd>
          </div>
          <div v-if="record.mode === 'BILL'">
            <dt class="text-muted">{{ $t('purchases.create_as_draft') }}</dt>
            <dd class="mt-1 text-heading">
              {{ $t(record.create_as_draft ? 'general.yes' : 'general.no') }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">{{ $t('purchases.notify_creator') }}</dt>
            <dd class="mt-1 text-heading">
              {{ $t(record.notify_creator ? 'general.yes' : 'general.no') }}
            </dd>
          </div>
        </dl>
        <p
          v-if="record.template?.notes"
          class="mt-5 whitespace-pre-wrap text-sm text-body"
        >
          {{ record.template.notes }}
        </p>
      </BaseCard>
      <BaseTable
        v-if="record.mode === 'BILL' && lines.length"
        :data="lines"
        :columns="lineColumns"
        :caption="$t('purchases.line_items')"
      >
        <template #cell-description="{ row }"
          ><span class="block whitespace-normal break-words">{{
            row.data.description
          }}</span></template
        >
        <template #cell-quantity="{ row }"
          ><span class="md:hidden">{{ $t('purchases.quantity') }}: </span
          >{{ row.data.quantity }}</template
        >
        <template #cell-price="{ row }"
          ><BaseFormatMoney :amount="row.data.price" :currency="currency"
        /></template>
      </BaseTable>
    </div>
    <BaseCard container-class="p-4 md:p-5">
      <h2 class="mb-3 font-semibold text-section text-heading">
        {{ $t('purchases.generated') }}
      </h2>
      <p v-if="!record.occurrences?.length" class="text-sm text-muted">
        {{ $t('purchases.nothing_generated') }}
      </p>
      <ul v-else>
        <li
          v-for="occurrence in record.occurrences"
          :key="occurrence.id"
          class="flex items-center justify-between gap-3 border-b border-line-light py-3 text-sm last:border-b-0"
        >
          <div class="min-w-0">
            <router-link
              :to="occurrenceLink(occurrence)"
              class="font-medium text-primary-600"
              >{{
                occurrence.number ||
                $t(
                  occurrence.record_type === 'bill'
                    ? 'purchases.bill'
                    : 'purchases.expense',
                )
              }}</router-link
            >
            <p class="text-muted">
              <PurchaseDate :value="occurrence.scheduled_for" /><template
                v-if="occurrence.status"
              >
                · {{ $t(`purchases.state_${occurrence.status}`) }}</template
              >
            </p>
          </div>
          <BaseFormatMoney
            v-if="occurrence.amount !== null"
            :amount="occurrence.amount"
            :currency="currency"
          />
        </li>
      </ul>
    </BaseCard>
  </div>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { getErrorTranslationKey } from '@/scripts/utils/error-handling'
import { useFrequencyPresets } from '@/scripts/components/recurrence/use-frequency-presets'
import type {
  PurchaseRecord,
  RecurringCostOccurrence,
} from '@/scripts/types/domain/purchase'
import type { Currency } from '@/scripts/types/domain/currency'
import { recurringAmount, scheduleDate } from '../helpers'
import PurchaseDate from './PurchaseDate.vue'
import PurchaseStatus from './PurchaseStatus.vue'

const props = defineProps<{ record: PurchaseRecord }>()

const { t } = useI18n()
const globalStore = useGlobalStore(),
  company = useCompanyStore()
const { labelFor: frequencyLabel } = useFrequencyPresets(t)
const currencies = ref<Currency[]>([])

onMounted(async () => {
  try {
    currencies.value = await globalStore.fetchCurrencies()
  } catch {
    // Amounts fall back to the company currency.
  }
})

/** Amounts are in the template's currency, which every run keeps. */
const currency = computed(
  () =>
    currencies.value.find(
      (row) => row.id === Number(props.record.template?.currency_id),
    ) ?? company.selectedCompanyCurrency,
)
const amount = computed(() => recurringAmount(props.record))
const lastError = computed(() => {
  const code = props.record.last_error || ''
  const key = getErrorTranslationKey(code)
  return key ? t(key) : t('errors.purchase_recurring_failed')
})
const lines = computed(() =>
  (props.record.template?.items ?? []).map((line, index) => ({
    ...line,
    id: index,
  })),
)
const lineColumns = computed(() => [
  { key: 'description', label: t('purchases.description'), mobile: 'title' },
  { key: 'quantity', label: t('purchases.quantity'), mobile: 'subtitle' },
  {
    key: 'price',
    label: t('purchases.unit_price'),
    mobile: 'trailing',
    align: 'end',
  },
])

function occurrenceLink(occurrence: RecurringCostOccurrence): string {
  return occurrence.record_type === 'bill'
    ? `/admin/bills/${occurrence.record_id}/view`
    : `/admin/expenses/${occurrence.record_id}/edit`
}
</script>
