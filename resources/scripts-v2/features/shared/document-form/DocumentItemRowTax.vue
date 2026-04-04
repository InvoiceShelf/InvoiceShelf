<template>
  <div class="flex items-center justify-between mb-3">
    <div class="flex items-center text-base" style="flex: 4">
      <label class="pr-2 mb-0" align="right">
        {{ $t('invoices.item.tax') }}
      </label>

      <BaseMultiselect
        v-model="selectedTax"
        value-prop="id"
        :options="filteredTypes"
        :placeholder="$t('general.select_a_tax')"
        open-direction="top"
        track-by="name"
        searchable
        object
        label="name"
        @update:modelValue="onSelectTax"
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
        </template>

        <template v-if="canAddTax" #action>
          <button
            type="button"
            class="flex items-center justify-center w-full px-2 py-2 bg-surface-muted border-none outline-hidden cursor-pointer"
            @click="openTaxModal"
          >
            <BaseIcon name="CheckCircleIcon" class="h-5 text-primary-400" />
            <label class="ml-2 text-sm leading-none cursor-pointer text-primary-400">
              {{ $t('invoices.add_new_tax') }}
            </label>
          </button>
        </template>
      </BaseMultiselect>
      <br />
    </div>

    <div class="text-sm text-right" style="flex: 3">
      <BaseFormatMoney :amount="taxAmount" :currency="currency" />
    </div>

    <div class="flex items-center justify-center w-6 h-10 mx-2 cursor-pointer">
      <BaseIcon
        v-if="taxes.length && index !== taxes.length - 1"
        name="TrashIcon"
        class="h-5 text-body cursor-pointer"
        @click="removeTax(index)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { TaxType } from '../../../types/domain/tax'
import type { Currency } from '../../../types/domain/currency'
import type { DocumentFormData, DocumentTax } from './use-document-calculations'

interface Props {
  ability: string
  store: Record<string, unknown>
  storeProp: string
  itemIndex: number
  index: number
  taxData: DocumentTax
  taxes: DocumentTax[]
  total: number
  totalTax: number
  discountedTotal: number
  currency: Currency | Record<string, unknown>
  updateItems: () => void
  discount?: number
}

interface Emits {
  (e: 'remove', index: number): void
  (e: 'update', payload: { index: number; item: DocumentTax }): void
}

const props = withDefaults(defineProps<Props>(), {
  ability: '',
  discount: 0,
})

const emit = defineEmits<Emits>()

const { t } = useI18n()

// We assume these stores are available globally or injected
// In the v2 arch, we'll use a lighter approach
const taxTypes = computed<TaxType[]>(() => {
  // Access taxTypeStore through the store's taxTypes or a global store
  return (window as Record<string, unknown>).__taxTypes as TaxType[] ?? []
})

const canAddTax = computed(() => {
  return (window as Record<string, unknown>).__userHasAbility?.(props.ability) ?? false
})

const selectedTax = ref<TaxType | null>(null)
const localTax = reactive<DocumentTax>({ ...props.taxData })

const storeData = computed(() => props.store[props.storeProp] as DocumentFormData)

const filteredTypes = computed<(TaxType & { disabled?: boolean })[]>(() => {
  const clonedTypes = taxTypes.value.map((a) => ({ ...a, disabled: false }))

  return clonedTypes.map((taxType) => {
    const found = props.taxes.find((tax) => tax.tax_type_id === taxType.id)
    taxType.disabled = !!found
    return taxType
  })
})

const taxAmount = computed<number>(() => {
  if (localTax.calculation_type === 'fixed') {
    return localTax.fixed_amount
  }

  if (props.discountedTotal) {
    const taxPerItemEnabled = storeData.value.tax_per_item === 'YES'
    const discountPerItemEnabled = storeData.value.discount_per_item === 'YES'

    if (taxPerItemEnabled && !discountPerItemEnabled) {
      return getTaxAmount()
    }
    if (storeData.value.tax_included) {
      return Math.round(
        props.discountedTotal -
          props.discountedTotal / (1 + (localTax.percent ?? 0) / 100),
      )
    }
    return Math.round((props.discountedTotal * (localTax.percent ?? 0)) / 100)
  }
  return 0
})

watch(
  () => props.discountedTotal,
  () => updateRowTax(),
)

watch(
  () => props.totalTax,
  () => updateRowTax(),
)

watch(
  () => taxAmount.value,
  () => updateRowTax(),
)

// Initialize selected tax if editing
if (props.taxData.tax_type_id > 0) {
  selectedTax.value =
    taxTypes.value.find((_type) => _type.id === props.taxData.tax_type_id) ?? null
}

updateRowTax()

function onSelectTax(val: TaxType): void {
  localTax.calculation_type = val.calculation_type
  localTax.percent = val.calculation_type === 'percentage' ? val.percent : null
  localTax.fixed_amount =
    val.calculation_type === 'fixed' ? val.fixed_amount : 0
  localTax.tax_type_id = val.id
  localTax.name = val.name

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
  // Modal integration - emit event or use modal store
  const modalStore = (window as Record<string, unknown>).__modalStore as
    | { openModal: (opts: Record<string, unknown>) => void }
    | undefined
  modalStore?.openModal({
    title: t('settings.tax_types.add_tax'),
    componentName: 'TaxTypeModal',
    data: { itemIndex: props.itemIndex, taxIndex: props.index },
    size: 'sm',
  })
}

function removeTax(index: number): void {
  const store = props.store as Record<string, Record<string, unknown>>
  const formData = store[props.storeProp] as DocumentFormData
  formData.items[props.itemIndex].taxes?.splice(index, 1)
  const item = formData.items[props.itemIndex]
  item.tax = 0
  item.totalTax = 0
}

function getTaxAmount(): number {
  if (localTax.calculation_type === 'fixed') {
    return localTax.fixed_amount
  }

  let itemsTotal = 0
  let discount = 0
  const itemTotal = props.discountedTotal
  const modelDiscount = storeData.value.discount ?? 0
  const type = storeData.value.discount_type
  let discountedTotal = props.discountedTotal

  if (modelDiscount > 0) {
    storeData.value.items.forEach((item) => {
      itemsTotal += item.total ?? 0
    })
    const proportion = parseFloat((itemTotal / itemsTotal).toFixed(2))
    discount =
      type === 'fixed'
        ? modelDiscount * 100
        : (itemsTotal * modelDiscount) / 100
    const itemDiscount = Math.round(discount * proportion)
    discountedTotal = itemTotal - itemDiscount
  }

  if (storeData.value.tax_included) {
    return Math.round(
      discountedTotal -
        discountedTotal / (1 + (localTax.percent ?? 0) / 100),
    )
  }

  return Math.round((discountedTotal * (localTax.percent ?? 0)) / 100)
}
</script>
