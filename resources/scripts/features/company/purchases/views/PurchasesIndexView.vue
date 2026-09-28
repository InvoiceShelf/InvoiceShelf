<template>
  <BasePage>
    <PurchaseListHeader :section="section">
      <template #actions>
        <BaseButton
          variant="primary-outline"
          :aria-expanded="showFilters"
          @click="showFilters = !showFilters"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps"
            ><BaseIcon
              :name="showFilters ? 'XMarkIcon' : 'FunnelIcon'"
              :class="slotProps.class"
          /></template>
        </BaseButton>
        <BaseButton v-if="canCreate" @click="router.push(createLink)">
          <template #left="slotProps"
            ><BaseIcon name="PlusIcon" :class="slotProps.class"
          /></template>
          {{ $t(createLabel) }}
        </BaseButton>
      </template>
    </PurchaseListHeader>
    <BaseFilterWrapper :show="showFilters" @clear="clearFilters">
      <BaseInputGroup :label="$t('purchases.search')"
        ><BaseInput v-model="filters.search" type="search"
      /></BaseInputGroup>
      <BaseInputGroup
        v-if="kind !== 'suppliers' && user.hasAbilities('view-supplier')"
        :label="$t('purchases.supplier')"
      >
        <BaseMultiselect
          v-model="filters.supplier_id"
          value-prop="id"
          label="name"
          :options="searchSuppliers"
          :filter-results="false"
          resolve-on-load
          searchable
          :delay="250"
        />
      </BaseInputGroup>
      <BaseInputGroup
        v-if="kind !== 'suppliers'"
        :label="$t('purchases.status')"
      >
        <BaseMultiselect
          v-model="filters.status"
          :options="
            states.map((value) => ({
              value,
              label: $t(`purchases.state_${value}`),
            }))
          "
          :placeholder="$t('purchases.all')"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>
    <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>
    <BaseEmptyPlaceholder
      v-if="initialLoaded && total === 0 && !hasFilters && !error"
      :art="
        kind === 'suppliers'
          ? 'customer'
          : kind === 'supplier-payments'
            ? 'payment'
            : 'invoice'
      "
      :ghost="6"
      :title="
        $t('purchases.no_records', {
          type: $t(
            kind === 'recurring-costs'
              ? recurringLabel(mode, 'title')
              : `purchases.${kind}`,
          ),
        })
      "
      :description="$t(emptyDescriptionKey)"
    >
      <template v-if="canCreate" #actions
        ><BaseButton @click="router.push(createLink)"
          ><template #left="slotProps"
            ><BaseIcon name="PlusIcon" :class="slotProps.class" /></template
          >{{ $t(createLabel) }}</BaseButton
        ></template
      >
    </BaseEmptyPlaceholder>
    <BaseTable
      v-show="!initialLoaded || total > 0 || hasFilters || error"
      ref="table"
      :data="fetchData"
      :columns="columns"
      :row-to="rowLink"
      :no-results-message="$t('purchases.no_matches')"
    >
      <template #cell-number="{ row }"
        ><router-link
          :to="rowLink(row.data)"
          class="font-medium text-primary-600"
          >{{ row.data.number || row.data.name }}</router-link
        ></template
      >
      <template #cell-frequency="{ row }">{{
        frequencyLabel(row.data.frequency)
      }}</template>
      <template #cell-next_run_at="{ row }"
        ><PurchaseDate
          :value="
            row.data.status === 'COMPLETED'
              ? null
              : scheduleDate(row.data.next_run_at)
          "
      /></template>
      <template #cell-template_amount="{ row }"
        ><BaseFormatMoney
          :amount="recurringAmount(row.data as PurchaseRecord).amount"
          :currency="templateCurrency(row.data as PurchaseRecord)" /><span
          v-if="recurringAmount(row.data as PurchaseRecord).plusTax"
          class="ms-1 text-xs text-muted"
          >{{ $t('purchases.plus_tax') }}</span
        ></template
      >
      <template
        v-for="field in [
          'due_date',
          'document_date',
          'payment_date',
        ]"
        :key="field"
        #[`cell-${field}`]="{ row }"
        ><PurchaseDate :value="row.data[field]"
      /></template>
      <template #cell-status="{ row }"
        ><PurchaseStatus :kind="kind" :record="row.data"
      /></template>
      <template #cell-total="{ row }"
        ><BaseFormatMoney
          :amount="row.data.total ?? row.data.amount ?? 0"
          :currency="row.data.currency"
      /></template>
      <template #cell-due_amount="{ row }"
        ><BaseFormatMoney
          :amount="row.data.due_amount || 0"
          :currency="row.data.currency"
      /></template>
      <template #cell-available_amount="{ row }"
        ><BaseFormatMoney
          :amount="row.data.available_amount || 0"
          :currency="row.data.currency"
      /></template>
    </BaseTable>
  </BasePage>
</template>
<script setup lang="ts">
import { ref, computed, reactive, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/scripts/stores/user.store'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import { useGlobalStore } from '@/scripts/stores/global.store'
import type {
  PurchaseKind,
  PurchaseRecord,
  RecurringCostMode,
} from '@/scripts/types/domain/purchase'
import type { Currency } from '@/scripts/types/domain/currency'
import { useFrequencyPresets } from '@/scripts/components/recurrence/use-frequency-presets'
import type {
  ColumnDef,
  RowData,
} from '@/scripts/components/table/DataTable.vue'
import {
  entityAbility,
  purchaseError,
  recurringAmount,
  scheduleDate,
} from '../helpers'
import {
  purchaseCreateLabels,
  recurringLabel,
  type PurchaseSection,
} from '../navigation'
import PurchaseListHeader from '../components/PurchaseListHeader.vue'
import PurchaseDate from '../components/PurchaseDate.vue'
import PurchaseStatus from '../components/PurchaseStatus.vue'
const props = defineProps<{
  kind: PurchaseKind
  section: PurchaseSection
  /** For recurring costs: which schedules to list. */
  mode?: RecurringCostMode
}>()
const router = useRouter(),
  route = useRoute(),
  user = useUserStore(),
  globalStore = useGlobalStore(),
  { t } = useI18n()
const recurring = computed(() => props.kind === 'recurring-costs')
const { labelFor: frequencyLabel } = useFrequencyPresets(t)
const currencies = ref<Currency[]>([])
onMounted(async () => {
  if (!recurring.value) return
  try {
    currencies.value = await globalStore.fetchCurrencies()
  } catch {
    // Amounts fall back to the company currency.
  }
})
function templateCurrency(row: PurchaseRecord) {
  return (
    currencies.value.find(
      (currency) => currency.id === Number(row.template?.currency_id),
    ) ?? row.supplier?.currency
  )
}
const table = ref<{ refresh: () => void } | null>(null)
const total = ref(0),
  error = ref(''),
  initialLoaded = ref(false)
const filters = reactive({
  search: '',
  status: null as string | null,
  supplier_id: Number(route.query.supplier_id) || null,
})
const showFilters = ref(!!filters.supplier_id)
const hasFilters = computed(
  () => !!(filters.search || filters.status || filters.supplier_id),
)
const emptyDescriptionKey = computed(() =>
  recurring.value
    ? recurringLabel(props.mode, 'empty')
    : `purchases.empty_${props.kind}`,
)
const createLabel = computed(() =>
  recurring.value
    ? recurringLabel(props.mode, 'new')
    : purchaseCreateLabels[props.kind],
)
// A schedule makes its records on its creator's behalf, so creating one
// also needs the right to create what it generates.
const canCreate = computed(
  () =>
    user.hasAbilities(`create-${entityAbility(props.kind)}`) &&
    (!recurring.value ||
      user.hasAbilities(
        props.mode === 'EXPENSE' ? 'create-expense' : 'create-bill',
      )),
)
const createLink = computed(() => ({
  path: `/admin/${props.kind}/create`,
  query: {
    ...(props.mode ? { mode: props.mode } : {}),
    ...(filters.supplier_id ? { supplier_id: filters.supplier_id } : {}),
  },
}))
const states = computed(() =>
  recurring.value
    ? ['ACTIVE', 'ON_HOLD', 'COMPLETED']
    : props.kind === 'bills'
      ? ['DRAFT', 'UNPAID', 'PARTIAL', 'SETTLED', 'OVERDUE', 'VOID']
      : ['OPEN', 'VOID'],
)
const columns = computed<ColumnDef[]>(() => {
  const list: ColumnDef[] = [
    {
      key: 'number',
      label: t(
        props.kind === 'suppliers' || recurring.value
          ? 'purchases.name'
          : 'purchases.reference',
      ),
      mobile: 'title',
    },
  ]
  if (props.kind === 'suppliers')
    list.push({ key: 'email', label: t('purchases.email'), mobile: 'subtitle' })
  else
    list.push({
      key: 'supplier.name',
      label: t('purchases.supplier'),
      mobile: 'subtitle',
    })
  if (recurring.value) {
    list.push(
      { key: 'frequency', label: t('purchases.frequency') },
      {
        key: 'next_run_at',
        label: t('purchases.next_run'),
        mobile: 'subtitle',
      },
      {
        key: 'template_amount',
        label: t('purchases.amount'),
        mobile: 'trailing',
        align: 'end',
      },
    )
  } else if (props.kind !== 'suppliers') {
    list.push({
      key:
        props.kind === 'bills'
          ? 'due_date'
          : props.kind === 'supplier-credits'
            ? 'document_date'
            : 'payment_date',
      label: t(
        props.kind === 'bills' ? 'purchases.due_date' : 'purchases.date',
      ),
      mobile: 'subtitle',
    })
    list.push({
      key: 'total',
      label: t('purchases.amount'),
      mobile: 'trailing',
      align: 'end',
    })
    if (props.kind === 'bills')
      list.push({
        key: 'due_amount',
        label: t('purchases.due'),
        mobile: 'trailing-sub',
        align: 'end',
      })
    if (['supplier-payments', 'supplier-credits'].includes(props.kind))
      list.push({
        key: 'available_amount',
        label: t('purchases.available'),
        mobile: 'trailing-sub',
        align: 'end',
      })
  }
  list.push({ key: 'status', label: t('purchases.status'), mobile: 'badge' })
  return list.map((column) => ({ ...column, sortable: false }))
})
function rowLink(row: RowData) {
  return `/admin/${props.kind}/${row.id}/view${props.mode ? `?mode=${props.mode}` : ''}`
}
async function searchSuppliers(search = '') {
  const rows = await purchaseService.suppliers(search)
  if (
    filters.supplier_id &&
    !search &&
    !rows.some((row) => row.id === filters.supplier_id)
  )
    rows.unshift(await purchaseService.get('suppliers', filters.supplier_id))
  return rows
}
async function fetchData({ page }: { page: number }) {
  error.value = ''
  try {
    const settlement =
      props.kind === 'bills' &&
      !['DRAFT', 'VOID'].includes(filters.status || '')
    const result = await purchaseService.list(props.kind, {
      page,
      limit: 20,
      search: filters.search,
      supplier_id: filters.supplier_id,
      status: settlement ? '' : filters.status,
      settlement_status: settlement ? filters.status : '',
      mode: props.mode,
    })
    total.value = result.meta.total
    initialLoaded.value = true
    return {
      data: result.data.map((row) => ({ ...row })),
      pagination: {
        totalPages: result.meta.last_page,
        currentPage: page,
        totalCount: result.meta.total,
        count: result.data.length,
        limit: 20,
      },
    }
  } catch (e) {
    error.value = purchaseError(e)
    throw e
  }
}
function clearFilters() {
  filters.search = ''
  filters.status = null
  filters.supplier_id = null
}
let timer: ReturnType<typeof setTimeout> | undefined
watch(filters, () => {
  clearTimeout(timer)
  timer = setTimeout(() => table.value?.refresh(), 250)
})
watch(
  () => route.query.supplier_id,
  (value) => {
    filters.supplier_id = Number(value) || null
  },
)
watch(
  () => filters.supplier_id,
  (value) => {
    if ((Number(route.query.supplier_id) || null) === value) return
    const query = { ...route.query }
    if (value) query.supplier_id = String(value)
    else delete query.supplier_id
    router.replace({ query })
  },
)
onUnmounted(() => clearTimeout(timer))
</script>
