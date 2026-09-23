<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  required,
  minLength,
  maxLength,
  between,
  helpers,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { taxTypeService } from '@/scripts/api/services/tax-type.service'
import type { CreateTaxTypePayload } from '@/scripts/api/services/tax-type.service'
import type {
  TaxType,
  TaxTypeTransactionType,
} from '@/scripts/types/domain/tax'

interface TaxTypeForm {
  id: number | null
  name: string
  calculation_type: string
  transaction_type: TaxTypeTransactionType
  percent: number
  fixed_amount: number
  compound_tax: boolean
  description: string
}

interface TaxTypeModalContext {
  transaction_type?: TaxTypeTransactionType
}

const modalStore = useModalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isEdit = ref<boolean>(false)

const currentTaxType = ref<TaxTypeForm>({
  id: null,
  name: '',
  calculation_type: 'percentage',
  transaction_type: 'sales',
  percent: 0,
  fixed_amount: 0,
  compound_tax: false,
  description: '',
})

const defaultCurrency = computed(() => companyStore.selectedCompanyCurrency)

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'TaxTypeModal'
)

const showCompoundToggle = computed<boolean>(
  () =>
    currentTaxType.value.calculation_type === 'percentage' &&
    currentTaxType.value.transaction_type === 'sales'
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3)
    ),
  },
  calculation_type: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  transaction_type: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  percent: {
    required: helpers.withMessage(t('validation.required'), required),
    between: helpers.withMessage(
      t('validation.enter_valid_tax_rate'),
      between(-100, 100)
    ),
  },
  fixed_amount: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  description: {
    maxLength: helpers.withMessage(
      t('validation.description_maxlength', { count: 255 }),
      maxLength(255)
    ),
  },
}))

const v$ = useVuelidate(rules, currentTaxType)

const fixedAmount = computed<number>({
  get: () => currentTaxType.value.fixed_amount / 100,
  set: (value: number) => {
    currentTaxType.value.fixed_amount = Math.round(value * 100)
  },
})

watch(
  [
    () => currentTaxType.value.calculation_type,
    () => currentTaxType.value.transaction_type,
  ],
  () => {
    if (!showCompoundToggle.value) {
      currentTaxType.value.compound_tax = false
    }
  }
)

async function setInitialData(): Promise<void> {
  if (modalStore.data && typeof modalStore.data === 'number') {
    isEdit.value = true
    const response = await taxTypeService.get(modalStore.data)
    if (response.data) {
      const tax = response.data
      currentTaxType.value = {
        id: tax.id,
        name: tax.name,
        calculation_type: tax.calculation_type ?? 'percentage',
        transaction_type: tax.transaction_type,
        percent: tax.percent,
        fixed_amount: tax.fixed_amount,
        compound_tax: tax.compound_tax ?? false,
        description: tax.description ?? '',
      }
    }
  } else {
    isEdit.value = false
    resetForm()
    const context = getModalContext(modalStore.data)
    if (context?.transaction_type) {
      currentTaxType.value.transaction_type = context.transaction_type
    }
  }
}

async function submitTaxTypeData(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true
  try {
    const payload: CreateTaxTypePayload = {
      name: currentTaxType.value.name,
      percent: currentTaxType.value.percent,
      fixed_amount: currentTaxType.value.fixed_amount,
      calculation_type: currentTaxType.value.calculation_type,
      transaction_type: currentTaxType.value.transaction_type,
      compound_tax: currentTaxType.value.compound_tax,
      description: currentTaxType.value.description || null,
    }

    let savedTaxType: TaxType
    if (isEdit.value && currentTaxType.value.id) {
      const response = await taxTypeService.update(currentTaxType.value.id, payload)
      savedTaxType = response.data
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.tax_types.updated_message',
      })
    } else {
      const response = await taxTypeService.create(payload)
      savedTaxType = response.data
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.tax_types.created_message',
      })
    }

    isSaving.value = false
    if (modalStore.refreshData) {
      modalStore.refreshData(savedTaxType)
    }
    closeTaxTypeModal()
  } catch {
    isSaving.value = false
  }
}

function resetForm(): void {
  currentTaxType.value = {
    id: null,
    name: '',
    calculation_type: 'percentage',
    transaction_type: 'sales',
    percent: 0,
    fixed_amount: 0,
    compound_tax: false,
    description: '',
  }
}

function getModalContext(data: unknown): TaxTypeModalContext | null {
  if (
    typeof data !== 'object' ||
    data === null ||
    !('transaction_type' in data)
  ) {
    return null
  }

  const transactionType = data.transaction_type
  if (transactionType !== 'sales' && transactionType !== 'purchases') {
    return null
  }

  return { transaction_type: transactionType }
}

function closeTaxTypeModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetForm()
    isEdit.value = false
    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal
    :show="modalActive"
    closable
    @close="closeTaxTypeModal"
    @open="setInitialData"
  >
    <template #header>
      {{ modalStore.title }}
    </template>
    <form action="" @submit.prevent="submitTaxTypeData">
      <div class="p-4 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('tax_types.name')"
            variant="horizontal"
            :error="v$.name.$error && v$.name.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="currentTaxType.name"
              :invalid="v$.name.$error"
              type="text"
              @input="v$.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('tax_types.tax_type')"
            variant="horizontal"
            required
          >
            <BaseSelectInput
              v-model="currentTaxType.calculation_type"
              :options="[
                { id: 'percentage', label: $t('tax_types.percentage') },
                { id: 'fixed', label: $t('tax_types.fixed_amount') },
              ]"
              :allow-empty="false"
              value-prop="id"
              label-prop="label"
              track-by="label"
              :searchable="false"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('tax_types.used_for')"
            :error="
              v$.transaction_type.$error &&
              v$.transaction_type.$errors[0].$message
            "
            variant="horizontal"
            required
          >
            <BaseSelectInput
              v-model="currentTaxType.transaction_type"
              :invalid="v$.transaction_type.$error"
              :options="[
                { id: 'sales', label: $t('tax_types.sales') },
                { id: 'purchases', label: $t('tax_types.purchases') },
              ]"
              :allow-empty="false"
              value-prop="id"
              label-prop="label"
              track-by="label"
              :searchable="false"
              @update:model-value="v$.transaction_type.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-if="currentTaxType.calculation_type === 'percentage'"
            :label="$t('tax_types.percent')"
            variant="horizontal"
            required
          >
            <BaseMoney
              v-model="currentTaxType.percent"
              :currency="{
                decimal: '.',
                thousands: ',',
                symbol: '% ',
                precision: 2,
                masked: false,
              }"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-else
            :label="$t('tax_types.fixed_amount')"
            variant="horizontal"
            required
          >
            <BaseMoney v-model="fixedAmount" :currency="defaultCurrency" />
          </BaseInputGroup>

          <BaseInputGroup
            v-if="showCompoundToggle"
            :label="$t('tax_types.compound_tax')"
            :help-text="$t('settings.tax_types.compound_tax_description')"
            variant="horizontal"
          >
            <BaseSwitch v-model="currentTaxType.compound_tax" />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('tax_types.description')"
            :error="
              v$.description.$error && v$.description.$errors[0].$message
            "
            variant="horizontal"
          >
            <BaseTextarea
              v-model="currentTaxType.description"
              :invalid="v$.description.$error"
              rows="4"
              cols="50"
              @input="v$.description.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>
      <div
        class="z-0 flex justify-end p-4 border-t border-solid border-line-default"
      >
        <BaseButton
          class="mr-3 text-sm"
          variant="primary-outline"
          type="button"
          @click="closeTaxTypeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
