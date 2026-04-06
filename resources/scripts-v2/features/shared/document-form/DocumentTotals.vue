<template>
  <div
    class="px-5 py-4 mt-6 bg-surface border border-line-light border-solid rounded-xl shadow md:min-w-[390px] min-w-[300px] lg:mt-7"
  >
    <!-- Subtotal -->
    <div class="flex items-center justify-between w-full">
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label v-else class="text-sm font-semibold leading-5 text-subtle uppercase">
        {{ $t('estimates.sub_total') }}
      </label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="flex items-center justify-center m-0 text-lg text-heading uppercase"
      >
        <BaseFormatMoney :amount="store.getSubTotal" :currency="defaultCurrency" />
      </label>
    </div>

    <!-- Net Total for per-item tax mode -->
    <div v-if="formData.tax_per_item === 'YES'">
      <div
        v-if="formData.tax_included"
        class="flex items-center justify-between w-full"
      >
        <BaseContentPlaceholders v-if="isLoading">
          <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
        </BaseContentPlaceholders>
        <label v-else class="text-sm font-semibold leading-5 text-muted uppercase">
          {{ $t('estimates.net_total') }}
        </label>

        <BaseContentPlaceholders v-if="isLoading">
          <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
        </BaseContentPlaceholders>
        <label
          v-else
          class="flex items-center justify-center m-0 text-lg text-heading uppercase"
        >
          <BaseFormatMoney :amount="store.getNetTotal" :currency="currency" />
        </label>
      </div>
    </div>

    <!-- Item-wise tax breakdown -->
    <div
      v-for="tax in itemWiseTaxes"
      :key="tax.tax_type_id"
      class="flex items-center justify-between w-full"
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else-if="formData.tax_per_item === 'YES'"
        class="m-0 text-sm font-semibold leading-5 text-muted uppercase"
      >
        <template v-if="tax.calculation_type === 'percentage'">
          {{ tax.name }} - {{ tax.percent }}%
        </template>
        <template v-else>
          {{ tax.name }} -
          <BaseFormatMoney :amount="tax.fixed_amount" :currency="defaultCurrency" />
        </template>
      </label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else-if="formData.tax_per_item === 'YES'"
        class="flex items-center justify-center m-0 text-lg text-heading uppercase"
      >
        <BaseFormatMoney :amount="tax.amount" :currency="defaultCurrency" />
      </label>
    </div>

    <!-- Global Discount -->
    <div
      v-if="formData.discount_per_item === 'NO' || formData.discount_per_item === null"
      class="flex items-center justify-between w-full mt-2"
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label v-else class="text-sm font-semibold leading-5 text-subtle uppercase">
        {{ $t('estimates.discount') }}
      </label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText
          :lines="1"
          class="w-24 h-8 border border-line-light rounded-md"
        />
      </BaseContentPlaceholders>
      <div v-else class="flex" style="width: 140px" role="group">
        <BaseInput
          v-model="totalDiscount"
          class="border-r-0 focus:border-r-2 rounded-tr-sm rounded-br-sm h-[38px]"
        />
        <BaseDropdown position="bottom-end">
          <template #activator>
            <BaseButton
              class="p-2 rounded-none rounded-tr-md rounded-br-md"
              type="button"
              variant="white"
            >
              <span class="flex items-center">
                {{ formData.discount_type === 'fixed' ? defaultCurrencySymbol : '%' }}
                <BaseIcon name="ChevronDownIcon" class="w-4 h-4 ml-1 text-muted" />
              </span>
            </BaseButton>
          </template>

          <BaseDropdownItem @click="selectFixed">
            {{ $t('general.fixed') }}
          </BaseDropdownItem>

          <BaseDropdownItem @click="selectPercentage">
            {{ $t('general.percentage') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>
    </div>

    <!-- Net Total for global tax mode -->
    <div
      v-if="formData.tax_per_item === 'NO' || formData.tax_per_item === null"
      class="flex items-center justify-between w-full mt-2"
    >
      <div
        v-if="formData.tax_included"
        class="flex items-center justify-between w-full"
      >
        <BaseContentPlaceholders v-if="isLoading">
          <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
        </BaseContentPlaceholders>
        <label v-else class="text-sm font-semibold leading-5 text-muted uppercase">
          {{ $t('estimates.net_total') }}
        </label>

        <BaseContentPlaceholders v-if="isLoading">
          <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
        </BaseContentPlaceholders>
        <label
          v-else
          class="flex items-center justify-center m-0 text-lg text-heading uppercase"
        >
          <BaseFormatMoney :amount="store.getNetTotal" :currency="currency" />
        </label>
      </div>
    </div>

    <!-- Global taxes list -->
    <div
      v-if="formData.tax_per_item === 'NO' || formData.tax_per_item === null"
    >
      <div
        v-for="(tax, index) in taxes"
        :key="tax.id"
        class="flex items-center justify-between w-full mt-2 text-sm"
      >
        <label v-if="tax.calculation_type === 'percentage'" class="font-semibold leading-5 text-muted uppercase">
          {{ tax.name }} ({{ tax.percent }} %)
        </label>
        <label v-else class="font-semibold leading-5 text-muted uppercase">
          {{ tax.name }} (<BaseFormatMoney :amount="tax.fixed_amount" :currency="currency" />)
        </label>
        <label class="flex items-center justify-center text-lg text-heading">
          <BaseFormatMoney :amount="tax.amount" :currency="currency" />
          <BaseIcon
            name="TrashIcon"
            class="h-5 ml-2 cursor-pointer"
            @click="removeTax(tax.id)"
          />
        </label>
      </div>
    </div>

    <!-- Add tax popup -->
    <div
      v-if="formData.tax_per_item === 'NO' || formData.tax_per_item === null"
      ref="taxModal"
      class="float-right pt-2 pb-4"
    >
      <TaxSelectPopup
        :store-prop="storeProp"
        :store="store"
        :type="taxPopupType"
        @select:tax-type="onSelectTax"
      />
    </div>

    <!-- Total Amount -->
    <div
      class="flex items-center justify-between w-full pt-2 mt-5 border-t border-line-light border-solid"
    >
      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label v-else class="m-0 text-sm font-semibold leading-5 text-subtle uppercase">
        {{ $t('estimates.total') }} {{ $t('estimates.amount') }}:
      </label>

      <BaseContentPlaceholders v-if="isLoading">
        <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
      </BaseContentPlaceholders>
      <label
        v-else
        class="flex items-center justify-center text-lg uppercase text-primary-400"
      >
        <BaseFormatMoney :amount="store.getTotal" :currency="defaultCurrency" />
      </label>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import TaxSelectPopup from './TaxSelectPopup.vue'
import { generateClientId } from '../../../utils'
import type { Currency } from '../../../types/domain/currency'
import type { TaxType } from '../../../types/domain/tax'
import type { DocumentFormData, DocumentTax, DocumentStore, DocumentItem } from './use-document-calculations'

interface Props {
  store: DocumentStore & {
    $patch: (fn: (state: Record<string, unknown>) => void) => void
    [key: string]: unknown
  }
  storeProp: string
  taxPopupType?: string
  currency?: Currency | Record<string, unknown> | string
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  taxPopupType: '',
  currency: '',
  isLoading: false,
})

const taxModal = ref<HTMLElement | null>(null)

const formData = computed<DocumentFormData>(() => {
  return props.store[props.storeProp] as DocumentFormData
})

const defaultCurrency = computed(() => {
  if (props.currency) {
    return props.currency
  }
  return null
})

const defaultCurrencySymbol = computed<string>(() => {
  const curr = defaultCurrency.value as Record<string, unknown> | null
  return (curr?.symbol as string) ?? '$'
})

watch(
  () => formData.value.items,
  () => setDiscount(),
  { deep: true },
)

const totalDiscount = computed<number>({
  get: () => formData.value.discount,
  set: (newValue: number) => {
    formData.value.discount = newValue
    setDiscount()
  },
})

const taxes = computed<DocumentTax[]>({
  get: () => formData.value.taxes,
  set: (value: DocumentTax[]) => {
    props.store.$patch((state: Record<string, unknown>) => {
      ;(state[props.storeProp] as DocumentFormData).taxes = value
    })
  },
})

interface AggregatedTax {
  tax_type_id: number
  amount: number
  percent: number | null
  name: string
  calculation_type: string | null
  fixed_amount: number
}

const itemWiseTaxes = computed<AggregatedTax[]>(() => {
  const result: AggregatedTax[] = []
  formData.value.items.forEach((item: DocumentItem) => {
    if (item.taxes) {
      item.taxes.forEach((tax: Partial<DocumentTax>) => {
        const found = result.find((_tax) => _tax.tax_type_id === tax.tax_type_id)
        if (found) {
          found.amount += tax.amount ?? 0
        } else if (tax.tax_type_id) {
          result.push({
            tax_type_id: tax.tax_type_id,
            amount: Math.round(tax.amount ?? 0),
            percent: tax.percent ?? null,
            name: tax.name ?? '',
            calculation_type: tax.calculation_type ?? null,
            fixed_amount: tax.fixed_amount ?? 0,
          })
        }
      })
    }
  })
  return result
})

function setDiscount(): void {
  const newValue = formData.value.discount

  if (formData.value.discount_type === 'percentage') {
    formData.value.discount_val = Math.round((props.store.getSubTotal * newValue) / 100)
    return
  }

  formData.value.discount_val = Math.round(newValue * 100)
}

function selectFixed(): void {
  if (formData.value.discount_type === 'fixed') return
  formData.value.discount_val = Math.round(formData.value.discount * 100)
  formData.value.discount_type = 'fixed'
}

function selectPercentage(): void {
  if (formData.value.discount_type === 'percentage') return
  const val = Math.round(formData.value.discount * 100) / 100
  formData.value.discount_val = Math.round((props.store.getSubTotal * val) / 100)
  formData.value.discount_type = 'percentage'
}

function onSelectTax(selectedTax: TaxType): void {
  let amount = 0
  if (
    selectedTax.calculation_type === 'percentage' &&
    props.store.getSubtotalWithDiscount &&
    selectedTax.percent
  ) {
    amount = Math.round(
      (props.store.getSubtotalWithDiscount * selectedTax.percent) / 100,
    )
  } else if (selectedTax.calculation_type === 'fixed') {
    amount = selectedTax.fixed_amount
  }

  const data: DocumentTax = {
    id: generateClientId(),
    name: selectedTax.name,
    percent: selectedTax.percent,
    tax_type_id: selectedTax.id,
    amount,
    calculation_type: selectedTax.calculation_type,
    fixed_amount: selectedTax.fixed_amount,
    compound_tax: selectedTax.compound_tax ?? false,
  }

  props.store.$patch((state: Record<string, unknown>) => {
    ;(state[props.storeProp] as DocumentFormData).taxes.push({ ...data })
  })
}

function updateTax(data: DocumentTax): void {
  const tax = formData.value.taxes.find((t: DocumentTax) => t.id === data.id)
  if (tax) {
    Object.assign(tax, { ...data })
  }
}

function removeTax(id: number | string): void {
  const index = formData.value.taxes.findIndex((tax: DocumentTax) => tax.id === id)
  props.store.$patch((state: Record<string, unknown>) => {
    ;(state[props.storeProp] as DocumentFormData).taxes.splice(index, 1)
  })
}
</script>
