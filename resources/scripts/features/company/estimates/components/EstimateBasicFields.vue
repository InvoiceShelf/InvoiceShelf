<template>
  <div class="md:grid-cols-12 grid-cols-1 md:gap-x-6 mt-6 mb-8 grid gap-y-5">
    <BaseCustomerSelectPopup
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="estimate"
      class="col-span-6 pr-0"
    />

    <BaseInputGrid
      class="col-span-6 rounded-xl shadow border border-line-light bg-surface p-5"
    >
      <BaseInputGroup
        :label="$t('reports.estimates.estimate_date')"
        :content-loading="isLoading"
        required
        :error="v.estimate_date.$error && v.estimate_date.$errors[0].$message"
      >
        <BaseDatePicker
          v-model="estimateStore.newEstimate.estimate_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('estimates.expiry_date')"
        :content-loading="isLoading"
      >
        <BaseDatePicker
          v-model="estimateStore.newEstimate.expiry_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('estimates.estimate_number')"
        :content-loading="isLoading"
        required
        :error="
          v.estimate_number.$error && v.estimate_number.$errors[0].$message
        "
      >
        <BaseInput
          v-model="estimateStore.newEstimate.estimate_number"
          :content-loading="isLoading"
        />
      </BaseInputGroup>

      <ExchangeRateConverter
        store-prop="newEstimate"
        :v="v"
        :is-loading="isLoading"
        :is-edit="isEdit"
        :customer-currency="estimateStore.newEstimate.currency_id"
      />
      <!-- Document-level custom fields sit with the number and the dates:
           they are attributes of the document, not a separate section. -->
      <CustomFieldInput
        v-for="field in customFields"
        :key="field.id"
        :custom-field-scope="customFieldScope"
        :field="field"
      />
    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import { ExchangeRateConverter } from '../../../shared/document-form'
import { useEstimateStore } from '../store'
import CustomFieldInput from '@/scripts/features/shared/custom-fields/CustomFieldInput.vue'
import { useCustomFields } from '@/scripts/features/shared/custom-fields/use-custom-fields'

interface ValidationField {
  $error: boolean
  $errors: Array<{ $message: string }>
  $touch: () => void
}

interface Props {
  v: Record<string, ValidationField>
  isLoading?: boolean
  isEdit?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
})

const estimateStore = useEstimateStore()

const customFieldScope = 'newEstimate'

const customFields = useCustomFields({
  store: estimateStore,
  storeProp: 'newEstimate',
  type: 'Estimate',
  isEdit: () => props.isEdit === true,
})
</script>
