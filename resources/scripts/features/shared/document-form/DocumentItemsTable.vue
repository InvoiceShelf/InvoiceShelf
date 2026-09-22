<template>
  <div class="rounded-xl border border-line-light shadow bg-surface">
    <!-- Single shared item-create modal for the whole table (one instance, not one
         per row — stacked HeadlessUI dialogs would otherwise close each other). -->
    <ItemModal />
    <TaxTypeModal />

    <!-- Tax Included Toggle -->
    <div
      v-if="taxIncludedSetting === 'YES'"
      class="flex items-center justify-end w-full px-6 text-base border-b border-line-light cursor-pointer text-primary-400 bg-surface"
    >
      <BaseSwitchSection
        v-model="taxIncludedField"
        :title="$t('settings.tax_types.tax_included')"
        :store="store"
        :store-prop="storeProp"
      />
    </div>

    <table class="text-center item-table min-w-full">
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

      <thead class="bg-surface-secondary border-b border-line-light">
        <tr>
          <th class="px-5 py-3 text-sm not-italic font-medium leading-5 text-left text-body">
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else class="pl-7">
              {{ $t('items.item', 2) }}
            </span>
          </th>
          <th class="px-5 py-3 text-sm not-italic font-medium leading-5 text-right text-body">
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else>
              {{ $t('invoices.item.quantity') }}
            </span>
          </th>
          <th class="px-5 py-3 text-sm not-italic font-medium leading-5 text-left text-body">
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else>
              {{ $t('invoices.item.price') }}
            </span>
          </th>
          <th
            v-if="formData.discount_per_item === 'YES'"
            class="px-5 py-3 text-sm not-italic font-medium leading-5 text-left text-body"
          >
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else>
              {{ $t('invoices.item.discount') }}
            </span>
          </th>
          <th class="px-5 py-3 text-sm not-italic font-medium leading-5 text-right text-body">
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else class="pr-10 column-heading">
              {{ $t('invoices.item.amount') }}
            </span>
          </th>
        </tr>
      </thead>

      <draggable
        v-model="formData.items"
        item-key="id"
        tag="tbody"
        handle=".handle"
      >
        <template #item="{ element, index }">
          <DocumentItemRow
            :key="element.id"
            :index="index"
            :item-data="element"
            :loading="isLoading"
            :currency="defaultCurrency"
            :item-validation-scope="itemValidationScope"
            :item-custom-fields="itemCustomFields"
            :invoice-items="formData.items"
            :tax-types="availableTaxTypes"
            :can-add-tax="canAddTax"
            :store="store"
            :store-prop="storeProp"
            @tax-type-created="upsertAvailableTaxType"
          />
        </template>
      </draggable>
    </table>

    <div
      class="flex items-center justify-center w-full px-6 py-3 text-base border-t border-line-light cursor-pointer text-primary-400 hover:bg-primary-100"
      @click="store.addItem()"
    >
      <BaseIcon name="PlusCircleIcon" class="mr-2" />
      {{ $t('general.add_new_item') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import draggable from 'vuedraggable'
import DocumentItemRow from './DocumentItemRow.vue'
import { useCustomFieldDefinitions } from '@/scripts/features/shared/custom-fields/use-custom-fields'
import ItemModal from '@/scripts/features/company/items/components/ItemModal.vue'
import TaxTypeModal from '@/scripts/features/company/settings/components/TaxTypeModal.vue'
import { useUserStore } from '../../../stores/user.store'
import { taxTypeService } from '../../../api/services/tax-type.service'
import { ABILITIES } from '../../../config/abilities'
import type { Currency } from '../../../types/domain/currency'
import type { TaxType } from '../../../types/domain/tax'
import type { DocumentFormData } from './use-document-calculations'

interface Props {
  store: Record<string, unknown> & {
    addItem: () => void
  }
  storeProp: string
  currency: Currency | Record<string, unknown> | string | null
  isLoading?: boolean
  itemValidationScope?: string
  taxIncludedSetting?: string
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  itemValidationScope: '',
  taxIncludedSetting: 'NO',
})

const itemCustomFields = useCustomFieldDefinitions('Item')

const userStore = useUserStore()
const availableTaxTypes = ref<TaxType[]>([])

const canAddTax = computed<boolean>(() => {
  return userStore.hasAbilities(ABILITIES.CREATE_TAX_TYPE)
})

onMounted(async () => {
  try {
    const response = await taxTypeService.list({
      limit: 'all',
      transaction_type: 'sales',
    })
    availableTaxTypes.value = response.data
  } catch {
    // Silently fail
  }
})

function upsertAvailableTaxType(taxType: TaxType): void {
  const index = availableTaxTypes.value.findIndex(({ id }) => id === taxType.id)

  if (index === -1) {
    availableTaxTypes.value.push(taxType)
    return
  }

  availableTaxTypes.value.splice(index, 1, taxType)
}

const formData = computed<DocumentFormData>(() => {
  return props.store[props.storeProp] as DocumentFormData
})

const defaultCurrency = computed(() => {
  if (props.currency) {
    return props.currency
  }
  return null
})

const taxIncludedField = computed<boolean>({
  get: () => {
    return !!formData.value.tax_included
  },
  set: (value: boolean) => {
    formData.value.tax_included = value
  },
})
</script>
