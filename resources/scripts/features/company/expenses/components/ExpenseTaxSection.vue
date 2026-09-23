<template>
  <section class="mt-6">
    <div class="rounded-xl border border-line-light bg-surface shadow-sm">
      <div
        class="flex items-start justify-between gap-4 border-b border-line-light p-4 sm:p-5"
      >
        <div class="min-w-0">
          <h2 class="text-base font-semibold text-heading">
            {{ $t('expenses.taxes') }}
          </h2>
          <p class="mt-1 text-sm leading-5 text-muted">
            {{ $t('expenses.taxes_description') }}
          </p>
        </div>

        <Popover class="relative shrink-0">
          <PopoverButton
            type="button"
            :disabled="isLoading"
            class="inline-flex h-9 items-center justify-center rounded-lg border border-line-default bg-surface px-3 text-sm font-medium text-primary-600 transition hover:bg-hover focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
          >
            <BaseIcon name="PlusIcon" class="me-1.5 h-4 w-4" />
            {{ $t('expenses.add_tax') }}
          </PopoverButton>

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
              class="absolute end-0 z-30 mt-2 w-[min(20rem,calc(100vw-3rem))] overflow-hidden rounded-xl border border-line-default bg-surface shadow-lg"
            >
              <div class="p-4">
                <BaseInput
                  v-model="textSearch"
                  :placeholder="$t('general.search')"
                  type="text"
                />
              </div>

              <div
                v-if="filteredTaxTypes.length"
                class="max-h-52 overflow-y-auto border-t border-line-light"
              >
                <button
                  v-for="taxType in filteredTaxTypes"
                  :key="taxType.id"
                  type="button"
                  class="flex w-full items-center justify-between gap-4 border-b border-line-light px-5 py-3 text-start last:border-b-0 hover:bg-hover disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="selectedTaxTypeIds.has(taxType.id)"
                  @click="selectTaxType(taxType, close)"
                >
                  <span class="min-w-0 truncate font-medium text-body">
                    {{ taxType.name }}
                  </span>
                  <span class="shrink-0 text-sm text-muted">
                    <BaseFormatMoney
                      v-if="taxType.calculation_type === 'fixed'"
                      :amount="taxType.fixed_amount"
                      :currency="currency"
                    />
                    <template v-else>{{ taxType.percent }}%</template>
                  </span>
                </button>
              </div>

              <p v-else class="border-t border-line-light p-5 text-center text-sm text-muted">
                {{ $t('general.no_tax_found') }}
              </p>

              <button
                v-if="canCreateTaxType"
                type="button"
                class="flex h-11 w-full items-center justify-center border-t border-line-light bg-surface-muted px-2 text-sm font-medium text-primary-600 hover:bg-hover"
                @click="openTaxTypeModal(close)"
              >
                <BaseIcon name="PlusCircleIcon" class="me-2 h-4 w-4" />
                {{ $t('expenses.add_new_tax') }}
              </button>
            </PopoverPanel>
          </transition>
        </Popover>
      </div>

      <div class="grid lg:grid-cols-[minmax(0,1fr)_19rem]">
        <div class="min-w-0 p-4 sm:p-5 lg:border-e lg:border-line-light">
          <div v-if="isLoading">
            <BaseContentPlaceholders>
              <BaseContentPlaceholdersText :lines="3" />
            </BaseContentPlaceholders>
          </div>

          <div v-else-if="modelValue.length" class="space-y-3">
            <div
              v-for="(tax, index) in modelValue"
              :key="tax.tax_type_id"
              class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 rounded-lg border border-line-light bg-surface-secondary p-4 sm:grid-cols-[minmax(0,1fr)_11rem_auto] sm:items-end"
            >
              <div class="col-span-2 min-w-0 self-center sm:col-span-1">
                <p class="truncate font-medium text-heading">{{ tax.name }}</p>
                <span
                  class="mt-1.5 inline-flex rounded-full bg-surface-muted px-2.5 py-1 text-xs font-medium text-muted"
                >
                  <BaseFormatMoney
                    v-if="tax.calculation_type === 'fixed'"
                    :amount="tax.fixed_amount ?? 0"
                    :currency="currency"
                  />
                  <template v-else>{{ tax.percent }}%</template>
                </span>
              </div>

              <div class="min-w-0">
                <p class="mb-1.5 text-xs font-medium text-muted">
                  {{ $t('expenses.tax_amount') }}
                </p>
                <BaseMoney
                  :key="`${tax.tax_type_id}-${currency?.id ?? 'none'}`"
                  :model-value="tax.amount / 100"
                  :currency="currency"
                  @update:model-value="updateTaxAmount(index, $event)"
                />
              </div>

              <BaseButton
                type="button"
                variant="white"
                size="sm"
                class="!h-10 !w-10 !px-0 justify-center text-alert-error-text"
                :aria-label="$t('general.delete')"
                @click="removeTax(index)"
              >
                <BaseIcon name="TrashIcon" class="h-5 w-5" />
              </BaseButton>
            </div>
          </div>

          <div
            v-else
            class="flex min-h-36 flex-col items-center justify-center rounded-lg border border-dashed border-line-default bg-surface-muted px-5 py-6 text-center"
          >
            <span
              class="flex h-10 w-10 items-center justify-center rounded-full bg-surface text-primary-400 shadow-sm"
            >
              <BaseIcon name="ReceiptPercentIcon" class="h-5 w-5" />
            </span>
            <p class="mt-3 text-sm font-medium text-heading">
              {{ $t('expenses.no_taxes') }}
            </p>
            <p class="mt-1 max-w-sm text-xs leading-5 text-muted">
              {{ $t('expenses.no_taxes_description') }}
            </p>
          </div>
        </div>

        <div
          class="rounded-b-xl bg-surface-muted p-4 sm:p-5 lg:rounded-es-none lg:rounded-e-xl"
        >
          <p class="text-xs font-semibold text-muted">
            {{ $t('expenses.tax_summary') }}
          </p>

          <div class="mt-4 space-y-3">
            <div class="flex items-center justify-between gap-4 text-sm">
              <span class="text-muted">{{ $t('expenses.gross') }}</span>
              <span class="font-medium text-heading">
                <BaseFormatMoney :amount="amount" :currency="currency" />
              </span>
            </div>
            <div class="flex items-center justify-between gap-4 text-sm">
              <span class="text-muted">{{ $t('expenses.input_tax') }}</span>
              <span class="font-medium text-heading">
                <BaseFormatMoney :amount="inputTaxTotal" :currency="currency" />
              </span>
            </div>
            <div
              class="flex items-center justify-between gap-4 border-t border-line-default pt-3 font-semibold text-heading"
            >
              <span>{{ $t('expenses.net') }}</span>
              <BaseFormatMoney :amount="netAmount" :currency="currency" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <TaxTypeModal />
  </section>
</template>

<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { taxTypeService } from '@/scripts/api/services/tax-type.service'
import { ABILITIES } from '@/scripts/config/abilities'
import TaxTypeModal from '@/scripts/features/company/settings/components/TaxTypeModal.vue'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useUserStore } from '@/scripts/stores/user.store'
import type { Currency } from '@/scripts/types/domain/currency'
import type { ExpenseTax, TaxType } from '@/scripts/types/domain/tax'

interface Props {
  modelValue: ExpenseTax[]
  amount: number
  currency: Currency | null
  isLoading?: boolean
}

interface Emits {
  (event: 'update:modelValue', taxes: ExpenseTax[]): void
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
})
const emit = defineEmits<Emits>()

const { t } = useI18n()
const modalStore = useModalStore()
const userStore = useUserStore()
const textSearch = ref<string>('')
const taxTypes = ref<TaxType[]>([])
const automaticTaxTypeIds = ref<Set<number>>(new Set())

const canCreateTaxType = computed<boolean>(() =>
  userStore.hasAbilities(ABILITIES.CREATE_TAX_TYPE),
)

const selectedTaxTypeIds = computed<Set<number>>(
  () => new Set(props.modelValue.map((tax) => tax.tax_type_id)),
)

const filteredTaxTypes = computed<TaxType[]>(() => {
  const search = textSearch.value.trim().toLowerCase()

  if (!search) {
    return taxTypes.value
  }

  return taxTypes.value.filter((taxType) =>
    taxType.name.toLowerCase().includes(search),
  )
})

const inputTaxTotal = computed<number>(() =>
  props.modelValue.reduce((total, tax) => total + tax.amount, 0),
)

const netAmount = computed<number>(() => props.amount - inputTaxTotal.value)

onMounted(async () => {
  await fetchTaxTypes()
})

watch(
  () => props.amount,
  () => {
    const recalculatedTaxes = props.modelValue.map((tax) => {
      if (!automaticTaxTypeIds.value.has(tax.tax_type_id)) {
        return tax
      }

      return {
        ...tax,
        amount: calculateTaxAmount(tax, props.amount),
      }
    })

    if (
      recalculatedTaxes.some(
        (tax, index) => tax.amount !== props.modelValue[index].amount,
      )
    ) {
      emit('update:modelValue', recalculatedTaxes)
    }
  },
)

async function fetchTaxTypes(): Promise<void> {
  try {
    const response = await taxTypeService.list({
      limit: 'all',
      transaction_type: 'purchases',
    })
    taxTypes.value = response.data
  } catch {
    taxTypes.value = []
  }
}

function selectTaxType(taxType: TaxType, close: () => void): void {
  if (selectedTaxTypeIds.value.has(taxType.id)) {
    return
  }

  const tax: ExpenseTax = {
    tax_type_id: taxType.id,
    amount: calculateTaxAmount(taxType, props.amount),
    name: taxType.name,
    percent: taxType.percent,
    calculation_type: taxType.calculation_type,
    fixed_amount: taxType.fixed_amount,
    compound_tax: taxType.compound_tax,
    type: taxType.type,
    tax_type: taxType,
  }

  automaticTaxTypeIds.value.add(tax.tax_type_id)
  emit('update:modelValue', [...props.modelValue, tax])
  close()
}

function updateTaxAmount(index: number, amount: string | number): void {
  const tax = props.modelValue[index]
  const majorAmount = Number(amount)
  const minorAmount = Number.isFinite(majorAmount)
    ? Math.round(majorAmount * 100)
    : 0

  automaticTaxTypeIds.value.delete(tax.tax_type_id)

  emit(
    'update:modelValue',
    props.modelValue.map((currentTax, currentIndex) =>
      currentIndex === index
        ? { ...currentTax, amount: minorAmount }
        : currentTax,
    ),
  )
}

function removeTax(index: number): void {
  const tax = props.modelValue[index]
  automaticTaxTypeIds.value.delete(tax.tax_type_id)
  emit(
    'update:modelValue',
    props.modelValue.filter((_, currentIndex) => currentIndex !== index),
  )
}

function calculateTaxAmount(
  tax: Pick<ExpenseTax, 'calculation_type' | 'fixed_amount' | 'percent'>,
  grossAmount: number,
): number {
  if (tax.calculation_type === 'fixed') {
    return tax.fixed_amount ?? 0
  }

  const divisor = 1 + (tax.percent ?? 0) / 100
  return divisor === 0 ? 0 : Math.round(grossAmount - grossAmount / divisor)
}

function openTaxTypeModal(close: () => void): void {
  close()
  modalStore.openModal({
    title: t('settings.tax_types.add_tax'),
    componentName: 'TaxTypeModal',
    size: 'sm',
    data: { transaction_type: 'purchases' },
    refreshData: fetchTaxTypes,
  })
}
</script>
