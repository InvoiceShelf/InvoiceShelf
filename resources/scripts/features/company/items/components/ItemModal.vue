<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'
import { useItemStore } from '../store'
import { useTaxTypes } from '../use-tax-types'
import ItemUnitModal from '@/scripts/features/company/settings/components/ItemUnitModal.vue'
import { useNotificationStore } from '../../../../stores/notification.store'
import {
  handleApiError,
  getErrorTranslationKey,
} from '../../../../utils/error-handling'
import type { TaxType } from '@/scripts/types/domain/tax'

interface TaxOption {
  id: number
  name: string
  percent: number
  fixed_amount: number
  calculation_type: string | null
  tax_name: string
}

interface ItemFormState {
  name: string
  description: string
  price: number
  unit_id: string | number | null
  taxes: TaxOption[]
}

const ABILITIES = {
  VIEW_TAX_TYPE: 'view-tax-type',
} as const

interface Emits {
  (e: 'newItem', item: unknown): void
}

const emit = defineEmits<Emits>()

const modalStore = useModalStore()
const itemStore = useItemStore()
const companyStore = useCompanyStore()
const userStore = useUserStore()
const notificationStore = useNotificationStore()
const { taxTypes, fetchTaxTypes } = useTaxTypes()

const { t } = useI18n()
const isLoading = ref<boolean>(false)
const triedSubmit = ref<boolean>(false)
const taxPerItemSetting = ref<string>(
  companyStore.selectedCompanySettings.tax_per_item || 'NO'
)

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'ItemModal'
)

// Local form state owned by this modal. ItemModal is permanently mounted (inside
// DocumentItemsTable, via BaseModal's `static` dialog); @vuelidate did not track
// this reactive form's values in that context (it kept validating an empty
// snapshot), so validation here is done with plain reactive computeds instead —
// the same reactivity the price/taxes computeds below already rely on.
const form = reactive<ItemFormState>({
  name: '',
  description: '',
  price: 0,
  unit_id: '',
  taxes: [],
})

const nameError = computed<string>(() => {
  const value = (form.name ?? '').trim()
  if (!value) {
    return t('validation.required')
  }
  if (value.length < 3) {
    return t('validation.name_min_length', { count: 3 })
  }
  return ''
})

const descriptionError = computed<string>(() => {
  if ((form.description ?? '').length > 255) {
    return t('validation.description_maxlength', { count: 255 })
  }
  return ''
})

const isFormValid = computed<boolean>(
  () => !nameError.value && !descriptionError.value
)

const price = computed<number>({
  get: () => form.price / 100,
  set: (value: number) => {
    form.price = Math.round(value * 100)
  },
})

const taxes = computed<TaxOption[]>({
  get: () =>
    form.taxes?.map((tax) => {
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
    }) ?? [],
  set: (value: TaxOption[]) => {
    form.taxes = value
  },
})

const isTaxPerItemEnabled = computed<boolean>(() => {
  return taxPerItemSetting.value === 'YES'
})

const getTaxTypes = computed<TaxOption[]>(() => {
  return taxTypes.value.map((tax: TaxType) => {
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

// Reset + prefill the form every time the modal opens. The typed item-search
// text is handed in via modalStore.data.name by BaseItemSelect.
watch(modalActive, (active) => {
  if (!active) {
    return
  }
  const data = modalStore.data as { name?: string } | null
  Object.assign(form, {
    name: data?.name ?? '',
    description: '',
    price: 0,
    unit_id: '',
    taxes: [],
  })
  triedSubmit.value = false
})

onMounted(async () => {
  await itemStore.fetchItemUnits({ limit: 'all' })

  if (userStore.hasAbilities(ABILITIES.VIEW_TAX_TYPE)) {
    await fetchTaxTypes()
  }
})

async function submitItemData(): Promise<void> {
  triedSubmit.value = true

  if (!isFormValid.value) {
    notificationStore.showNotification({
      type: 'error',
      message: nameError.value || descriptionError.value,
    })
    return
  }

  const data: Record<string, unknown> = {
    name: form.name,
    description: form.description,
    price: form.price,
    unit_id: form.unit_id || null,
    taxes: (form.taxes ?? []).map((tax) => ({
      tax_type_id: tax.id,
      amount:
        tax.calculation_type === 'fixed'
          ? tax.fixed_amount
          : Math.round((form.price / 100) * tax.percent),
      percent: tax.percent,
      fixed_amount: tax.fixed_amount,
      calculation_type: tax.calculation_type,
      name: tax.name,
      collective_tax: 0,
    })),
  }

  isLoading.value = true

  try {
    const res = await itemStore.addItem(data)
    isLoading.value = false
    if (res.data && modalStore.refreshData) {
      modalStore.refreshData(res.data)
    }
    closeItemModal()
  } catch (err: unknown) {
    isLoading.value = false
    const normalized = handleApiError(err)
    const translationKey = getErrorTranslationKey(normalized.message)
    notificationStore.showNotification({
      type: 'error',
      message: translationKey ? t(translationKey) : normalized.message,
    })
  }
}

function addItemUnit(): void {
  modalStore.openModal({
    title: t('settings.customization.items.add_item_unit'),
    componentName: 'ItemUnitModal',
    size: 'sm',
    refreshData: (unit: { id: number }) => {
      form.unit_id = unit.id
    },
  })
}

function closeItemModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    triedSubmit.value = false
  }, 300)
}
</script>

<template>
  <BaseModal :show="modalActive" closable @close="closeItemModal">
    <template #header>
      {{ modalStore.title }}
    </template>
    <div class="item-modal">
      <form action="" @submit.prevent="submitItemData">
        <div class="px-8 py-8 sm:p-6">
          <BaseInputGrid layout="one-column">
            <BaseInputGroup
              :label="$t('items.name')"
              required
              :error="triedSubmit ? nameError : ''"
            >
              <BaseInput
                v-model="form.name"
                type="text"
                :invalid="Boolean(triedSubmit && nameError)"
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
                v-model="form.unit_id"
                label="name"
                :options="itemStore.itemUnits"
                value-prop="id"
                :can-deselect="false"
                :can-clear="false"
                :placeholder="$t('items.select_a_unit')"
                searchable
                track-by="name"
              >
                <template #action>
                  <BaseSelectAction @click="addItemUnit">
                    <BaseIcon
                      name="PlusCircleIcon"
                      class="h-4 mr-2 -ml-2 text-center text-primary-400"
                    />
                    {{ $t('settings.customization.items.add_item_unit') }}
                  </BaseSelectAction>
                </template>
              </BaseMultiselect>
              <ItemUnitModal />
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
              :error="triedSubmit ? descriptionError : ''"
            >
              <BaseTextarea
                v-model="form.description"
                rows="4"
                cols="50"
                :invalid="Boolean(triedSubmit && descriptionError)"
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
            {{ $t('general.save') }}
          </BaseButton>
        </div>
      </form>
    </div>
  </BaseModal>
</template>
