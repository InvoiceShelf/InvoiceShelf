<template>
  <!-- Single shared item-create modal for the whole editor (one instance, not one
       per row — stacked HeadlessUI dialogs would otherwise close each other). -->
  <ItemModal />
  <TaxTypeModal />

  <!-- Phones: each line is a card; reordering lives in the card's menu -->
  <section v-if="isPhone" class="flex flex-col gap-3" :aria-label="$t('items.item', 2)">
    <div class="flex items-center justify-between gap-3">
      <h2 class="font-semibold text-section text-heading">
        {{ $t('items.item', 2) }}
      </h2>

      <BaseSwitch
        v-if="taxIncludedSetting === 'YES'"
        v-model="taxIncludedField"
        :label-left="$t('settings.tax_types.tax_included')"
        class="text-sm text-body"
      />
    </div>

    <DocumentItemRow
      v-for="(element, index) in formData.items"
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
      layout="card"
      @move="moveItem"
      @tax-type-created="upsertAvailableTaxType"
    />

    <button
      type="button"
      class="
        flex items-center justify-center w-full h-12 gap-2 text-sm font-medium transition-colors
        border-2 border-dashed rounded-xl border-line-default text-primary-600
        hover:border-primary-300 hover:bg-primary-50/60
      "
      @click="store.addItem()"
    >
      <BaseIcon name="PlusIcon" class="w-5 h-5" />
      {{ $t('general.add_new_item') }}
    </button>
  </section>

  <!-- Tablet and desktop: the items table -->
  <div v-else class="border glass rounded-xl">
    <!-- Tax Included Toggle -->
    <div
      v-if="taxIncludedSetting === 'YES'"
      class="flex items-center justify-end w-full px-6 text-base border-b border-line-light cursor-pointer text-primary-400"
    >
      <BaseSwitchSection
        v-model="taxIncludedField"
        :title="$t('settings.tax_types.tax_included')"
        :store="store"
        :store-prop="storeProp"
      />
    </div>

    <table class="min-w-full text-center item-table">
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

      <thead class="border-b bg-surface-secondary/70 border-line-light">
        <tr>
          <th
            class="px-5 py-3 text-sm font-medium leading-5 text-left text-muted"
            :class="taxIncludedSetting === 'YES' ? '' : 'rounded-tl-xl'"
          >
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else class="pl-7">
              {{ $t('items.item', 2) }}
            </span>
          </th>
          <th class="px-5 py-3 text-sm font-medium leading-5 text-right text-muted">
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else>
              {{ $t('invoices.item.quantity') }}
            </span>
          </th>
          <th class="px-5 py-3 text-sm font-medium leading-5 text-left text-muted">
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else>
              {{ $t('invoices.item.price') }}
            </span>
          </th>
          <th
            v-if="formData.discount_per_item === 'YES'"
            class="px-5 py-3 text-sm font-medium leading-5 text-left text-muted"
          >
            <BaseContentPlaceholders v-if="isLoading">
              <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
            </BaseContentPlaceholders>
            <span v-else>
              {{ $t('invoices.item.discount') }}
            </span>
          </th>
          <th
            class="px-5 py-3 text-sm font-medium leading-5 text-right text-muted"
            :class="taxIncludedSetting === 'YES' ? '' : 'rounded-tr-xl'"
          >
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
            @move="moveItem"
            @tax-type-created="upsertAvailableTaxType"
          />
        </template>
      </draggable>
    </table>

    <button
      type="button"
      class="
        flex items-center justify-center w-full h-12 gap-2 px-6 text-sm font-medium transition-colors
        rounded-b-xl text-primary-600 hover:bg-primary-50/60
      "
      @click="store.addItem()"
    >
      <BaseIcon name="PlusCircleIcon" class="w-5 h-5" />
      {{ $t('general.add_new_item') }}
    </button>
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
import { useBreakpoints } from '../../../composables/use-breakpoints'
import { taxTypeService } from '../../../api/services/tax-type.service'
import { ABILITIES } from '../../../config/abilities'
import type { Currency } from '../../../types/domain/currency'
import type { TaxType } from '../../../types/domain/tax'
import type { DocumentFormData } from './use-document-calculations'

interface Props {
  store: Record<string, unknown> & {
    addItem: () => void
    removeItem: (index: number) => void
    updateItem: (data: Record<string, unknown>) => void
    $patch: (fn: (state: Record<string, unknown>) => void) => void
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
const { isPhone } = useBreakpoints()

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

// The rows fall back to the company currency when this is empty
const defaultCurrency = computed<Currency | Record<string, unknown>>(() => {
  return (props.currency || null) as Currency | Record<string, unknown>
})

// Phones reorder from each card's menu, keyboards from a row's drag handle
function moveItem(from: number, to: number): void {
  const items = formData.value.items

  if (to < 0 || to >= items.length) {
    return
  }

  const [moved] = items.splice(from, 1)
  items.splice(to, 0, moved)
}

const taxIncludedField = computed<boolean>({
  get: () => {
    return !!formData.value.tax_included
  },
  set: (value: boolean) => {
    formData.value.tax_included = value
  },
})
</script>
