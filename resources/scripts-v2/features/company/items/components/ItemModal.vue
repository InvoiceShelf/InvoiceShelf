<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  required,
  minLength,
  maxLength,
  helpers,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '../../../../stores/modal.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useItemStore } from '../store'

// Tax type store - imported from original location
import { useTaxTypeStore } from '@/scripts/admin/stores/tax-type'

interface TaxOption {
  id: number
  name: string
  percent: number
  fixed_amount: number
  calculation_type: string | null
  tax_name: string
}

interface Emits {
  (e: 'newItem', item: unknown): void
}

const emit = defineEmits<Emits>()

const modalStore = useModalStore()
const itemStore = useItemStore()
const companyStore = useCompanyStore()
const taxTypeStore = useTaxTypeStore()

const { t } = useI18n()
const isLoading = ref<boolean>(false)
const taxPerItemSetting = ref<string>(
  companyStore.selectedCompanySettings.tax_per_item || 'NO'
)

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'ItemModal'
)

const price = computed<number>({
  get: () => itemStore.currentItem.price / 100,
  set: (value: number) => {
    itemStore.currentItem.price = Math.round(value * 100)
  },
})

const taxes = computed({
  get: () =>
    itemStore.currentItem.taxes.map((tax) => {
      if (tax) {
        const currencySymbol = companyStore.selectedCompanyCurrency?.symbol ?? '$'
        return {
          ...tax,
          tax_type_id: tax.id,
          tax_name: `${tax.name} (${
            tax.calculation_type === 'fixed'
              ? tax.fixed_amount + currencySymbol
              : tax.percent + '%'
          })`,
        }
      }
      return tax
    }),
  set: (value: TaxOption[]) => {
    itemStore.currentItem.taxes = value as unknown as typeof itemStore.currentItem.taxes
  },
})

const isTaxPerItemEnabled = computed<boolean>(() => {
  return taxPerItemSetting.value === 'YES'
})

const rules = {
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3)
    ),
  },
  description: {
    maxLength: helpers.withMessage(
      t('validation.description_maxlength', { count: 255 }),
      maxLength(255)
    ),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => itemStore.currentItem)
)

const getTaxTypes = computed<TaxOption[]>(() => {
  return taxTypeStore.taxTypes.map((tax) => {
    const currencyCode = companyStore.selectedCompanyCurrency?.code ?? 'USD'
    const amount =
      tax.calculation_type === 'fixed'
        ? new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currencyCode,
          }).format(tax.fixed_amount / 100)
        : `${tax.percent}%`

    return {
      ...tax,
      tax_name: `${tax.name} (${amount})`,
    }
  }) as TaxOption[]
})

onMounted(() => {
  v$.value.$reset()
  itemStore.fetchItemUnits({ limit: 'all' })
})

async function submitItemData(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  const data: Record<string, unknown> = {
    ...itemStore.currentItem,
    taxes: itemStore.currentItem.taxes.map((tax) => ({
      tax_type_id: tax.id,
      amount:
        tax.calculation_type === 'fixed'
          ? tax.fixed_amount
          : Math.round(price.value * tax.percent),
      percent: tax.percent,
      fixed_amount: tax.fixed_amount,
      calculation_type: tax.calculation_type,
      name: tax.name,
      collective_tax: 0,
    })),
  }

  isLoading.value = true

  const action = itemStore.isEdit ? itemStore.updateItem : itemStore.addItem

  try {
    const res = await action(data)
    isLoading.value = false
    if (res.data) {
      if (modalStore.refreshData) {
        modalStore.refreshData(res.data)
      }
    }
    closeItemModal()
  } catch {
    isLoading.value = false
  }
}

function closeItemModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    itemStore.resetCurrentItem()
    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal :show="modalActive" @close="closeItemModal">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeItemModal"
        />
      </div>
    </template>
    <div class="item-modal">
      <form action="" @submit.prevent="submitItemData">
        <div class="px-8 py-8 sm:p-6">
          <BaseInputGrid layout="one-column">
            <BaseInputGroup
              :label="$t('items.name')"
              required
              :error="v$.name.$error && v$.name.$errors[0].$message"
            >
              <BaseInput
                v-model="itemStore.currentItem.name"
                type="text"
                :invalid="v$.name.$error"
                @input="v$.name.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('items.price')">
              <BaseMoney
                :key="companyStore.selectedCompanyCurrency?.id"
                v-model="price"
                :currency="companyStore.selectedCompanyCurrency"
                class="
                  relative
                  w-full
                  focus:border focus:border-solid focus:border-primary
                "
              />
            </BaseInputGroup>

            <BaseInputGroup :label="$t('items.unit')">
              <BaseMultiselect
                v-model="itemStore.currentItem.unit_id"
                label="name"
                :options="itemStore.itemUnits"
                value-prop="id"
                :can-deselect="false"
                :can-clear="false"
                :placeholder="$t('items.select_a_unit')"
                searchable
                track-by="name"
              />
            </BaseInputGroup>

            <BaseInputGroup
              v-if="isTaxPerItemEnabled"
              :label="$t('items.taxes')"
            >
              <BaseMultiselect
                v-model="taxes"
                :options="getTaxTypes"
                mode="tags"
                label="tax_name"
                value-prop="id"
                class="w-full"
                :can-deselect="false"
                :can-clear="false"
                searchable
                track-by="tax_name"
                object
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('items.description')"
              :error="
                v$.description.$error && v$.description.$errors[0].$message
              "
            >
              <BaseTextarea
                v-model="itemStore.currentItem.description"
                rows="4"
                cols="50"
                :invalid="v$.description.$error"
                @input="v$.description.$touch()"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>
        <div
          class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
        >
          <BaseButton
            class="mr-3"
            variant="primary-outline"
            type="button"
            @click="closeItemModal"
          >
            {{ $t('general.cancel') }}
          </BaseButton>
          <BaseButton
            :loading="isLoading"
            :disabled="isLoading"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
            </template>
            {{ itemStore.isEdit ? $t('general.update') : $t('general.save') }}
          </BaseButton>
        </div>
      </form>
    </div>
  </BaseModal>
</template>
