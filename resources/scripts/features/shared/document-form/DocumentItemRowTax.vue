<template>
  <div class="flex items-center justify-between mb-3">
    <div class="flex items-center text-base" style="flex: 4">
      <span class="pr-2 mb-0" aria-hidden="true">
        {{ $t('invoices.item.tax') }}
      </span>

      <BaseMultiselect
        v-model="selectedTax"
        :aria-label="$t('invoices.item.tax')"
        value-prop="id"
        :options="filteredTypes"
        :placeholder="$t('general.select_a_tax')"
        open-direction="top"
        track-by="name"
        searchable
        object
        label="name"
        @update:model-value="onSelectTax"
      >
        <template #singlelabel="{ value }">
          <div class="absolute left-3.5">
            {{ value.name }} -
            <template v-if="value.calculation_type === 'fixed'">
              <BaseFormatMoney :amount="value.fixed_amount" :currency="currency" />
            </template>
            <template v-else>
              {{ value.percent }} %
            </template>
          </div>
        </template>

        <template #option="{ option }">
          {{ option.name }} -
          <template v-if="option.calculation_type === 'fixed'">
            <BaseFormatMoney :amount="option.fixed_amount" :currency="currency" />
          </template>
          <template v-else>
            {{ option.percent }} %
          </template>
          <BaseBadge v-if="option.compound_tax" class="ml-2 text-xs">
            {{ $t('tax_types.compound_tax') }}
          </BaseBadge>
        </template>

        <template v-if="canAddTax" #action>
          <button
            type="button"
            class="flex items-center justify-center w-full px-2 py-2 bg-surface-muted border-none outline-hidden cursor-pointer text-primary-600 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus"
            @click="openTaxModal"
          >
            <BaseIcon name="CheckCircleIcon" class="h-5" />
            <span class="ml-2 text-sm leading-none">
              {{ $t('invoices.add_new_tax') }}
            </span>
          </button>
        </template>
      </BaseMultiselect>
      <br />
    </div>

    <div class="text-sm text-right" style="flex: 3">
      <BaseFormatMoney :amount="taxAmount" :currency="currency" />
    </div>

    <div class="flex items-center justify-center w-8 h-10 mx-1">
      <BaseIconButton
        v-if="taxes.length && index !== taxes.length - 1"
        icon="TrashIcon"
        :label="$t('general.remove_named', { name: taxData.name || $t('invoices.item.tax') })"
        size="sm"
        tone="danger"
        @click="removeTax(index)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../stores/modal.store'
import type { TaxType } from '../../../types/domain/tax'
import type { Currency } from '../../../types/domain/currency'
import type { DocumentFormData, DocumentTax } from './use-document-calculations'
import { calcTaxAmount } from './use-document-calculations'

interface Props {
  store: Record<string, unknown>
  storeProp: string
  itemIndex: number
  index: number
  taxData: DocumentTax
  taxes: DocumentTax[]
  total: number
  /** Sum of the item's non-compound tax amounts, i.e. the compound base widener. */
  totalSimpleTax: number
  discountedTotal: number
  currency: Currency | Record<string, unknown>
  updateItems: () => void
  taxTypes?: TaxType[]
  canAddTax?: boolean
  discount?: number
}

interface Emits {
  (e: 'remove', index: number): void
  (e: 'update', payload: { index: number; item: DocumentTax }): void
  (e: 'taxTypeCreated', taxType: TaxType): void
}

const props = withDefaults(defineProps<Props>(), {
  taxTypes: () => [],
  canAddTax: false,
  discount: 0,
})

const emit = defineEmits<Emits>()

const { t } = useI18n()
const modalStore = useModalStore()

const selectedTax = ref<TaxType | null>(null)
const localTax = reactive<DocumentTax>({ ...props.taxData })

const storeData = computed(() => props.store[props.storeProp] as DocumentFormData)

const filteredTypes = computed<(TaxType & { disabled?: boolean })[]>(() => {
  const clonedTypes = props.taxTypes.map((a) => ({ ...a, disabled: false }))

  return clonedTypes.map((taxType) => {
    const found = props.taxes.find((tax) => tax.tax_type_id === taxType.id)
    taxType.disabled = !!found
    return taxType
  })
})

/**
 * The item's taxable base, shared by every tax branch.
 *
 * With a per-item discount the item total is already net of it. With a
 * document-level discount the item carries its proportional share of that
 * discount instead.
 */
const effectiveBase = computed<number>(() => {
  if (storeData.value.discount_per_item === 'YES') {
    return props.discountedTotal
  }

  const modelDiscount = storeData.value.discount ?? 0

  if (modelDiscount <= 0) {
    return props.discountedTotal
  }

  const itemsTotal = storeData.value.items.reduce(
    (sum: number, item) => sum + (item.total ?? 0),
    0,
  )

  if (!itemsTotal) {
    return props.discountedTotal
  }

  const proportion = parseFloat((props.discountedTotal / itemsTotal).toFixed(2))
  const discount =
    storeData.value.discount_type === 'fixed'
      ? modelDiscount * 100
      : (itemsTotal * modelDiscount) / 100

  return props.discountedTotal - Math.round(discount * proportion)
})

const taxAmount = computed<number>(() => {
  return calcTaxAmount(
    effectiveBase.value,
    localTax.percent,
    localTax.fixed_amount,
    localTax.calculation_type,
    storeData.value.tax_included ?? false,
    localTax.compound_tax ?? false,
    props.totalSimpleTax,
  )
})

watch(
  () => props.discountedTotal,
  () => updateRowTax(),
)

// A sibling simple tax landing later widens this row's base when it is compound.
watch(
  () => props.totalSimpleTax,
  () => updateRowTax(),
)

watch(
  () => taxAmount.value,
  () => updateRowTax(),
)

// Resolve the selected tax type when editing. The list is fetched by the parent
// table, so it usually arrives after this row has been set up.
watch(
  () => props.taxTypes,
  (types) => {
    if (localTax.tax_type_id > 0) {
      selectedTax.value =
        types.find((_type) => _type.id === localTax.tax_type_id) ?? selectedTax.value
    }
  },
  { immediate: true },
)

updateRowTax()

function onSelectTax(val: TaxType): void {
  localTax.calculation_type = val.calculation_type
  localTax.percent = val.calculation_type === 'percentage' ? val.percent : null
  localTax.fixed_amount =
    val.calculation_type === 'fixed' ? val.fixed_amount : 0
  localTax.tax_type_id = val.id
  localTax.name = val.name
  localTax.compound_tax = val.compound_tax ?? false

  updateRowTax()
}

function updateRowTax(): void {
  if (localTax.tax_type_id === 0) {
    return
  }

  emit('update', {
    index: props.index,
    item: {
      ...localTax,
      amount: taxAmount.value,
    },
  })
}

function openTaxModal(): void {
  modalStore.openModal({
    title: t('settings.tax_types.add_tax'),
    componentName: 'TaxTypeModal',
    data: {
      itemIndex: props.itemIndex,
      taxIndex: props.index,
      transaction_type: 'sales',
    },
    size: 'sm',
    refreshData: (...args: unknown[]) => {
      const taxType = args[0]
      if (isTaxType(taxType)) {
        selectedTax.value = taxType
        onSelectTax(taxType)
        emit('taxTypeCreated', taxType)
      }
    },
  })
}

function isTaxType(value: unknown): value is TaxType {
  return (
    typeof value === 'object' &&
    value !== null &&
    'id' in value &&
    typeof value.id === 'number' &&
    'name' in value &&
    typeof value.name === 'string'
  )
}

function removeTax(index: number): void {
  const store = props.store as Record<string, Record<string, unknown>>
  const formData = store[props.storeProp] as DocumentFormData
  formData.items[props.itemIndex].taxes?.splice(index, 1)

  // Re-sync the item so the remaining rows re-base off the new simple total
  // instead of leaving stale `tax` / `totalTax` values behind.
  props.updateItems()
}
</script>
