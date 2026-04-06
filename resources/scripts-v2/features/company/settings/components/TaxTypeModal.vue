<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  required,
  minLength,
  maxLength,
  between,
  helpers,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { useCompanyStore } from '@v2/stores/company.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import { taxTypeService } from '@v2/api/services/tax-type.service'
import type { CreateTaxTypePayload } from '@v2/api/services/tax-type.service'

interface TaxTypeForm {
  id: number | null
  name: string
  calculation_type: string
  percent: number
  fixed_amount: number
  description: string
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
  percent: 0,
  fixed_amount: 0,
  description: '',
})

const defaultCurrency = computed(() => companyStore.selectedCompanyCurrency)

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'TaxTypeModal'
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
        percent: tax.percent,
        fixed_amount: tax.fixed_amount,
        description: tax.description ?? '',
      }
    }
  } else {
    isEdit.value = false
    resetForm()
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
      description: currentTaxType.value.description || null,
    }

    if (isEdit.value && currentTaxType.value.id) {
      await taxTypeService.update(currentTaxType.value.id, payload)
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.tax_types.updated_message',
      })
    } else {
      await taxTypeService.create(payload)
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.tax_types.created_message',
      })
    }

    isSaving.value = false
    if (modalStore.refreshData) {
      modalStore.refreshData()
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
    percent: 0,
    fixed_amount: 0,
    description: '',
  }
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
    @close="closeTaxTypeModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeTaxTypeModal"
        />
      </div>
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
