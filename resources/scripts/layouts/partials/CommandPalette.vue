<template>
  <TransitionRoot
    as="template"
    :show="globalStore.isSearchOpen"
    @after-leave="reset"
  >
    <Dialog
      as="div"
      class="relative z-50"
      :initial-focus="input"
      @close="close"
    >
      <TransitionChild
        as="template"
        enter="ease-out duration-150"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-100"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-overlay" aria-hidden="true" />
      </TransitionChild>

      <div class="fixed inset-0 md:px-6 md:pt-[12vh]">
        <TransitionChild
          as="template"
          enter="ease-out duration-150"
          enter-from="opacity-0 md:scale-[0.98]"
          enter-to="opacity-100 md:scale-100"
          leave="ease-in duration-100"
          leave-from="opacity-100 md:scale-100"
          leave-to="opacity-0 md:scale-[0.98]"
        >
          <DialogPanel
            class="
              flex flex-col w-full h-full mx-auto overflow-hidden bg-surface
              md:h-auto md:max-h-[70vh] md:max-w-xl md:rounded-2xl md:border md:border-line-light
              shadow-lg safe-header
            "
          >
            <div class="flex items-center gap-3 px-4 border-b h-14 shrink-0 border-line-light">
              <BaseIcon name="MagnifyingGlassIcon" class="w-5 h-5 shrink-0 text-subtle" />
              <input
                ref="input"
                v-model="query"
                type="text"
                role="combobox"
                aria-controls="command-palette-results"
                :aria-activedescendant="activeId"
                :aria-expanded="true"
                autocomplete="off"
                spellcheck="false"
                :placeholder="$t('global_search.placeholder')"
                class="flex-1 min-w-0 px-0 bg-transparent border-0 rounded-none shadow-none focus:ring-0 focus:border-0"
                @keydown="onKeydown"
              />
              <BaseSpinner v-if="isSearching" class="w-4 h-4 shrink-0 text-subtle" />
              <button
                v-if="isPhone"
                type="button"
                class="text-sm font-medium shrink-0 text-primary-600"
                @click="close"
              >
                {{ $t('general.cancel') }}
              </button>
              <kbd
                v-else
                class="px-1.5 font-sans text-[11px] leading-5 border rounded-md shrink-0 border-line-default text-subtle"
              >
                Esc
              </kbd>
            </div>

            <div
              id="command-palette-results"
              ref="list"
              role="listbox"
              class="flex-1 min-h-0 p-2 overflow-y-auto overscroll-contain"
            >
              <template v-for="section in sections" :key="section.key">
                <p class="px-3 pt-3 pb-1 text-xs font-medium text-muted first:pt-1">
                  {{ section.label }}
                </p>
                <button
                  v-for="item in section.items"
                  :id="`cp-${item.index}`"
                  :key="item.key"
                  type="button"
                  role="option"
                  :aria-selected="item.index === activeIndex"
                  :class="[
                    'flex items-center w-full gap-3 px-3 py-2 text-left rounded-lg',
                    item.index === activeIndex ? 'bg-hover-strong' : '',
                  ]"
                  @mousemove="activeIndex = item.index"
                  @click="run(item)"
                >
                  <span
                    class="flex items-center justify-center w-8 h-8 rounded-lg shrink-0 bg-surface-secondary text-muted"
                  >
                    <BaseIcon :name="item.icon" class="w-4.5 h-4.5" />
                  </span>
                  <span class="flex flex-col flex-1 min-w-0">
                    <span class="text-sm font-medium truncate text-heading">{{ item.title }}</span>
                    <span v-if="item.subtitle" class="text-xs truncate text-muted">
                      {{ item.subtitle }}
                    </span>
                  </span>
                  <BaseFormatMoney
                    v-if="item.amount !== undefined"
                    :amount="item.amount"
                    :currency="item.currency"
                    class="text-sm shrink-0 text-body"
                  />
                </button>
              </template>

              <p
                v-if="query && !isSearching && flatItems.length === 0"
                class="px-3 py-10 text-sm text-center text-muted"
              >
                {{ $t('global_search.no_results_found') }}
              </p>
            </div>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import {
  Dialog,
  DialogPanel,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'
import { useDebounceFn, useEventListener } from '@vueuse/core'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { client } from '@/scripts/api/client'
import { API } from '@/scripts/api/endpoints'
import { ABILITIES } from '@/scripts/config/abilities'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useCreateActions } from '@/scripts/composables/use-create-actions'
import type { CurrencyConfig } from '@/scripts/utils/format-money'

interface PaletteItem {
  key: string
  icon: string
  title: string
  subtitle?: string
  amount?: number
  currency?: CurrencyConfig | null
  to: string
  index: number
}

interface Section {
  key: string
  label: string
  items: PaletteItem[]
}

type RawItem = Omit<PaletteItem, 'index'>

interface DocumentRow {
  id: number
  invoice_number?: string
  estimate_number?: string
  payment_number?: string
  total?: number
  amount?: number
  currency?: CurrencyConfig | null
  customer?: { name?: string } | null
}

interface ContactRow {
  id: number
  name: string
  email?: string
  contact_name?: string
}

const RESULT_LIMIT = 5

const globalStore = useGlobalStore()
const userStore = useUserStore()
const companyStore = useCompanyStore()
const router = useRouter()
const { t } = useI18n()
const { isPhone } = useBreakpoints()
const { createActions } = useCreateActions()

const input = ref<HTMLInputElement | null>(null)
const list = ref<HTMLElement | null>(null)
const query = ref<string>('')
const activeIndex = ref<number>(0)
const isSearching = ref<boolean>(false)
const remote = ref<Record<string, RawItem[]>>({})
let requestId = 0

// ⌘K / Ctrl+K from anywhere in the app
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    globalStore.setSearchOpen(!globalStore.isSearchOpen)
  }
})

const needle = computed<string>(() => query.value.trim().toLowerCase())

const pages = computed<RawItem[]>(() => {
  return globalStore.menuGroups.flat().map((item) => ({
    key: `page-${item.link}`,
    icon: item.icon,
    title: t(item.title),
    to: item.link,
  }))
})

const actions = computed<RawItem[]>(() => {
  return createActions.value.map((action) => ({
    key: `create-${action.to}`,
    icon: action.icon,
    title: t(action.label),
    to: action.to,
  }))
})

function matches(item: RawItem): boolean {
  return !needle.value || item.title.toLowerCase().includes(needle.value)
}

const sections = computed<Section[]>(() => {
  const raw: Array<{ key: string; label: string; items: RawItem[] }> = []

  if (needle.value) {
    raw.push({ key: 'customers', label: t('global_search.customers'), items: remote.value.customers ?? [] })
    raw.push({ key: 'invoices', label: t('navigation.invoices'), items: remote.value.invoices ?? [] })
    raw.push({ key: 'estimates', label: t('navigation.estimates'), items: remote.value.estimates ?? [] })
    raw.push({ key: 'payments', label: t('navigation.payments'), items: remote.value.payments ?? [] })
    raw.push({ key: 'users', label: t('global_search.users'), items: remote.value.users ?? [] })
  }

  raw.push({ key: 'create', label: t('global_search.create'), items: actions.value.filter(matches) })
  raw.push({ key: 'pages', label: t('global_search.go_to'), items: pages.value.filter(matches) })

  let index = 0
  return raw
    .filter((section) => section.items.length > 0)
    .map((section) => ({
      ...section,
      items: section.items.map((item) => ({ ...item, index: index++ })),
    }))
})

const flatItems = computed<PaletteItem[]>(() => sections.value.flatMap((s) => s.items))

const activeId = computed<string | undefined>(() => {
  return flatItems.value.length ? `cp-${activeIndex.value}` : undefined
})

watch(needle, () => {
  activeIndex.value = 0

  if (!needle.value) {
    remote.value = {}
    isSearching.value = false
    return
  }

  isSearching.value = true
  searchRemote()
})

watch(activeIndex, async (index) => {
  await nextTick()
  list.value?.querySelector(`#cp-${index}`)?.scrollIntoView({ block: 'nearest' })
})

async function fetchDocuments(
  url: string,
  ability: string,
  map: (row: DocumentRow) => RawItem,
): Promise<RawItem[]> {
  if (!userStore.hasAbilities(ability)) {
    return []
  }

  try {
    const { data } = await client.get(url, {
      params: { search: needle.value, limit: RESULT_LIMIT, page: 1 },
    })
    return ((data?.data ?? []) as DocumentRow[]).slice(0, RESULT_LIMIT).map(map)
  } catch {
    return []
  }
}

async function fetchContacts(): Promise<Pick<Record<string, RawItem[]>, 'customers' | 'users'>> {
  if (!userStore.currentUser?.is_owner && !userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER)) {
    return { customers: [], users: [] }
  }

  try {
    const { data } = await client.get(API.SEARCH, { params: { search: needle.value } })
    const customers = ((data?.customers?.data ?? []) as ContactRow[]).slice(0, RESULT_LIMIT)
    const users = ((data?.users?.data ?? []) as ContactRow[]).slice(0, RESULT_LIMIT)

    return {
      customers: customers.map((c) => ({
        key: `customer-${c.id}`,
        icon: 'UserIcon',
        title: c.name,
        subtitle: c.contact_name || c.email,
        to: `/admin/customers/${c.id}/view`,
      })),
      users: users.map((u) => ({
        key: `user-${u.id}`,
        icon: 'UsersIcon',
        title: u.name,
        subtitle: u.email,
        to: `/admin/members/${u.id}/edit`,
      })),
    }
  } catch {
    return { customers: [], users: [] }
  }
}

const searchRemote = useDebounceFn(async () => {
  if (!needle.value || companyStore.isAdminMode) {
    isSearching.value = false
    return
  }

  const id = ++requestId

  const [contacts, invoices, estimates, payments] = await Promise.all([
    fetchContacts(),
    fetchDocuments(API.INVOICES, ABILITIES.VIEW_INVOICE, (row) => ({
      key: `invoice-${row.id}`,
      icon: 'DocumentTextIcon',
      title: row.invoice_number ?? '',
      subtitle: row.customer?.name,
      amount: row.total,
      currency: row.currency,
      to: `/admin/invoices/${row.id}/view`,
    })),
    fetchDocuments(API.ESTIMATES, ABILITIES.VIEW_ESTIMATE, (row) => ({
      key: `estimate-${row.id}`,
      icon: 'DocumentIcon',
      title: row.estimate_number ?? '',
      subtitle: row.customer?.name,
      amount: row.total,
      currency: row.currency,
      to: `/admin/estimates/${row.id}/view`,
    })),
    fetchDocuments(API.PAYMENTS, ABILITIES.VIEW_PAYMENT, (row) => ({
      key: `payment-${row.id}`,
      icon: 'CreditCardIcon',
      title: row.payment_number ?? '',
      subtitle: row.customer?.name,
      amount: row.amount,
      currency: row.currency,
      to: `/admin/payments/${row.id}/view`,
    })),
  ])

  // A slower, older request must not overwrite a newer one
  if (id !== requestId) {
    return
  }

  remote.value = { ...contacts, invoices, estimates, payments }
  isSearching.value = false
}, 220)

function onKeydown(event: KeyboardEvent): void {
  const count = flatItems.value.length

  if (event.key === 'ArrowDown' && count) {
    event.preventDefault()
    activeIndex.value = (activeIndex.value + 1) % count
  } else if (event.key === 'ArrowUp' && count) {
    event.preventDefault()
    activeIndex.value = (activeIndex.value - 1 + count) % count
  } else if (event.key === 'Enter') {
    const item = flatItems.value[activeIndex.value]
    if (item) {
      event.preventDefault()
      run(item)
    }
  }
}

function run(item: PaletteItem): void {
  close()
  router.push(item.to)
}

function close(): void {
  globalStore.setSearchOpen(false)
}

function reset(): void {
  query.value = ''
  remote.value = {}
  activeIndex.value = 0
}
</script>
