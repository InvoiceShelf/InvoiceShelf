<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onClickOutside, onKeyStroke, useDebounceFn } from '@vueuse/core'
import { FocusScope } from 'reka-ui'
import { useRoute } from 'vue-router'
import { useUserStore } from '@/scripts/stores/user.store'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useModalStore } from '@/scripts/stores/modal.store'
import { ABILITIES } from '@/scripts/config/abilities'
import { useCustomerStore } from '@/scripts/features/company/customers/store'
import { useInvoiceStore } from '@/scripts/features/company/invoices/store'
import { useEstimateStore } from '@/scripts/features/company/estimates/store'
import { useRecurringInvoiceStore } from '@/scripts/features/company/recurring-invoices/store'
import CustomerModal from '@/scripts/features/company/customers/components/CustomerModal.vue'

type DocumentType = 'estimate' | 'invoice' | 'recurring-invoice'

interface ValidationError {
  $message: string
}

interface Validation {
  $error: boolean
  $errors: ValidationError[]
}

interface Props {
  valid?: Validation
  customerId?: number | null
  type?: DocumentType | null
  contentLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  valid: () => ({ $error: false, $errors: [] }),
  customerId: null,
  type: null,
  contentLoading: false,
})

const userStore = useUserStore()
const modalStore = useModalStore()
const { t } = useI18n()
const route = useRoute()

const customerStore = useCustomerStore()
const invoiceStore = useInvoiceStore()
const estimateStore = useEstimateStore()
const recurringInvoiceStore = useRecurringInvoiceStore()

const search = ref<string | null>(null)
const isSearchingCustomer = ref<boolean>(false)
// Until the first page arrives, so "no customers" does not flash on open
const isLoadingCustomers = ref<boolean>(true)

const { isPhone } = useBreakpoints()

// The picker: a panel under the field on wider screens, a full-screen sheet
// on phones
const isOpen = ref<boolean>(false)
const trigger = ref<HTMLElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const searchField = ref<HTMLElement | null>(null)
const card = ref<HTMLElement | null>(null)

/*
 * Picking, clearing and closing all replace the element that had focus, so
 * focus is put back on what took its place: the customer card after a pick,
 * the picker button otherwise.
 */
async function restoreFocus(): Promise<void> {
  await nextTick()
  ;(card.value ?? trigger.value)?.focus()
}

// A pick or a clear swaps the card and the button once the customer has
// loaded; focus follows then, and only after the user did it
let focusAfterChange = false

watch(
  () => selectedCustomer.value?.id,
  (id, previous) => {
    if (focusAfterChange && id !== previous) {
      focusAfterChange = false
      void restoreFocus()
    }
  },
)

function leaveFocus(event: Event): void {
  event.preventDefault()
}

function openPicker(): void {
  isOpen.value = true
}

function closePicker(): void {
  const wasOpen = isOpen.value
  isOpen.value = false

  if (wasOpen) {
    void restoreFocus()
  }
}

watch(isOpen, async (open) => {
  if (open) {
    await nextTick()
    searchField.value?.querySelector('input')?.focus()
  }
})

onClickOutside(panel, () => {
  if (!isPhone.value) {
    closePicker()
  }
}, { ignore: [trigger] })

onKeyStroke('Escape', () => {
  if (isOpen.value) {
    closePicker()
  }
})

const selectedCustomer = computed(() => {
  switch (props.type) {
    case 'invoice':
      return invoiceStore.newInvoice.customer
    case 'estimate':
      return estimateStore.newEstimate.customer
    case 'recurring-invoice':
      return recurringInvoiceStore.newRecurringInvoice.customer
    default:
      return null
  }
})

// Fetch initial customers on setup
async function fetchInitialCustomers(): Promise<void> {
  try {
    await customerStore.fetchCustomers({
      orderByField: '',
      orderBy: '',
    })
  } finally {
    isLoadingCustomers.value = false
  }
}

// Select customer on setup if customerId is provided
if (props.customerId) {
  if (props.type === 'invoice') {
    invoiceStore.selectCustomer(props.customerId)
  } else if (props.type === 'estimate') {
    estimateStore.selectCustomer(props.customerId)
  } else if (props.type === 'recurring-invoice') {
    recurringInvoiceStore.selectCustomer(props.customerId)
  }
}

fetchInitialCustomers()

const debounceSearchCustomer = useDebounceFn(() => {
  isSearchingCustomer.value = true
  searchCustomer()
}, 500)

async function searchCustomer(): Promise<void> {
  try {
    await customerStore.fetchCustomers({
      display_name: search.value ?? '',
      page: 1,
    })
  } finally {
    isSearchingCustomer.value = false
  }
}

function selectNewCustomer(id: number): void {
  const params: Record<string, unknown> = { userId: id }
  if (route.params.id) params.model_id = route.params.id

  if (props.type === 'invoice') {
    invoiceStore.getNextNumber(params, true)
    invoiceStore.selectCustomer(id)
  } else if (props.type === 'estimate') {
    estimateStore.getNextNumber(params, true)
    estimateStore.selectCustomer(id)
  } else if (props.type === 'recurring-invoice') {
    recurringInvoiceStore.selectCustomer(id)
  }

  focusAfterChange = true
  closePicker()
  search.value = null
}

function resetSelectedCustomer(): void {
  focusAfterChange = true

  if (props.type === 'invoice') {
    invoiceStore.resetSelectedCustomer()
  } else if (props.type === 'estimate') {
    estimateStore.resetSelectedCustomer()
  } else if (props.type === 'recurring-invoice') {
    recurringInvoiceStore.resetSelectedCustomer()
  }
}

async function editCustomer(): Promise<void> {
  if (!selectedCustomer.value) return
  await customerStore.fetchCustomer(selectedCustomer.value.id)
  modalStore.openModal({
    title: t('customers.edit_customer'),
    componentName: 'CustomerModal',
  })
}

function openCustomerModal(): void {
  closePicker()
  customerStore.resetCurrentCustomer()
  modalStore.openModal({
    title: t('customers.add_customer'),
    componentName: 'CustomerModal',
    variant: 'md',
  })
}

function initials(name: string | null | undefined): string {
  const words = (name ?? '').trim().split(/\s+/)
  return words.slice(0, 2).map((word) => word.charAt(0).toUpperCase()).join('')
}

interface AddressLike {
  name?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
}

// Name, then "City, State", then the postcode; empty parts drop out
function addressLines(address: AddressLike | null | undefined): string[] {
  if (!address) {
    return []
  }

  const place = [address.city, address.state].filter(Boolean).join(', ')

  return [address.name, place, address.zip].filter((line): line is string => !!line)
}

const addressBlocks = computed(() => {
  const customer = selectedCustomer.value as { billing?: AddressLike; shipping?: AddressLike } | null

  return [
    { key: 'billing', label: t('general.bill_to'), lines: addressLines(customer?.billing) },
    { key: 'shipping', label: t('general.ship_to'), lines: addressLines(customer?.shipping) },
  ].filter((block) => block.lines.length > 0)
})
</script>

<template>
  <div>
    <CustomerModal />

    <BaseContentPlaceholders v-if="contentLoading">
      <BaseContentPlaceholdersBox :rounded="true" class="w-full h-24 md:h-32" />
    </BaseContentPlaceholders>

    <!-- The chosen customer -->
    <div
      v-else-if="selectedCustomer"
      ref="card"
      tabindex="-1"
      :aria-label="`${$t('invoices.customer')}: ${selectedCustomer.name}`"
      class="flex flex-col gap-4 p-4 border md:p-5 glass rounded-xl focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
    >
      <div class="flex items-start gap-3">
        <span
          class="flex items-center justify-center w-11 h-11 text-sm font-semibold rounded-xl shrink-0 bg-btn-primary text-on-primary"
          aria-hidden="true"
        >
          {{ initials(selectedCustomer.name) }}
        </span>

        <div class="flex-1 min-w-0">
          <p class="text-xs font-medium text-muted">{{ $t('invoices.customer') }}</p>
          <p class="text-base font-semibold truncate text-heading">{{ selectedCustomer.name }}</p>
        </div>

        <div class="flex items-center gap-1 -me-1 shrink-0">
          <button
            type="button"
            class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg md:w-9 md:h-9 text-muted hover:bg-hover-strong hover:text-heading"
            :aria-label="$t('customers.edit_customer')"
            @click.stop="editCustomer"
          >
            <BaseIcon name="PencilSquareIcon" class="w-5 h-5" />
          </button>
          <button
            type="button"
            class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg md:w-9 md:h-9 text-muted hover:bg-hover-strong hover:text-heading"
            :aria-label="$t('general.deselect')"
            @click="resetSelectedCustomer"
          >
            <BaseIcon name="XMarkIcon" class="w-5 h-5" />
          </button>
        </div>
      </div>

      <dl v-if="addressBlocks.length" class="grid grid-cols-2 gap-4 pt-4 border-t border-line-light">
        <div v-for="block in addressBlocks" :key="block.key" class="min-w-0">
          <dt class="mb-1 text-xs font-medium text-muted">{{ block.label }}</dt>
          <dd v-for="line in block.lines" :key="line" class="text-sm truncate text-body">{{ line }}</dd>
        </div>
      </dl>
    </div>

    <!-- No customer yet: the field that opens the picker -->
    <div v-else class="relative">
      <button
        ref="trigger"
        type="button"
        :aria-expanded="isOpen"
        aria-haspopup="dialog"
        :data-invalid="valid.$error ? 'true' : undefined"
        :class="valid.$error ? 'border-danger' : 'border-line-strong hover:border-primary-400'"
        class="
          flex items-center w-full gap-4 p-4 text-start transition-colors border-2 border-dashed md:p-5
          rounded-xl bg-surface/50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus
        "
        @click="isOpen ? closePicker() : openPicker()"
      >
        <span
          class="flex items-center justify-center w-11 h-11 rounded-xl shrink-0 bg-primary-50 text-primary-600"
          aria-hidden="true"
        >
          <BaseIcon name="UserPlusIcon" class="w-5 h-5" />
        </span>

        <span class="flex flex-col flex-1 min-w-0">
          <span class="text-base font-semibold text-heading">
            {{ $t('invoices.customer') }}
            <span class="text-danger" aria-hidden="true">*</span>
          </span>
          <span v-if="valid.$error" class="text-sm text-danger">
            {{ $t('estimates.errors.required') }}
          </span>
          <span v-else class="text-sm text-muted">{{ $t('customers.select_a_customer') }}</span>
        </span>

        <BaseIcon name="ChevronDownIcon" class="w-5 h-5 text-subtle shrink-0" />
      </button>

      <Teleport to="body" :disabled="!isPhone">
        <transition
          :enter-active-class="isPhone ? 'transition duration-200 ease-out' : 'transition duration-150 ease-out'"
          :enter-from-class="isPhone ? 'translate-y-full' : 'translate-y-1 opacity-0'"
          :leave-active-class="isPhone ? 'transition duration-150 ease-in' : 'transition duration-100 ease-in'"
          :leave-to-class="isPhone ? 'translate-y-full' : 'translate-y-1 opacity-0'"
        >
          <!--
            On phones the sheet keeps focus inside it until it closes. Where
            focus starts and ends is left to openPicker and closePicker.
          -->
          <component
            :is="isPhone ? FocusScope : 'div'"
            v-if="isOpen"
            ref="panel"
            v-bind="isPhone ? { trapped: true, loop: true } : {}"
            role="dialog"
            :aria-modal="isPhone ? 'true' : undefined"
            :aria-label="$t('customers.select_a_customer')"
            :class="
              isPhone
                ? 'fixed inset-0 z-50 flex flex-col bg-surface'
                : 'absolute inset-x-0 z-30 mt-2 overflow-hidden border glass-strong rounded-xl'
            "
            @mount-auto-focus="leaveFocus"
            @unmount-auto-focus="leaveFocus"
          >
            <div v-if="isPhone" class="flex items-center gap-2 px-2 border-b safe-header border-line-light">
              <button
                type="button"
                class="flex items-center justify-center w-11 h-11 rounded-lg text-muted hover:bg-hover-strong"
                :aria-label="$t('general.close')"
                @click="closePicker"
              >
                <BaseIcon name="XMarkIcon" class="w-6 h-6" />
              </button>
              <h2 class="flex-1 text-base font-semibold text-heading">
                {{ $t('customers.select_a_customer') }}
              </h2>
            </div>

            <div ref="searchField" class="p-3">
              <BaseInput
                v-model="search"
                :placeholder="$t('general.search')"
                type="search"
                icon="search"
                @update:model-value="() => debounceSearchCustomer()"
              />
            </div>

            <ul
              class="flex flex-col overflow-y-auto border-t border-line-light overscroll-contain"
              :class="isPhone ? 'flex-1' : 'max-h-80'"
            >
              <li v-for="customer in customerStore.customers" :key="customer.id">
                <button
                  type="button"
                  class="
                    flex items-center w-full gap-3 px-4 py-3 text-start transition-colors
                    hover:bg-hover-strong focus:outline-hidden focus-visible:bg-hover-strong
                  "
                  @click="selectNewCustomer(customer.id)"
                >
                  <span
                    class="flex items-center justify-center w-10 h-10 text-sm font-semibold rounded-xl shrink-0 bg-primary-50 text-primary-700"
                    aria-hidden="true"
                  >
                    {{ initials(customer.name) }}
                  </span>
                  <span class="flex flex-col min-w-0">
                    <span class="text-sm font-medium truncate text-heading">{{ customer.name }}</span>
                    <span v-if="customer.contact_name" class="text-sm truncate text-muted">
                      {{ customer.contact_name }}
                    </span>
                  </span>
                </button>
              </li>

              <li
                v-if="isLoadingCustomers || isSearchingCustomer"
                class="px-4 py-8 text-sm text-center text-muted"
                role="status"
              >
                {{ $t('general.loading') }}
              </li>

              <!-- A search that found nothing, or a company with no customers yet -->
              <li
                v-else-if="customerStore.customers.length === 0"
                class="flex flex-col gap-1 px-4 py-8 text-sm text-center"
                role="status"
              >
                <template v-if="search">
                  <span class="text-muted">{{ $t('customers.no_customers_found') }}</span>
                </template>
                <template v-else>
                  <span class="font-medium text-heading">{{ $t('customers.no_customers') }}</span>
                  <span class="text-muted">
                    {{
                      userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)
                        ? $t('customers.add_first_customer')
                        : $t('customers.ask_to_add_customers')
                    }}
                  </span>
                </template>
              </li>
            </ul>

            <button
              v-if="userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)"
              type="button"
              class="
                flex items-center justify-center w-full gap-2 text-sm font-medium transition-colors border-t
                h-12 border-line-light text-primary-600 hover:bg-primary-50/60
              "
              :class="isPhone ? 'safe-drawer h-auto pt-3.5' : ''"
              @click="openCustomerModal"
            >
              <BaseIcon name="UserPlusIcon" class="w-5 h-5" />
              {{ $t('customers.add_new_customer') }}
            </button>
          </component>
        </transition>
      </Teleport>
    </div>
  </div>
</template>
