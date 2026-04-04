<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useRoute } from 'vue-router'
import { usePermissions } from '../../composables/use-permissions'
import { useModal } from '../../composables/use-modal'
import { ABILITIES } from '../../config/abilities'
import type { Customer, Address } from '../../types/domain'

type DocumentType = 'estimate' | 'invoice' | 'recurring-invoice'

interface ValidationError {
  $message: string
}

interface Validation {
  $error: boolean
  $errors: ValidationError[]
}

interface SelectedCustomerData {
  id: number
  name: string
  billing?: Pick<Address, 'name' | 'city' | 'state' | 'zip'> | null
  shipping?: Pick<Address, 'name' | 'city' | 'state' | 'zip'> | null
}

interface Props {
  valid?: Validation
  customerId?: number | null
  type?: DocumentType | null
  contentLoading?: boolean
  selectedCustomer?: SelectedCustomerData | null
  customers?: Customer[]
}

interface Emits {
  (e: 'select', customerId: number): void
  (e: 'deselect'): void
  (e: 'edit', customerId: number): void
  (e: 'search', query: string): void
  (e: 'create'): void
}

const props = withDefaults(defineProps<Props>(), {
  valid: () => ({ $error: false, $errors: [] }),
  customerId: null,
  type: null,
  contentLoading: false,
  selectedCustomer: null,
  customers: () => [],
})

const emit = defineEmits<Emits>()

const { hasAbility } = usePermissions()
const { openModal } = useModal()
const { t } = useI18n()
const route = useRoute()

const search = ref<string | null>(null)
const isSearchingCustomer = ref<boolean>(false)

const debounceSearchCustomer = useDebounceFn(() => {
  isSearchingCustomer.value = true
  searchCustomer()
}, 500)

function searchCustomer(): void {
  if (search.value !== null) {
    emit('search', search.value)
  }
  isSearchingCustomer.value = false
}

function editCustomer(): void {
  if (props.selectedCustomer) {
    emit('edit', props.selectedCustomer.id)
  }
}

function resetSelectedCustomer(): void {
  emit('deselect')
}

function openCustomerModal(): void {
  emit('create')
}

function initGenerator(name: string): string {
  if (name) {
    const nameSplit = name.split(' ')
    return nameSplit[0].charAt(0).toUpperCase()
  }
  return ''
}

function selectNewCustomer(id: number, close: () => void): void {
  emit('select', id)
  close()
  search.value = null
}
</script>

<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      class="w-full"
      style="min-height: 170px"
    />
  </BaseContentPlaceholders>

  <div v-else class="max-h-[173px]">
    <div
      v-if="selectedCustomer"
      class="
        flex flex-col
        p-4
        bg-surface
        border border-line-light border-solid
        min-h-[170px]
        rounded-xl
        shadow
      "
      @click.stop
    >
      <div class="flex relative justify-between mb-2">
        <BaseText
          :text="selectedCustomer.name"
          class="flex-1 text-base font-medium text-left text-heading"
        />
        <div class="flex">
          <a
            class="
              relative
              my-0
              ml-6
              text-sm
              font-medium
              cursor-pointer
              text-primary-500
              items-center
              flex
            "
            @click.stop="editCustomer"
          >
            <BaseIcon name="PencilIcon" class="text-muted h-4 w-4 mr-1" />

            {{ $t('general.edit') }}
          </a>
          <a
            class="
              relative
              my-0
              ml-6
              text-sm
              flex
              items-center
              font-medium
              cursor-pointer
              text-primary-500
            "
            @click="resetSelectedCustomer"
          >
            <BaseIcon name="XCircleIcon" class="text-muted h-4 w-4 mr-1" />
            {{ $t('general.deselect') }}
          </a>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-8 mt-2">
        <div v-if="selectedCustomer.billing" class="flex flex-col">
          <label
            class="
              mb-1
              text-sm
              font-medium
              text-left text-subtle
              uppercase
              whitespace-nowrap
            "
          >
            {{ $t('general.bill_to') }}
          </label>

          <div
            v-if="selectedCustomer.billing"
            class="flex flex-col flex-1 p-0 text-left"
          >
            <label
              v-if="selectedCustomer.billing.name"
              class="relative w-11/12 text-sm truncate"
            >
              {{ selectedCustomer.billing.name }}
            </label>

            <label class="relative w-11/12 text-sm truncate">
              <span v-if="selectedCustomer.billing.city">
                {{ selectedCustomer.billing.city }}
              </span>
              <span
                v-if="
                  selectedCustomer.billing.city &&
                  selectedCustomer.billing.state
                "
              >
                ,
              </span>
              <span v-if="selectedCustomer.billing.state">
                {{ selectedCustomer.billing.state }}
              </span>
            </label>
            <label
              v-if="selectedCustomer.billing.zip"
              class="relative w-11/12 text-sm truncate"
            >
              {{ selectedCustomer.billing.zip }}
            </label>
          </div>
        </div>

        <div v-if="selectedCustomer.shipping" class="flex flex-col">
          <label
            class="
              mb-1
              text-sm
              font-medium
              text-left text-subtle
              uppercase
              whitespace-nowrap
            "
          >
            {{ $t('general.ship_to') }}
          </label>

          <div
            v-if="selectedCustomer.shipping"
            class="flex flex-col flex-1 p-0 text-left"
          >
            <label
              v-if="selectedCustomer.shipping.name"
              class="relative w-11/12 text-sm truncate"
            >
              {{ selectedCustomer.shipping.name }}
            </label>

            <label class="relative w-11/12 text-sm truncate">
              <span v-if="selectedCustomer.shipping.city">
                {{ selectedCustomer.shipping.city }}
              </span>
              <span
                v-if="
                  selectedCustomer.shipping.city &&
                  selectedCustomer.shipping.state
                "
              >
                ,
              </span>
              <span v-if="selectedCustomer.shipping.state">
                {{ selectedCustomer.shipping.state }}
              </span>
            </label>
            <label
              v-if="selectedCustomer.shipping.zip"
              class="relative w-11/12 text-sm truncate"
            >
              {{ selectedCustomer.shipping.zip }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <Popover v-else v-slot="{ open }" class="relative flex flex-col rounded-xl">
      <PopoverButton
        :class="{
          '': open,
          'border border-solid border-red-500 focus:ring-red-500 rounded':
            valid.$error,
          'focus:ring-2 focus:ring-primary-400': !valid.$error,
        }"
        class="w-full outline-hidden rounded-xl"
      >
        <div
          class="
            relative
            flex
            justify-center
            px-0
            p-0
            py-16
            bg-surface
            border border-line-light border-solid
            rounded-xl
            shadow
            min-h-[170px]
          "
        >
          <BaseIcon
            name="UserIcon"
            class="
              flex
              justify-center
              !w-10
              !h-10
              p-2
              mr-5
              text-sm text-white
              bg-surface-muted
              rounded-full
              font-base
            "
          />

          <div class="mt-1">
            <label class="text-lg font-medium text-heading">
              {{ $t('customers.new_customer') }}
              <span class="text-red-500"> * </span>
            </label>

            <p
              v-if="valid.$error && valid.$errors[0]?.$message"
              class="text-red-500 text-sm absolute right-3 bottom-3"
            >
              {{ $t('estimates.errors.required') }}
            </p>
          </div>
        </div>
      </PopoverButton>

      <!-- Customer Select Popup -->
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-1 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-1 opacity-0"
      >
        <div v-if="open" class="absolute min-w-full z-10">
          <PopoverPanel
            v-slot="{ close }"
            focus
            static
            class="
              overflow-hidden
              rounded-xl
              shadow
              ring-1 ring-black/5
              bg-surface
            "
          >
            <div class="relative">
              <BaseInput
                v-model="search"
                container-class="m-4"
                :placeholder="$t('general.search')"
                type="text"
                icon="search"
                @update:modelValue="(val: string | null) => debounceSearchCustomer()"
              />

              <ul
                class="
                  max-h-80
                  flex flex-col
                  overflow-auto
                  list
                  border-t border-line-light
                "
              >
                <li
                  v-for="(customer, index) in customers"
                  :key="index"
                  href="#"
                  class="
                    flex
                    px-6
                    py-2
                    border-b border-line-light border-solid
                    cursor-pointer
                    hover:cursor-pointer hover:bg-hover-strong
                    focus:outline-hidden focus:bg-surface-tertiary
                    last:border-b-0
                  "
                  @click="selectNewCustomer(customer.id, close)"
                >
                  <span
                    class="
                      flex
                      items-center
                      content-center
                      justify-center
                      w-10
                      h-10
                      mr-4
                      text-xl
                      font-semibold
                      leading-9
                      text-white
                      bg-surface-muted
                      rounded-full
                      avatar
                    "
                  >
                    {{ initGenerator(customer.name) }}
                  </span>

                  <div class="flex flex-col justify-center text-left">
                    <BaseText
                      v-if="customer.name"
                      :text="customer.name"
                      class="
                        m-0
                        text-base
                        font-normal
                        leading-tight
                        cursor-pointer
                      "
                    />
                    <BaseText
                      v-if="customer.contact_name"
                      :text="customer.contact_name"
                      class="
                        m-0
                        text-sm
                        font-medium
                        text-subtle
                        cursor-pointer
                      "
                    />
                  </div>
                </li>
                <div
                  v-if="customers.length === 0"
                  class="flex justify-center p-5 text-subtle"
                >
                  <label class="text-base text-muted cursor-pointer">
                    {{ $t('customers.no_customers_found') }}
                  </label>
                </div>
              </ul>
            </div>

            <button
              v-if="hasAbility(ABILITIES.CREATE_CUSTOMER)"
              type="button"
              class="
                h-10
                flex
                items-center
                justify-center
                w-full
                px-2
                py-3
                bg-surface-muted
                border-none
                outline-hidden
                focus:bg-surface-muted
              "
              @click="openCustomerModal"
            >
              <BaseIcon name="UserPlusIcon" class="text-primary-400" />

              <label
                class="
                  m-0
                  ml-3
                  text-sm
                  leading-none
                  cursor-pointer
                  font-base
                  text-primary-400
                "
              >
                {{ $t('customers.add_new_customer') }}
              </label>
            </button>
          </PopoverPanel>
        </div>
      </transition>
    </Popover>
  </div>
</template>
