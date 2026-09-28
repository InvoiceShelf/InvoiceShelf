<template>
  <div
    class="space-y-3 md:rounded-xl md:border md:border-line-default md:bg-surface"
  >
    <div
      v-for="(line, index) in model"
      :key="index"
      class="rounded-xl border border-line-default p-4 md:rounded-none md:border-0 md:border-b md:border-line-light md:px-5 md:py-4"
    >
      <div class="grid grid-cols-2 items-start gap-4 md:grid-cols-12">
        <PurchaseField
          :label="$t('purchases.description')"
          :name="`${prefix}.${index}.description`"
          required
          class="col-span-2 md:col-span-4"
        >
          <BaseInput
            v-model="line.description"
            required
            :disabled="locked || linked"
            maxlength="1000"
          />
        </PurchaseField>
        <PurchaseField
          :label="$t('purchases.category')"
          :name="`${prefix}.${index}.expense_category_id`"
          required
          class="col-span-2 md:col-span-3"
        >
          <PurchaseLookupSelect
            v-model="line.expense_category_id"
            kind="category"
            :options="options.categories"
            required
            :disabled="locked || linked"
          />
        </PurchaseField>
        <PurchaseField
          :label="$t('purchases.quantity')"
          :name="`${prefix}.${index}.quantity`"
          required
          class="md:col-span-2"
        >
          <BaseInput
            v-model.number="line.quantity"
            type="number"
            min="0.01"
            step="0.01"
            required
            :disabled="locked"
          />
        </PurchaseField>
        <PurchaseField
          :label="$t('purchases.unit_price')"
          :name="`${prefix}.${index}.price`"
          required
          class="md:col-span-3"
        >
          <PurchaseMoney
            v-model="line.price"
            :currency="currency"
            :disabled="locked || linked"
          />
        </PurchaseField>
        <PurchaseField
          :label="$t('purchases.purchase_taxes')"
          :name="`${prefix}.${index}.tax_type_ids`"
          class="col-span-2 md:col-span-7"
        >
          <PurchaseLookupSelect
            v-model="line.tax_type_ids"
            kind="tax"
            :options="options.taxes"
            multiple
            :disabled="locked || linked"
            :placeholder="$t('purchases.select_taxes')"
          />
        </PurchaseField>
        <PurchaseField
          :label="$t('purchases.discount_percent')"
          :name="`${prefix}.${index}.discount`"
          class="md:col-span-2"
        >
          <BaseInput
            v-model.number="line.discount"
            type="number"
            min="0"
            max="100"
            step="0.01"
            :disabled="locked || linked"
          />
        </PurchaseField>
        <div class="flex items-end justify-end self-end md:col-span-3">
          <BaseButton
            v-if="!locked && model.length > 1"
            variant="white"
            type="button"
            :aria-label="$t('purchases.remove_line')"
            @click="model.splice(index, 1)"
          >
            <template #left="slotProps"
              ><BaseIcon name="TrashIcon" :class="slotProps.class" /></template
            >{{ $t('purchases.remove') }}
          </BaseButton>
        </div>
      </div>
    </div>
    <div v-if="!locked && !linked" class="md:p-4">
      <button
        type="button"
        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line-default text-sm font-medium text-primary-600 hover:border-primary-300 hover:bg-primary-50/60"
        @click="model.push(blankLine())"
      >
        <BaseIcon name="PlusIcon" class="h-5 w-5" />{{
          $t('purchases.add_line')
        }}
      </button>
    </div>
  </div>
</template>
<script setup lang="ts">
import type {
  PurchaseLine,
  PurchaseOptions,
} from '@/scripts/types/domain/purchase'
import type { Currency } from '@/scripts/types/domain/currency'
import { blankLine } from '../helpers'
import PurchaseLookupSelect from './PurchaseLookupSelect.vue'
import PurchaseField from './PurchaseField.vue'
import PurchaseMoney from './PurchaseMoney.vue'
const model = defineModel<PurchaseLine[]>({ required: true })
withDefaults(
  defineProps<{
    options: PurchaseOptions
    currency?: Currency | null
    locked?: boolean
    linked?: boolean
    prefix?: string
  }>(),
  { prefix: 'items', currency: null },
)
</script>
