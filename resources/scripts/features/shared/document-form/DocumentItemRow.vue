<template>
  <!-- Phones: the line as a card -->
  <div
    v-if="layout === 'card'"
    class="flex flex-col gap-3 p-4 border glass rounded-xl"
  >
    <div class="flex items-start gap-2">
      <BaseItemSelect
        type="Invoice"
        class="min-w-0"
        :item="itemData"
        :invalid="v$.name.$error"
        :invalid-description="v$.description.$error"
        :taxes="itemData.taxes"
        :index="index"
        :store-prop="storeProp"
        :store="store"
        @search="searchVal"
        @select="onSelectItem"
        @update:description="updateItemAttribute('description', $event)"
      />

      <BaseDropdown
        v-if="invoiceItems.length > 1"
        position="bottom-end"
        wrapper-class="flex shrink-0"
        :label="itemActionsLabel"
      >
        <template #activator>
          <span
            class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg text-muted hover:bg-hover-strong hover:text-heading"
          >
            <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
          </span>
        </template>

        <BaseDropdownItem v-if="index > 0" @click="emit('move', index, index - 1)">
          <BaseIcon name="ArrowUpIcon" class="w-5 h-5 mr-3 text-subtle" />
          {{ $t('invoices.item.move_up') }}
        </BaseDropdownItem>

        <BaseDropdownItem v-if="index < invoiceItems.length - 1" @click="emit('move', index, index + 1)">
          <BaseIcon name="ArrowDownIcon" class="w-5 h-5 mr-3 text-subtle" />
          {{ $t('invoices.item.move_down') }}
        </BaseDropdownItem>

        <BaseDropdownItem @click="store.removeItem(index)">
          <BaseIcon name="TrashIcon" class="w-5 h-5 mr-3 text-danger" />
          <span class="text-danger">{{ $t('invoices.item.remove') }}</span>
        </BaseDropdownItem>
      </BaseDropdown>
    </div>

    <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3">
      <label class="flex flex-col gap-1.5 min-w-0">
        <span class="text-xs font-medium text-muted">{{ $t('invoices.item.quantity') }}</span>
        <BaseInput
          v-model="quantity"
          :invalid="v$.quantity.$error"
          :content-loading="loading"
          type="number"
          inputmode="decimal"
          step="any"
          @change="syncItemToStore()"
          @input="v$.quantity.$touch()"
        />
      </label>

      <label class="flex flex-col gap-1.5 min-w-0">
        <span class="text-xs font-medium text-muted">{{ $t('invoices.item.price') }}</span>
        <BaseMoney
          :key="selectedCurrency?.id ?? 'default'"
          v-model="price"
          :invalid="v$.price.$error"
          :content-loading="loading"
          :currency="selectedCurrency"
        />
      </label>
    </div>

    <!-- Discount, taxes and custom fields stay folded away until needed -->
    <template v-if="hasOptions">
      <button
        type="button"
        class="flex items-center self-start gap-1.5 -my-1 py-1 text-sm font-medium rounded-md text-primary-600"
        :aria-expanded="showOptions"
        @click="showOptions = !showOptions"
      >
        <BaseIcon name="AdjustmentsHorizontalIcon" class="w-4 h-4" />
        {{ $t('invoices.item.options') }}
        <BaseIcon
          name="ChevronDownIcon"
          class="w-4 h-4 transition-transform"
          :class="showOptions ? 'rotate-180' : ''"
        />
      </button>

      <div v-show="showOptions" class="flex flex-col gap-3">
        <div v-if="formData.discount_per_item === 'YES'" class="flex flex-col gap-1.5">
          <span class="text-xs font-medium text-muted">{{ $t('invoices.item.discount') }}</span>
          <div class="flex" role="group">
            <BaseInput
              v-model="discount"
              :invalid="v$.discount_val.$error"
              :content-loading="loading"
              :aria-label="$t('invoices.item.discount')"
              inputmode="decimal"
              class="flex-1 min-w-0 [&_input]:rounded-r-none"
            />
            <BaseDropdown position="bottom-end" wrapper-class="flex" :label="discountTypeLabel">
              <template #activator>
                <span
                  class="flex items-center h-11 gap-1 px-3 text-sm border border-l-0 rounded-r-lg bg-surface border-control-border text-body"
                >
                  {{ itemData.discount_type === 'fixed' ? currencySymbol : '%' }}
                  <BaseIcon name="ChevronDownIcon" class="w-4 h-4 text-muted" />
                </span>
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

        <div v-if="formData.tax_per_item === 'YES'" class="flex flex-col gap-1.5">
          <DocumentItemRowTax
            v-for="(tax, taxIndex) in itemData.taxes"
            :key="tax.id"
            :index="taxIndex"
            :item-index="index"
            :tax-data="tax as DocumentTax"
            :taxes="(itemData.taxes ?? []) as DocumentTax[]"
            :discounted-total="total"
            :total-simple-tax="totalSimpleTax"
            :total="subtotal"
            :currency="currency"
            :update-items="syncItemToStore"
            :tax-types="taxTypes"
            :can-add-tax="canAddTax"
            :store="store"
            :store-prop="storeProp"
            :discount="discount"
            @update="updateTax"
            @tax-type-created="onTaxTypeCreated"
          />
        </div>

        <div v-if="lineCustomFields.length > 0" class="flex flex-col gap-3">
          <CustomFieldInput
            v-for="field in lineCustomFields"
            :key="field.id"
            :custom-field-scope="itemValidationScope"
            :field="field"
          />
        </div>
      </div>
    </template>

    <div class="flex items-baseline justify-between gap-3 pt-3 border-t border-line-light">
      <span class="text-sm text-muted">{{ $t('invoices.item.amount') }}</span>
      <span class="text-right">
        <BaseContentPlaceholders v-if="loading">
          <BaseContentPlaceholdersText :lines="1" class="w-20 h-5" />
        </BaseContentPlaceholders>
        <span v-else class="text-base font-semibold text-heading">
          <BaseFormatMoney :amount="total" :currency="selectedCurrency" />
        </span>
        <span v-if="showBaseCurrencyEquivalent" class="block mt-0.5 text-xs text-muted">
          <BaseFormatMoney :amount="baseCurrencyTotal" :currency="companyCurrency" />
        </span>
      </span>
    </div>
  </div>

  <tr v-else class="box-border border-b border-line-light">
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
                <button
                  ref="handle"
                  type="button"
                  class="
                    flex items-center justify-center w-6 h-8 mt-1 mr-1.5 rounded-md shrink-0 text-subtle cursor-move handle
                    focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus
                  "
                  :aria-label="$t('invoices.item.reorder', { position: index + 1, count: invoiceItems.length })"
                  @keydown.up.prevent="moveBy(-1)"
                  @keydown.down.prevent="moveBy(1)"
                >
                  <DragIcon aria-hidden="true" />
                </button>
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
                  @update:description="updateItemAttribute('description', $event)"
                />
              </div>
            </td>

            <!-- Quantity -->
            <td class="px-5 py-4 text-right align-top">
              <BaseInput
                v-model="quantity"
                :invalid="v$.quantity.$error"
                :content-loading="loading"
                :aria-label="$t('invoices.item.quantity')"
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
                      :aria-label="$t('invoices.item.price')"
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
                    :aria-label="$t('invoices.item.discount')"
                    class="border-r-0 focus:border-r-2 rounded-tr-sm rounded-br-sm h-[38px]"
                  />
                  <BaseDropdown position="bottom-end" :label="discountTypeLabel">
                    <template #activator>
                      <BaseButton
                        :content-loading="loading"
                        class="rounded-tr-md rounded-br-md !p-2 rounded-none"
                        tag="span"
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
                  <span
                    v-if="showBaseCurrencyEquivalent"
                    class="block text-xs text-muted mt-1"
                  >
                    <BaseFormatMoney
                      :amount="baseCurrencyTotal"
                      :currency="companyCurrency"
                    />
                  </span>
                </span>
                <div class="flex items-center justify-center w-8 h-10 mx-1">
                  <BaseIconButton
                    v-if="showRemoveButton"
                    icon="TrashIcon"
                    :label="$t('invoices.item.remove')"
                    size="sm"
                    tone="danger"
                    @click="store.removeItem(index)"
                  />
                </div>
              </div>
            </td>
          </tr>

          <!-- Per-item custom fields -->
          <tr v-if="lineCustomFields.length > 0">
            <td class="px-5 py-4 text-left align-top" />
            <td colspan="4" class="px-5 py-4 text-left align-top">
              <BaseInputGrid layout="three-column">
                <CustomFieldInput
                  v-for="field in lineCustomFields"
                  :key="field.id"
                  :custom-field-scope="itemValidationScope"
                  :field="field"
                />
              </BaseInputGrid>
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
                :tax-data="tax as DocumentTax"
                :taxes="(itemData.taxes ?? []) as DocumentTax[]"
                :discounted-total="total"
                :total-simple-tax="totalSimpleTax"
                :total="subtotal"
                :currency="currency"
                :update-items="syncItemToStore"
                :tax-types="taxTypes"
                :can-add-tax="canAddTax"
                :store="store"
                :store-prop="storeProp"
                :discount="discount"
                @update="updateTax"
                @tax-type-created="onTaxTypeCreated"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </td>
  </tr>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, between, maxLength, helpers, minValue } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useCompanyStore } from '../../../stores/company.store'
import DocumentItemRowTax from './DocumentItemRowTax.vue'
import CustomFieldInput from '@/scripts/features/shared/custom-fields/CustomFieldInput.vue'
import {
  buildLineCustomFields,
  type CustomFieldItem,
} from '@/scripts/features/shared/custom-fields/use-custom-fields'
import DragIcon from '@/scripts/components/icons/DragIcon.vue'
import { generateClientId } from '../../../utils'
import { announce } from '@/scripts/utils/page-focus'
import type { Currency } from '../../../types/domain/currency'
import type { TaxType } from '../../../types/domain/tax'
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
  layout?: 'row' | 'card'
  currency: Currency | Record<string, unknown>
  invoiceItems: DocumentItem[]
  itemValidationScope?: string
  itemCustomFields?: CustomFieldItem[]
  taxTypes?: TaxType[]
  canAddTax?: boolean
}

interface Emits {
  (e: 'update', data: Record<string, unknown>): void
  (e: 'remove', index: number): void
  (e: 'itemValidate', valid: boolean): void
  (e: 'taxTypeCreated', taxType: TaxType): void
  (e: 'move', from: number, to: number): void
}

const props = withDefaults(defineProps<Props>(), {
  type: '',
  loading: false,
  layout: 'row',
  itemValidationScope: '',
  itemCustomFields: () => [],
  taxTypes: () => [],
  canAddTax: false,
})

const emit = defineEmits<Emits>()

const { t } = useI18n()
const companyStore = useCompanyStore()

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

// The card's Options disclosure: shown when the line has anything to set
// beyond quantity and price, and open from the start when something is set
const hasOptions = computed<boolean>(() => {
  return formData.value.discount_per_item === 'YES'
    || formData.value.tax_per_item === 'YES'
    || lineCustomFields.value.length > 0
})

const showOptions = ref<boolean>(
  !!props.itemData.discount
  || (props.itemData.taxes ?? []).some((tax) => !!tax.tax_type_id),
)

const showRemoveButton = computed<boolean>(() => {
  return formData.value.items.length > 1
})

const itemActionsLabel = computed<string>(() => {
  return t('invoices.item.item_actions_for', { position: props.index + 1 })
})

const discountTypeLabel = computed<string>(() => {
  const type = props.itemData.discount_type === 'fixed' ? currencySymbol.value : '%'

  return t('invoices.item.discount_type', { type })
})

const handle = ref<HTMLButtonElement | null>(null)

// The keyboard's way to reorder: arrow keys on the drag handle
async function moveBy(step: number): Promise<void> {
  const to = props.index + step
  const count = props.invoiceItems.length

  if (to < 0 || to >= count) {
    return
  }

  emit('move', props.index, to)
  await nextTick()
  handle.value?.focus()
  announce(t('invoices.item.moved', { position: to + 1, count }))
}

// Base handed down to the tax rows: only the non-compound taxes count, so a
// compound row can never widen its own base through this value.
const totalSimpleTax = computed<number>(() => {
  const taxes = props.itemData.taxes ?? []
  return Math.round(
    taxes.reduce((sum: number, tax: Partial<DocumentTax>) => {
      if (tax.compound_tax) {
        return sum
      }
      return sum + (tax.amount ?? 0)
    }, 0),
  )
})

const totalCompoundTax = computed<number>(() => {
  const taxes = props.itemData.taxes ?? []
  return Math.round(
    taxes.reduce((sum: number, tax: Partial<DocumentTax>) => {
      if (tax.compound_tax) {
        return sum + (tax.amount ?? 0)
      }
      return sum
    }, 0),
  )
})

const totalTax = computed<number>(() => totalSimpleTax.value + totalCompoundTax.value)

const companyCurrency = computed(() => companyStore.selectedCompanyCurrency)

const showBaseCurrencyEquivalent = computed<boolean>(() => {
  return !!(formData.value.exchange_rate && (props.store as Record<string, unknown>).showExchangeRate)
})

const baseCurrencyTotal = computed<number>(() => {
  if (!formData.value.exchange_rate) return 0
  return Math.round(total.value * Number(formData.value.exchange_rate))
})

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
        id: generateClientId(),
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

function onTaxTypeCreated(taxType: TaxType): void {
  emit('taxTypeCreated', taxType)
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

/**
 * This line's answers: one entry per definition, seeded from whatever the
 * line already holds, and mirrored onto the line itself so they travel with
 * the item in the submitted payload.
 */
const lineCustomFields = ref<CustomFieldItem[]>([])

function seedLineCustomFields(saved: CustomFieldItem[] = []): void {
  lineCustomFields.value =
    props.itemCustomFields.length > 0
      ? buildLineCustomFields(props.itemCustomFields, saved)
      : []
}

watch(
  () => props.itemCustomFields,
  () => seedLineCustomFields((props.itemData.fields as CustomFieldItem[]) ?? []),
  { immediate: true }
)

// An edit screen fetches the document after the row is built, so the saved
// answers arrive later than the definitions.
watch(
  () => props.itemData.fields,
  (saved) => seedLineCustomFields((saved as CustomFieldItem[]) ?? [])
)

watch(
  lineCustomFields,
  (fields) => {
    props.itemData.custom_fields = fields
  },
  { deep: true, immediate: true }
)

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

  seedLineCustomFields((itm.fields as CustomFieldItem[]) ?? [])

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
    totalCompoundTax: totalCompoundTax.value,
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
