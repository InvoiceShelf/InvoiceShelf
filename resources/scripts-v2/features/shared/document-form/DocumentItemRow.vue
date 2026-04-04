<template>
  <tr class="box-border bg-surface border-b border-line-light">
    <td colspan="5" class="p-0 text-left align-top">
      <table class="w-full">
        <colgroup>
          <col style="width: 40%; min-width: 280px" />
          <col style="width: 10%; min-width: 120px" />
          <col style="width: 15%; min-width: 120px" />
          <col
            v-if="formData.discount_per_item === 'YES'"
            style="width: 15%; min-width: 160px"
          />
          <col style="width: 15%; min-width: 120px" />
        </colgroup>
        <tbody>
          <tr>
            <!-- Item Name + Description -->
            <td class="px-5 py-4 text-left align-top">
              <div class="flex justify-start">
                <div
                  class="flex items-center justify-center w-5 h-5 mt-2 mr-2 text-subtle cursor-move handle"
                >
                  <DragIcon />
                </div>
                <BaseItemSelect
                  type="Invoice"
                  :item="itemData"
                  :invalid="v$.name.$error"
                  :invalid-description="v$.description.$error"
                  :taxes="itemData.taxes"
                  :index="index"
                  :store-prop="storeProp"
                  :store="store"
                  @search="searchVal"
                  @select="onSelectItem"
                />
              </div>
            </td>

            <!-- Quantity -->
            <td class="px-5 py-4 text-right align-top">
              <BaseInput
                v-model="quantity"
                :invalid="v$.quantity.$error"
                :content-loading="loading"
                type="number"
                small
                step="any"
                @change="syncItemToStore()"
                @input="v$.quantity.$touch()"
              />
            </td>

            <!-- Price -->
            <td class="px-5 py-4 text-left align-top">
              <div class="flex flex-col">
                <div class="flex-auto flex-fill bd-highlight">
                  <div class="relative w-full">
                    <BaseMoney
                      :key="selectedCurrency?.id ?? 'default'"
                      v-model="price"
                      :invalid="v$.price.$error"
                      :content-loading="loading"
                      :currency="selectedCurrency"
                    />
                  </div>
                </div>
              </div>
            </td>

            <!-- Discount -->
            <td
              v-if="formData.discount_per_item === 'YES'"
              class="px-5 py-4 text-left align-top"
            >
              <div class="flex flex-col">
                <div class="flex" style="width: 120px" role="group">
                  <BaseInput
                    v-model="discount"
                    :invalid="v$.discount_val.$error"
                    :content-loading="loading"
                    class="border-r-0 focus:border-r-2 rounded-tr-sm rounded-br-sm h-[38px]"
                  />
                  <BaseDropdown position="bottom-end">
                    <template #activator>
                      <BaseButton
                        :content-loading="loading"
                        class="rounded-tr-md rounded-br-md !p-2 rounded-none"
                        type="button"
                        variant="white"
                      >
                        <span class="flex items-center">
                          {{
                            itemData.discount_type === 'fixed'
                              ? currencySymbol
                              : '%'
                          }}
                          <BaseIcon
                            name="ChevronDownIcon"
                            class="w-4 h-4 ml-1 text-muted"
                          />
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
            </td>

            <!-- Amount -->
            <td class="px-5 py-4 text-right align-top">
              <div class="flex items-center justify-end text-sm">
                <span>
                  <BaseContentPlaceholders v-if="loading">
                    <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
                  </BaseContentPlaceholders>

                  <BaseFormatMoney
                    v-else
                    :amount="total"
                    :currency="selectedCurrency"
                  />
                </span>
                <div class="flex items-center justify-center w-6 h-10 mx-2">
                  <BaseIcon
                    v-if="showRemoveButton"
                    class="h-5 text-body cursor-pointer"
                    name="TrashIcon"
                    @click="store.removeItem(index)"
                  />
                </div>
              </div>
            </td>
          </tr>

          <!-- Per-item taxes -->
          <tr v-if="formData.tax_per_item === 'YES'">
            <td class="px-5 py-4 text-left align-top" />
            <td colspan="4" class="px-5 py-4 text-left align-top">
              <BaseContentPlaceholders v-if="loading">
                <BaseContentPlaceholdersText
                  :lines="1"
                  class="w-24 h-8 border border-line-light rounded-md"
                />
              </BaseContentPlaceholders>

              <DocumentItemRowTax
                v-for="(tax, taxIndex) in itemData.taxes"
                v-else
                :key="tax.id"
                :index="taxIndex"
                :item-index="index"
                :tax-data="tax"
                :taxes="itemData.taxes ?? []"
                :discounted-total="total"
                :total-tax="totalSimpleTax"
                :total="subtotal"
                :currency="currency"
                :update-items="syncItemToStore"
                :ability="'create-invoice'"
                :store="store"
                :store-prop="storeProp"
                :discount="discount"
                @update="updateTax"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </td>
  </tr>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, between, maxLength, helpers, minValue } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import DocumentItemRowTax from './DocumentItemRowTax.vue'
import DragIcon from '@v2/components/icons/DragIcon.vue'
import type { Currency } from '../../../types/domain/currency'
import type { DocumentItem, DocumentFormData, DocumentTax } from './use-document-calculations'

interface Props {
  store: Record<string, unknown> & {
    removeItem: (index: number) => void
    updateItem: (data: Record<string, unknown>) => void
    $patch: (fn: (state: Record<string, unknown>) => void) => void
  }
  storeProp: string
  itemData: DocumentItem
  index: number
  type?: string
  loading?: boolean
  currency: Currency | Record<string, unknown>
  invoiceItems: DocumentItem[]
  itemValidationScope?: string
}

interface Emits {
  (e: 'update', data: Record<string, unknown>): void
  (e: 'remove', index: number): void
  (e: 'itemValidate', valid: boolean): void
}

const props = withDefaults(defineProps<Props>(), {
  type: '',
  loading: false,
  itemValidationScope: '',
})

const emit = defineEmits<Emits>()

const { t } = useI18n()

const formData = computed<DocumentFormData>(() => {
  return props.store[props.storeProp] as DocumentFormData
})

const currencySymbol = computed<string>(() => {
  const curr = props.currency as Record<string, unknown>
  return (curr?.symbol as string) ?? '$'
})

const quantity = computed<number>({
  get: () => props.itemData.quantity,
  set: (newValue: number) => {
    updateItemAttribute('quantity', parseFloat(String(newValue)))
  },
})

const price = computed<number>({
  get: () => props.itemData.price / 100,
  set: (newValue: number) => {
    const priceInCents = Math.round(newValue * 100)
    updateItemAttribute('price', priceInCents)
    setDiscount()
  },
})

const subtotal = computed<number>(() => {
  return Math.round(props.itemData.price * props.itemData.quantity)
})

const discount = computed<number>({
  get: () => props.itemData.discount,
  set: (newValue: number) => {
    updateItemAttribute('discount', newValue)
    setDiscount()
  },
})

const total = computed<number>(() => {
  return subtotal.value - props.itemData.discount_val
})

const selectedCurrency = computed(() => {
  if (props.currency) {
    return props.currency
  }
  return null
})

const showRemoveButton = computed<boolean>(() => {
  return formData.value.items.length > 1
})

const totalSimpleTax = computed<number>(() => {
  const taxes = props.itemData.taxes ?? []
  return Math.round(
    taxes.reduce((sum: number, tax: Partial<DocumentTax>) => {
      return sum + (tax.amount ?? 0)
    }, 0),
  )
})

const totalTax = computed<number>(() => totalSimpleTax.value)

const rules = {
  name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  quantity: {
    required: helpers.withMessage(t('validation.required'), required),
    maxLength: helpers.withMessage(t('validation.amount_maxlength'), maxLength(20)),
  },
  price: {
    required: helpers.withMessage(t('validation.required'), required),
    maxLength: helpers.withMessage(t('validation.price_maxlength'), maxLength(20)),
  },
  discount_val: {
    between: helpers.withMessage(
      t('validation.discount_maxlength'),
      between(
        0,
        computed(() => Math.abs(subtotal.value)),
      ),
    ),
  },
  description: {
    maxLength: helpers.withMessage(t('validation.notes_maxlength'), maxLength(65000)),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => formData.value.items[props.index]),
  { $scope: props.itemValidationScope },
)

function updateTax(data: { index: number; item: DocumentTax }): void {
  props.store.$patch((state: Record<string, unknown>) => {
    const form = state[props.storeProp] as DocumentFormData
    form.items[props.index].taxes![data.index] = data.item
  })

  const itemTaxes = props.itemData.taxes ?? []
  const lastTax = itemTaxes[itemTaxes.length - 1]

  if (lastTax?.tax_type_id !== 0) {
    props.store.$patch((state: Record<string, unknown>) => {
      const form = state[props.storeProp] as DocumentFormData
      form.items[props.index].taxes!.push({
        id: crypto.randomUUID(),
        tax_type_id: 0,
        name: '',
        amount: 0,
        percent: null,
        calculation_type: null,
        fixed_amount: 0,
        compound_tax: false,
      })
    })
  }

  syncItemToStore()
}

function setDiscount(): void {
  const newValue = formData.value.items[props.index].discount
  const absoluteSubtotal = Math.abs(subtotal.value)

  if (props.itemData.discount_type === 'percentage') {
    updateItemAttribute('discount_val', Math.round((absoluteSubtotal * newValue) / 100))
  } else {
    updateItemAttribute(
      'discount_val',
      Math.min(Math.round(newValue * 100), absoluteSubtotal),
    )
  }
}

function searchVal(val: string): void {
  updateItemAttribute('name', val)
}

function onSelectItem(itm: Record<string, unknown>): void {
  props.store.$patch((state: Record<string, unknown>) => {
    const form = state[props.storeProp] as DocumentFormData
    const item = form.items[props.index]
    item.name = itm.name as string
    item.price = itm.price as number
    item.item_id = itm.id as number
    item.description = (itm.description as string | null) ?? null

    if (itm.unit) {
      item.unit_name = (itm.unit as Record<string, string>).name
    }

    if (form.tax_per_item === 'YES' && itm.taxes) {
      let idx = 0
      ;(itm.taxes as DocumentTax[]).forEach((tax) => {
        updateTax({ index: idx, item: { ...tax } })
        idx++
      })
    }

    if (form.exchange_rate) {
      item.price = Math.round(item.price / form.exchange_rate)
    }
  })

  syncItemToStore()
}

function selectFixed(): void {
  if (props.itemData.discount_type === 'fixed') return
  updateItemAttribute('discount_val', Math.round(props.itemData.discount * 100))
  updateItemAttribute('discount_type', 'fixed')
}

function selectPercentage(): void {
  if (props.itemData.discount_type === 'percentage') return
  updateItemAttribute('discount_val', (subtotal.value * props.itemData.discount) / 100)
  updateItemAttribute('discount_type', 'percentage')
}

function syncItemToStore(): void {
  const itemTaxes = formData.value.items?.[props.index]?.taxes ?? []

  const data = {
    ...formData.value.items[props.index],
    index: props.index,
    total: total.value,
    sub_total: subtotal.value,
    totalSimpleTax: totalSimpleTax.value,
    totalTax: totalTax.value,
    tax: totalTax.value,
    taxes: [...itemTaxes],
    tax_type_ids: itemTaxes.flatMap((tax) =>
      tax.tax_type_id ? [tax.tax_type_id] : [],
    ),
  }

  props.store.updateItem(data)
}

function updateItemAttribute(attribute: string, value: unknown): void {
  props.store.$patch((state: Record<string, unknown>) => {
    const form = state[props.storeProp] as DocumentFormData
    ;(form.items[props.index] as Record<string, unknown>)[attribute] = value
  })

  syncItemToStore()
}
</script>
