<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <!-- Table Header with Title and Actions -->
    <div class="p-6 border-b border-gray-200 dark:border-gray-600">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
        <div class="flex items-center space-x-3">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Invoices</h3>
          <span v-if="totalCount > 0" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
            {{ totalCount }} {{ totalCount === 1 ? 'invoice' : 'invoices' }}
          </span>
        </div>
        
        <!-- Actions Row -->
        <div class="flex flex-wrap items-center gap-3">
          <!-- Filter Toggle -->
          <BaseButton
            v-if="totalCount > 0"
            variant="primary-outline"
            size="sm"
            @click="toggleFilters"
          >
            {{ $t('general.filter') }}
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                name="FunnelIcon"
                :class="slotProps.class"
              />
              <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
            </template>
          </BaseButton>

          <!-- Bulk Actions -->
          <BaseDropdown
            v-if="selectedInvoices.length > 0 && userStore.hasAbilities(abilities.DELETE_INVOICE)"
            class="relative"
          >
            <template #activator>
              <BaseButton variant="primary-outline" size="sm">
                {{ $t('general.actions') }} ({{ selectedInvoices.length }})
                <template #right="slotProps">
                  <BaseIcon name="ChevronDownIcon" :class="slotProps.class" />
                </template>
              </BaseButton>
            </template>

            <BaseDropdownItem @click="deleteMultipleInvoices">
              <BaseIcon name="TrashIcon" class="w-4 h-4 mr-2 text-gray-400" />
              {{ $t('general.delete') }}
            </BaseDropdownItem>
          </BaseDropdown>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div v-if="showFilters || hasActiveFilters" class="p-6 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
      <!-- Filter Chips Row -->
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <!-- Filter Badge with Count -->
        <div class="flex items-center">
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
            <BaseIcon name="FunnelIcon" class="w-3 h-3 mr-1" />
            Filter {{ activeFilterCount }}
          </span>
        </div>

        <!-- Customer Filter Chip -->
        <div v-if="filters.customer_id" class="flex items-center space-x-1">
          <span class="text-sm text-gray-500 dark:text-gray-400">Customer:</span>
          <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
            {{ getCustomerName(filters.customer_id) }}
            <button @click="clearCustomerFilter" class="ml-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <BaseIcon name="XMarkIcon" class="w-3 h-3" />
            </button>
          </span>
        </div>

        <!-- Status Filter Chip -->
        <div v-if="filters.status" class="flex items-center space-x-1">
          <span class="text-sm text-gray-500 dark:text-gray-400">Status:</span>
          <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
            {{ getStatusLabel(filters.status) }}
            <button @click="clearStatusFilter" class="ml-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <BaseIcon name="XMarkIcon" class="w-3 h-3" />
            </button>
          </span>
        </div>

        <!-- Date Range Filter Chip (from unified filter) -->
        <div v-if="currentDateRangeLabel && currentDateRangeLabel !== 'Last 30 days'" class="flex items-center space-x-1">
          <span class="text-sm text-gray-500 dark:text-gray-400">Date:</span>
          <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
            {{ currentDateRangeLabel }}
            <div class="ml-1 text-gray-400">
              <BaseIcon name="LockClosedIcon" class="w-3 h-3" title="Controlled by global date filter" />
            </div>
          </span>
        </div>

        <!-- Search Input -->
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <BaseIcon name="MagnifyingGlassIcon" class="h-4 w-4 text-gray-400" />
          </div>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search"
            class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-sm"
          />
        </div>
      </div>

      <!-- Expanded Filters (when filter button is clicked) -->
      <div v-if="showFilters" class="space-y-4">
        <!-- Status Filter Buttons -->
        <div>
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="status in statusOptions"
              :key="status.value"
              :class="[
                'px-3 py-1 text-xs font-medium rounded-full transition-colors duration-200',
                filters.status === status.value
                  ? 'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              ]"
              @click="toggleStatusFilter(status.value)"
            >
              {{ status.label }}
            </button>
          </div>
        </div>

        <!-- Advanced Filters Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Customer Filter -->
          <BaseInputGroup :label="$t('customers.customer', 1)">
            <BaseCustomerSelectInput
              v-model="filters.customer_id"
              :placeholder="$t('customers.type_or_click')"
              value-prop="id"
              label="name"
            />
          </BaseInputGroup>

          <!-- Date Range Info -->
          <BaseInputGroup label="Date Range">
            <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md">
              Controlled by global filter: {{ currentDateRangeLabel }}
            </div>
          </BaseInputGroup>
        </div>
   <!-- Filter Actions -->
   <div class="flex items-center justify-between pt-4">
          <BaseButton variant="primary-outline" size="sm" @click="clearAllFilters">
            {{ $t('general.clear_all') }}
          </BaseButton>
          <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ filteredCount }} of {{ totalCount }} invoices shown
          </div>
        </div>
      </div>
     
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
        <thead class="bg-gray-50 dark:bg-gray-700">
          <tr>
            <!-- Select All Checkbox -->
            <th scope="col" class="px-6 py-3 text-left">
              <BaseCheckbox
                v-model="selectAllField"
                variant="primary"
                @change="selectAllInvoices"
              />
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              <div class="flex items-center space-x-1 cursor-pointer" @click="sortBy('invoice_number')">
                <span>Number</span>
                <BaseIcon name="ChevronUpDownIcon" class="w-3 h-3" />
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              <div class="flex items-center space-x-1 cursor-pointer" @click="sortBy('status')">
                <span>Status</span>
                <BaseIcon name="ChevronUpDownIcon" class="w-3 h-3" />
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              <div class="flex items-center space-x-1 cursor-pointer" @click="sortBy('invoice_date')">
                <span>Date</span>
                <BaseIcon name="ChevronUpDownIcon" class="w-3 h-3" />
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              Customer
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              <div class="flex items-center space-x-1 cursor-pointer" @click="sortBy('total')">
                <span>Total</span>
                <BaseIcon name="ChevronUpDownIcon" class="w-3 h-3" />
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
              <div class="flex items-center space-x-1 cursor-pointer" @click="sortBy('due_amount')">
                <span>Amount Due</span>
                <BaseIcon name="ChevronUpDownIcon" class="w-3 h-3" />
              </div>
            </th>
            <th scope="col" class="relative px-6 py-3">
              <span class="sr-only">Actions</span>
            </th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
          <!-- Loading State -->
          <tr v-if="isLoading" v-for="n in 5" :key="n" class="animate-pulse">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="h-4 w-4 bg-gray-200 dark:bg-gray-600 rounded"></div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded w-20"></div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="h-6 bg-gray-200 dark:bg-gray-600 rounded-full w-16"></div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded w-24"></div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="h-8 w-8 bg-gray-200 dark:bg-gray-600 rounded-full"></div>
                <div class="ml-3 h-4 bg-gray-200 dark:bg-gray-600 rounded w-32"></div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded w-20"></div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded w-20"></div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right">
              <div class="h-6 w-6 bg-gray-200 dark:bg-gray-600 rounded"></div>
            </td>
          </tr>

          <!-- Invoice Rows -->
          <tr v-else v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
            <!-- Checkbox -->
            <td class="px-6 py-4 whitespace-nowrap">
              <BaseCheckbox
                :id="invoice.id"
                v-model="selectedInvoices"
                :value="invoice.id"
                variant="primary"
              />
            </td>

            <!-- Invoice Number -->
            <td class="px-6 py-4 whitespace-nowrap">
              <router-link
                :to="{ path: `/admin/invoices/${invoice.id}/view` }"
                class="flex items-center text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
              >
                <BaseIcon name="DocumentTextIcon" class="w-4 h-4 mr-2 text-gray-400" />
                {{ invoice.invoice_number }}
              </router-link>
            </td>

            <!-- Status -->
            <td class="px-6 py-4 whitespace-nowrap">
              <BaseInvoiceStatusBadge :status="invoice.status" class="rounded-full">
                <BaseInvoiceStatusLabel :status="invoice.status" />
              </BaseInvoiceStatusBadge>
            </td>

            <!-- Date -->
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
              {{ formatDate(invoice.invoice_date) }}
            </td>

            <!-- Customer -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="flex-shrink-0 h-8 w-8">
                  <div 
                    :class="getCustomerAvatarColor(invoice.customer.name)"
                    class="h-8 w-8 rounded-full flex items-center justify-center text-white text-sm font-medium"
                  >
                    {{ getCustomerInitials(invoice.customer.name) }}
                  </div>
                </div>
                <div class="ml-3">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ invoice.customer.name }}
                  </div>
                  <div v-if="invoice.customer.contact_name" class="text-xs text-gray-500 dark:text-gray-400">
                    {{ invoice.customer.contact_name }}
                  </div>
                </div>
              </div>
            </td>

            <!-- Total -->
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
              <BaseFormatMoney
                :amount="invoice.total"
                :currency="invoice.customer.currency"
              />
            </td>

            <!-- Amount Due -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex flex-col space-y-1">
                <div class="text-sm font-medium text-gray-900 dark:text-white">
                  <BaseFormatMoney
                    :amount="invoice.due_amount"
                    :currency="invoice.customer.currency"
                  />
                </div>
                <div class="flex space-x-1">
                  <BasePaidStatusBadge
                    v-if="invoice.overdue"
                    status="OVERDUE"
                    class="px-1 py-0.5 text-xs"
                  >
                    {{ $t('invoices.overdue') }}
                  </BasePaidStatusBadge>
                  <BasePaidStatusBadge
                    :status="invoice.paid_status"
                    class="px-1 py-0.5 text-xs"
                  >
                    <BaseInvoiceStatusLabel :status="invoice.paid_status" />
                  </BasePaidStatusBadge>
                </div>
              </div>
            </td>

            <!-- Actions -->
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <InvoiceDropdown 
                :row="invoice" 
                :table="{ refresh: loadInvoices }"
                @invoice-updated="loadInvoices"
              />
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-if="!isLoading && invoices.length === 0">
            <td colspan="8" class="px-6 py-12 text-center">
              <div class="text-gray-500 dark:text-gray-400">
                <BaseIcon name="DocumentTextIcon" class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                <p class="text-sm font-medium">{{ $t('invoices.no_invoices') }}</p>
                <p class="text-xs mt-1">{{ $t('invoices.list_of_invoices') }}</p>
                <router-link 
                  v-if="userStore.hasAbilities(abilities.CREATE_INVOICE)"
                  to="/admin/invoices/create"
                  class="inline-flex items-center mt-4 px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-primary-700 bg-primary-100 hover:bg-primary-200 dark:bg-primary-900 dark:text-primary-300 dark:hover:bg-primary-800"
                >
                  <BaseIcon name="PlusIcon" class="w-4 h-4 mr-1" />
                  {{ $t('invoices.add_new_invoice') }}
                </router-link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="totalCount > pageSize && !isLoading" class="px-6 py-4 border-t border-gray-200 dark:border-gray-600">
      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-700 dark:text-gray-300">
          Showing {{ ((currentPage - 1) * pageSize) + 1 }} to {{ Math.min(currentPage * pageSize, totalCount) }} of {{ totalCount }} results
        </div>
        <div class="flex items-center space-x-2">
          <BaseButton
            variant="primary-outline"
            size="sm"
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
          >
            <BaseIcon name="ChevronLeftIcon" class="w-4 h-4" />
            Previous
          </BaseButton>
          
          <div class="flex space-x-1">
            <button
              v-for="page in visiblePages"
              :key="page"
              :class="[
                'px-3 py-1 text-sm rounded-md transition-colors duration-200',
                page === currentPage
                  ? 'bg-primary-600 text-white'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
              ]"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
          </div>

          <BaseButton
            variant="primary-outline"
            size="sm"
            :disabled="currentPage === totalPages"
            @click="goToPage(currentPage + 1)"
          >
            Next
            <BaseIcon name="ChevronRightIcon" class="w-4 h-4" />
          </BaseButton>
        </div>
      </div>
    </div>
  </div>

  <!-- Modals -->
  <SendInvoiceModal />
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDateFilterStore } from '@/scripts/admin/stores/dateFilter'
import { debouncedWatch } from '@vueuse/core'
import abilities from '@/scripts/admin/stub/abilities'

// Components
import InvoiceDropdown from '@/scripts/admin/components/dropdowns/InvoiceIndexDropdown.vue'
import SendInvoiceModal from '@/scripts/admin/components/modal-components/SendInvoiceModal.vue'
import BaseInvoiceStatusLabel from '@/scripts/components/base/BaseInvoiceStatusLabel.vue'
import BaseInvoiceStatusBadge from '@/scripts/components/base/BaseInvoiceStatusBadge.vue'
import BasePaidStatusBadge from '@/scripts/components/base/BasePaidStatusBadge.vue'
import BaseCustomerSelectInput from '@/scripts/components/base/BaseCustomerSelectInput.vue'

const { t } = useI18n()
const userStore = useUserStore()
const invoiceStore = useInvoiceStore()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const dateFilterStore = useDateFilterStore()

// State
const invoices = ref([])
const isLoading = ref(false)
const showFilters = ref(false)
const selectedInvoices = ref([])
const currentPage = ref(1)
const totalCount = ref(0)
const filteredCount = ref(0)
const pageSize = ref(10)
const sortField = ref('created_at')
const sortOrder = ref('desc')
const currentDateRange = ref({ start: '', end: '' })
const currentDateRangeLabel = ref('Last 30 days')

// Filters (removed date filters as they're now controlled by unified filter)
const filters = reactive({
  customer_id: '',
  status: '',
  search: ''
})

// Status options for filter
const statusOptions = ref([
  { label: t('general.all'), value: '' },
  { label: t('general.draft'), value: 'DRAFT' },
  { label: t('general.sent'), value: 'SENT' },
  { label: t('general.due'), value: 'DUE' },
  { label: t('invoices.overdue'), value: 'OVERDUE' },
  { label: t('invoices.viewed'), value: 'VIEWED' },
  { label: t('invoices.completed'), value: 'COMPLETED' },
  { label: t('invoices.unpaid'), value: 'UNPAID' },
  { label: t('invoices.paid'), value: 'PAID' },
  { label: t('invoices.partially_paid'), value: 'PARTIALLY_PAID' }
])

// Computed
const totalPages = computed(() => Math.ceil(totalCount.value / pageSize.value))

const selectAllField = computed({
  get: () => selectedInvoices.value.length === invoices.value.length && invoices.value.length > 0,
  set: (value) => {
    if (value) {
      selectedInvoices.value = invoices.value.map(invoice => invoice.id)
    } else {
      selectedInvoices.value = []
    }
  }
})

const visiblePages = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, currentPage.value + 2)
  
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  
  return pages
})

const hasActiveFilters = computed(() => {
  return filters.customer_id || filters.status || filters.search || 
         (currentDateRangeLabel.value !== 'Last 30 days')
})

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.customer_id) count++
  if (filters.status) count++
  if (filters.search) count++
  if (currentDateRangeLabel.value !== 'Last 30 days') count++
  return count
})

// Initialize current date range from unified filter
function initializeDateRange() {
  const range = dateFilterStore.dateRange
  currentDateRange.value = range
  currentDateRangeLabel.value = dateFilterStore.displayLabel
}

// Watch filters with debounce
debouncedWatch(
  filters,
  () => {
    currentPage.value = 1
    loadInvoices()
  },
  { debounce: 500 }
)

// Methods
async function loadInvoices() {
  isLoading.value = true
  
  try {
    const params = {
      page: currentPage.value,
      per_page: pageSize.value,
      orderByField: sortField.value,
      orderBy: sortOrder.value,
      customer_id: filters.customer_id,
      status: filters.status,
      from_date: currentDateRange.value.start,
      to_date: currentDateRange.value.end,
      search: filters.search
    }

    const response = await invoiceStore.fetchInvoices(params)
    
    invoices.value = response.data.data
    totalCount.value = response.data.meta.total
    filteredCount.value = response.data.meta.total
    
  } catch (error) {
    console.error('Error loading invoices:', error)
    notificationStore.showNotification({
      type: 'error',
      message: t('general.something_went_wrong')
    })
  } finally {
    isLoading.value = false
  }
}

function toggleFilters() {
  showFilters.value = !showFilters.value
}

function clearAllFilters() {
  filters.customer_id = ''
  filters.status = ''
  filters.search = ''
  showFilters.value = false
  // Note: Date filter cannot be cleared from table as it's controlled by global filter
}

function clearCustomerFilter() {
  filters.customer_id = ''
}

function clearStatusFilter() {
  filters.status = ''
}

function toggleStatusFilter(value) {
  filters.status = filters.status === value ? '' : value
}

function sortBy(field) {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortOrder.value = 'asc'
  }
  loadInvoices()
}

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    loadInvoices()
  }
}

function selectAllInvoices() {
  selectAllField.value = !selectAllField.value
}

async function deleteMultipleInvoices() {
  const result = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  })

  if (result) {
    try {
      await invoiceStore.deleteInvoice({ ids: selectedInvoices.value })
      selectedInvoices.value = []
      loadInvoices()
      notificationStore.showNotification({
        type: 'success',
        message: t('invoices.deleted_successfully')
      })
    } catch (error) {
      notificationStore.showNotification({
        type: 'error',
        message: t('general.something_went_wrong')
      })
    }
  }
}

// Utility functions
function getCustomerAvatarColor(name) {
  const colors = [
    'bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500',
    'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-orange-500'
  ]
  const index = name.charCodeAt(0) % colors.length
  return colors[index]
}

function getCustomerInitials(name) {
  return name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .substring(0, 2)
}

function formatDate(date) {
  if (!date) return ''
  const d = new Date(date)
  const day = d.getDate()
  const month = d.toLocaleDateString('en-US', { month: 'short' })
  const year = d.getFullYear()
  
  const suffix = day === 1 || day === 21 || day === 31 ? 'st' :
                 day === 2 || day === 22 ? 'nd' :
                 day === 3 || day === 23 ? 'rd' : 'th'
  
  return `${day}${suffix} ${month} ${year}`
}
function getStatusLabel(value) {
  const status = statusOptions.value.find(s => s.value === value)
  return status ? status.label : value
}

function getCustomerName(customerId) {
  // Try to find customer name from loaded invoices
  const invoice = invoices.value.find(inv => inv.customer.id == customerId)
  return invoice ? invoice.customer.name : 'Customer'
}

// Method to refresh table with new date range from unified filter
function refreshWithDateRange(newDateRange) {
  currentDateRange.value = newDateRange
  currentDateRangeLabel.value = dateFilterStore.displayLabel
  currentPage.value = 1
  loadInvoices()
}

// Export method for PDF snapshot
function getTableDataForSnapshot() {
  return {
    invoices: invoices.value,
    totalCount: totalCount.value,
    filteredCount: filteredCount.value,
    filters: {
      customer_id: filters.customer_id,
      status: filters.status,
      from_date: currentDateRange.value.start,
      to_date: currentDateRange.value.end,
      search: filters.search
    },
    hasActiveFilters: hasActiveFilters.value,
    activeFilterCount: activeFilterCount.value
  }
}

// Expose methods to parent component
defineExpose({
  getTableDataForSnapshot,
  refreshWithDateRange
})

// Lifecycle
onMounted(() => {
  initializeDateRange()
  loadInvoices()
})
</script>
