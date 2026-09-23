<template>
  <div class="w-full mt-4 tax-select">
    <PopoverRoot v-slot="{ close }">
      <PopoverTrigger
        class="flex items-center gap-1 text-sm font-medium rounded-md text-primary-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
      >
        <BaseIcon name="PlusIcon" class="w-4 h-4" />
        {{ $t('settings.tax_types.add_tax') }}
      </PopoverTrigger>

      <PopoverPortal>
        <!-- Over the button, opening towards the start: the totals sit at the end of the form -->
        <PopoverContent
          side="bottom"
          align="end"
          :side-offset="-20"
          :avoid-collisions="false"
          class="
            z-10 min-w-[350px] focus:outline-hidden
            data-[state=open]:animate-rise-in data-[state=closed]:animate-rise-out
          "
        >
          <div class="overflow-hidden rounded-xl shadow ring-1 ring-black/5">
            <!-- Search Input -->
            <div class="relative bg-surface">
              <div class="relative p-4">
                <BaseInput
                  v-model="textSearch"
                  :placeholder="$t('general.search')"
                  :aria-label="$t('general.search')"
                  type="search"
                  class="text-heading"
                />
              </div>

              <!-- List of Taxes -->
              <div
                v-if="filteredTaxType.length > 0"
                class="relative flex flex-col overflow-auto list max-h-36 border-t border-line-light"
              >
                <button
                  v-for="(taxType, idx) in filteredTaxType"
                  :key="idx"
                  type="button"
                  :disabled="existingTaxIds.has(taxType.id)"
                  class="
                    w-full px-6 py-4 text-start border-b border-line-light border-solid last:border-b-0
                    hover:bg-surface-tertiary focus:outline-hidden focus-visible:bg-surface-tertiary
                    focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus
                    disabled:bg-surface-tertiary disabled:opacity-50 disabled:cursor-not-allowed
                  "
                  @click="selectTaxType(taxType, close)"
                >
                  <span class="flex justify-between px-2">
                    <span class="m-0 text-base font-semibold leading-tight text-body">
                      {{ taxType.name }}
                    </span>
                    <span class="m-0 text-base font-semibold text-body">
                      <template v-if="taxType.calculation_type === 'fixed'">
                        <BaseFormatMoney :amount="taxType.fixed_amount" :currency="companyCurrency" />
                      </template>
                      <template v-else>
                        {{ taxType.percent }} %
                        <BaseBadge v-if="taxType.compound_tax" class="text-xs">
                          {{ $t('tax_types.compound_tax') }}
                        </BaseBadge>
                      </template>
                    </span>
                  </span>
                </button>
              </div>

              <div v-else class="flex justify-center p-5" role="status">
                <span class="text-base text-muted">
                  {{ $t('general.no_tax_found') }}
                </span>
              </div>
            </div>

            <!-- Add new Tax action -->
            <button
              v-if="canCreateTaxType"
              type="button"
              class="flex items-center justify-center w-full h-10 px-2 py-3 border-none bg-surface-muted text-primary-600 outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus"
              @click="openTaxTypeModal"
            >
              <BaseIcon name="CheckCircleIcon" />
              <span class="m-0 ms-3 text-sm leading-none font-base">
                {{ $t('estimates.add_new_tax') }}
              </span>
            </button>
          </div>
        </PopoverContent>
      </PopoverPortal>
    </PopoverRoot>
  </div>
</template>

<script setup lang="ts">
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../stores/modal.store'
import type { TaxType } from '../../../types/domain/tax'
import type { Currency } from '../../../types/domain/currency'
import type { DocumentFormData, DocumentTax } from './use-document-calculations'

interface Props {
  type?: string | null
  store: Record<string, unknown>
  storeProp: string
  taxTypes?: TaxType[]
  companyCurrency?: Currency | Record<string, unknown> | null
  canCreateTaxType?: boolean
}

interface Emits {
  (e: 'select:taxType', taxType: TaxType): void
}

const props = withDefaults(defineProps<Props>(), {
  type: null,
  taxTypes: () => [],
  companyCurrency: null,
  canCreateTaxType: false,
})

const emit = defineEmits<Emits>()

const { t } = useI18n()
const modalStore = useModalStore()
const textSearch = ref<string | null>(null)

const formData = computed<DocumentFormData>(() => {
  return props.store[props.storeProp] as DocumentFormData
})

const filteredTaxType = computed<TaxType[]>(() => {
  if (textSearch.value) {
    return props.taxTypes.filter((el) =>
      el.name.toLowerCase().includes(textSearch.value!.toLowerCase()),
    )
  }
  return props.taxTypes
})

const taxes = computed<DocumentTax[]>(() => {
  return formData.value.taxes
})

const existingTaxIds = computed<Set<number>>(() => {
  return new Set(taxes.value.map((t) => t.tax_type_id))
})

function selectTaxType(data: TaxType, close: () => void): void {
  emit('select:taxType', { ...data })
  close()
}

function openTaxTypeModal(): void {
  modalStore.openModal({
    title: t('settings.tax_types.add_tax'),
    componentName: 'TaxTypeModal',
    size: 'sm',
    data: { transaction_type: 'sales' },
    refreshData: (...args: unknown[]) => {
      const taxType = args[0]
      if (isTaxType(taxType)) {
        emit('select:taxType', taxType)
      }
    },
  })
}

function isTaxType(value: unknown): value is TaxType {
  return (
    typeof value === 'object' &&
    value !== null &&
    'id' in value &&
    typeof value.id === 'number' &&
    'name' in value &&
    typeof value.name === 'string'
  )
}
</script>
