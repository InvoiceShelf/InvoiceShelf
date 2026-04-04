<template>
  <BasePage class="relative estimate-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem
            :title="$t('estimates.estimate', 2)"
            to="/admin/estimates"
          />
          <BaseBreadcrumbItem
            v-if="isEdit"
            :title="$t('estimates.edit_estimate')"
            to="#"
            active
          />
          <BaseBreadcrumbItem v-else :title="$t('estimates.new_estimate')" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <router-link
            v-if="isEdit"
            :to="`/estimates/pdf/${estimateStore.newEstimate.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            :content-loading="isLoadingContent"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                :class="slotProps.class"
                name="ArrowDownOnSquareIcon"
              />
            </template>
            {{ $t('estimates.save_estimate') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields -->
      <EstimateBasicFields
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
      />

      <BaseScrollPane>
        <!-- Estimate Items -->
        <DocumentItemsTable
          :currency="estimateStore.newEstimate.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="estimateValidationScope"
          :store="estimateStore"
          store-prop="newEstimate"
        />

        <!-- Estimate Footer Section -->
        <div
          class="block mt-10 estimate-foot lg:flex lg:justify-between lg:items-start"
        >
          <div class="relative w-full lg:w-1/2">
            <!-- Estimate Custom Notes -->
            <DocumentNotes
              :store="estimateStore"
              store-prop="newEstimate"
              :fields="estimateNoteFieldList"
              type="Estimate"
            />

            <!-- Estimate Template Button -->
            <TemplateSelectButton
              :store="estimateStore"
              store-prop="newEstimate"
              :is-mark-as-default="isMarkAsDefault"
            />
          </div>

          <DocumentTotals
            :currency="estimateStore.newEstimate.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="estimateStore"
            store-prop="newEstimate"
            tax-popup-type="estimate"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useEstimateStore } from '../store'
import EstimateBasicFields from '../components/EstimateBasicFields.vue'
import {
  DocumentItemsTable,
  DocumentTotals,
  DocumentNotes,
  TemplateSelectButton,
} from '../../../shared/document-form'

const estimateStore = useEstimateStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const estimateValidationScope = 'newEstimate'
const isSaving = ref<boolean>(false)
const isMarkAsDefault = ref<boolean>(false)

const estimateNoteFieldList = ref<Record<string, unknown> | null>(null)

const isLoadingContent = computed<boolean>(
  () => estimateStore.isFetchingInitialSettings,
)

const pageTitle = computed<string>(() =>
  isEdit.value ? t('estimates.edit_estimate') : t('estimates.new_estimate'),
)

const isEdit = computed<boolean>(() => route.name === 'estimates.edit')

const rules = {
  estimate_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  estimate_number: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  reference_number: {
    maxLength: helpers.withMessage(t('validation.price_maxlength'), maxLength(255)),
  },
  customer_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  exchange_rate: {
    required: requiredIf(() => {
      helpers.withMessage(t('validation.required'), required)
      return estimateStore.showExchangeRate
    }),
    decimal: helpers.withMessage(t('validation.valid_exchange_rate'), decimal),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => estimateStore.newEstimate),
  { $scope: estimateValidationScope },
)

// Initialization
estimateStore.resetCurrentEstimate()
v$.value.$reset
estimateStore.fetchEstimateInitialSettings(
  isEdit.value,
  { id: route.params.id as string, query: route.query as Record<string, string> },
)

watch(
  () => estimateStore.newEstimate.customer,
  (newVal) => {
    if (newVal && (newVal as Record<string, unknown>).currency) {
      estimateStore.newEstimate.selectedCurrency = (
        newVal as Record<string, unknown>
      ).currency as Record<string, unknown>
    }
  },
)

async function submitForm(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  const data: Record<string, unknown> = {
    ...structuredClone(estimateStore.newEstimate),
    sub_total: estimateStore.getSubTotal,
    total: estimateStore.getTotal,
    tax: estimateStore.getTotalTax,
  }

  const items = data.items as Array<Record<string, unknown>>
  if (data.discount_per_item === 'YES') {
    items.forEach((item, index) => {
      if (item.discount_type === 'fixed') {
        items[index].discount = Math.round((item.discount as number) * 100)
      }
    })
  } else {
    if (data.discount_type === 'fixed') {
      data.discount = Math.round((data.discount as number) * 100)
    }
  }

  const taxes = data.taxes as Array<Record<string, unknown>>
  if (data.tax_per_item !== 'YES' && taxes.length) {
    data.tax_type_ids = taxes.map((tax) => tax.tax_type_id)
  }

  try {
    const action = isEdit.value
      ? estimateStore.updateEstimate
      : estimateStore.addEstimate

    const res = await action(data)
    if (res.data.data) {
      router.push(`/admin/estimates/${res.data.data.id}/view`)
    }
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}
</script>
