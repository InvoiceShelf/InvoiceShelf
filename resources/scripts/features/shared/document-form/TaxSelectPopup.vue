<template>
  <div class="w-full mt-4 tax-select">
    <Popover v-slot="{ open: isOpen }" class="relative">
      <PopoverButton
        class="flex items-center text-sm font-medium text-primary-400 focus:outline-hidden focus:border-none"
      >
        <BaseIcon name="PlusIcon" class="w-4 h-4 font-medium text-primary-400" />
        {{ $t('settings.tax_types.add_tax') }}
      </PopoverButton>

      <div class="relative w-full max-w-md px-4">
        <transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="translate-y-1 opacity-0"
          enter-to-class="translate-y-0 opacity-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="translate-y-0 opacity-100"
          leave-to-class="translate-y-1 opacity-0"
        >
          <PopoverPanel
            v-slot="{ close }"
            style="min-width: 350px; margin-left: 62px; top: -28px"
            class="absolute z-10 px-4 py-2 -translate-x-full sm:px-0"
          >
            <div class="overflow-hidden rounded-xl shadow ring-1 ring-black/5">
              <!-- Search Input -->
              <div class="relative bg-surface">
                <div class="relative p-4">
                  <BaseInput
                    v-model="textSearch"
                    :placeholder="$t('general.search')"
                    type="text"
                    class="text-heading"
                  />
                </div>

                <!-- List of Taxes -->
                <div
                  v-if="filteredTaxType.length > 0"
                  class="relative flex flex-col overflow-auto list max-h-36 border-t border-line-light"
                >
                  <div
                    v-for="(taxType, idx) in filteredTaxType"
                    :key="idx"
                    :class="{
                      'bg-surface-tertiary cursor-not-allowed opacity-50 pointer-events-none':
                        existingTaxIds.has(taxType.id),
                    }"
                    tabindex="2"
                    class="px-6 py-4 border-b border-line-light border-solid cursor-pointer hover:bg-surface-tertiary hover:cursor-pointer last:border-b-0"
                    @click="selectTaxType(taxType, close)"
                  >
                    <div class="flex justify-between px-2">
                      <label
                        class="m-0 text-base font-semibold leading-tight text-body cursor-pointer"
                      >
                        {{ taxType.name }}
                      </label>
                      <label
                        class="m-0 text-base font-semibold text-body cursor-pointer"
                      >
                        <template v-if="taxType.calculation_type === 'fixed'">
                          <BaseFormatMoney :amount="taxType.fixed_amount" :currency="companyCurrency" />
                        </template>
                        <template v-else>
                          {{ taxType.percent }} %
                        </template>
                      </label>
                    </div>
                  </div>
                </div>

                <div v-else class="flex justify-center p-5 text-subtle">
                  <label class="text-base text-muted cursor-pointer">
                    {{ $t('general.no_tax_found') }}
                  </label>
                </div>
              </div>

              <!-- Add new Tax action -->
              <button
                v-if="canCreateTaxType"
                type="button"
                class="flex items-center justify-center w-full h-10 px-2 py-3 bg-surface-muted border-none outline-hidden"
                @click="openTaxTypeModal"
              >
                <BaseIcon name="CheckCircleIcon" class="text-primary-400" />
                <label
                  class="m-0 ml-3 text-sm leading-none cursor-pointer font-base text-primary-400"
                >
                  {{ $t('estimates.add_new_tax') }}
                </label>
              </button>
            </div>
          </PopoverPanel>
        </transition>
      </div>
    </Popover>
  </div>
</template>

<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
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
    refreshData: (data: TaxType) => emit('select:taxType', data),
  })
}
</script>
