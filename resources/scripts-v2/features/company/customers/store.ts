import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { customerService } from '../../../api/services/customer.service'
import type {
  CustomerListParams,
  CustomerListResponse,
  CustomerStatsData,
} from '../../../api/services/customer.service'
import { useNotificationStore } from '../../../stores/notification.store'
import { useGlobalStore } from '../../../stores/global.store'
import { useCompanyStore } from '../../../stores/company.store'
import { handleApiError } from '../../../utils/error-handling'
import type { Customer, CreateCustomerPayload } from '../../../types/domain/customer'
import type { Address } from '../../../types/domain/user'
import type { ApiResponse, DeletePayload } from '../../../types/api'

export interface CustomerFormAddress {
  name: string | null
  phone: string | null
  address_street_1: string | null
  address_street_2: string | null
  city: string | null
  state: string | null
  country_id: number | null
  zip: string | null
  type: string | null
}

export interface CustomerForm {
  id?: number
  name: string
  contact_name: string
  email: string
  phone: string | null
  password: string
  confirm_password: string
  currency_id: number | null
  website: string | null
  prefix?: string | null
  tax_id?: string | null
  billing: CustomerFormAddress
  shipping: CustomerFormAddress
  customFields: unknown[]
  fields: unknown[]
  enable_portal: boolean
  password_added?: boolean
}

export interface CustomerViewData {
  customer?: Customer
  [key: string]: unknown
}

function createAddressStub(): CustomerFormAddress {
  return {
    name: null,
    phone: null,
    address_street_1: null,
    address_street_2: null,
    city: null,
    state: null,
    country_id: null,
    zip: null,
    type: null,
  }
}

function createCustomerStub(): CustomerForm {
  return {
    name: '',
    contact_name: '',
    email: '',
    phone: null,
    password: '',
    confirm_password: '',
    currency_id: null,
    website: null,
    billing: createAddressStub(),
    shipping: createAddressStub(),
    customFields: [],
    fields: [],
    enable_portal: false,
  }
}

export const useCustomerStore = defineStore('customer', () => {
  // State
  const customers = ref<Customer[]>([])
  const totalCustomers = ref<number>(0)
  const selectAllField = ref<boolean>(false)
  const selectedCustomers = ref<number[]>([])
  const selectedViewCustomer = ref<CustomerViewData>({})
  const isFetchingInitialSettings = ref<boolean>(false)
  const isFetchingViewData = ref<boolean>(false)
  const currentCustomer = ref<CustomerForm>(createCustomerStub())
  const editCustomer = ref<Customer | null>(null)

  // Getters
  const isEdit = computed<boolean>(() => !!currentCustomer.value.id)

  // Actions
  function resetCurrentCustomer(): void {
    currentCustomer.value = createCustomerStub()
  }

  function copyAddress(fromBillingToShipping = true): void {
    if (fromBillingToShipping) {
      currentCustomer.value.shipping = {
        ...currentCustomer.value.billing,
        type: 'shipping',
      }
    }
  }

  async function fetchCustomerInitialSettings(isEditMode: boolean): Promise<void> {
    const route = useRoute()
    const globalStore = useGlobalStore()
    const companyStore = useCompanyStore()

    isFetchingInitialSettings.value = true

    const editActions: Promise<unknown>[] = []
    if (isEditMode) {
      editActions.push(fetchCustomer(Number(route.params.id)))
    } else {
      currentCustomer.value.currency_id =
        companyStore.selectedCompanyCurrency?.id ?? null
    }

    try {
      await Promise.all([
        globalStore.fetchCurrencies(),
        globalStore.fetchCountries(),
        ...editActions,
      ])
      isFetchingInitialSettings.value = false
    } catch (err: unknown) {
      isFetchingInitialSettings.value = false
      handleApiError(err)
    }
  }

  async function fetchCustomers(params?: CustomerListParams): Promise<CustomerListResponse> {
    try {
      const response = await customerService.list(params)
      customers.value = response.data
      totalCustomers.value = response.meta.customer_total_count
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchViewCustomer(params: { id: number }): Promise<ApiResponse<CustomerStatsData>> {
    isFetchingViewData.value = true
    try {
      const response = await customerService.getStats(params.id, params as Record<string, unknown>)
      selectedViewCustomer.value = {}
      Object.assign(selectedViewCustomer.value, response.data)
      setAddressStub(response.data as unknown as Record<string, unknown>)
      isFetchingViewData.value = false
      return response
    } catch (err: unknown) {
      isFetchingViewData.value = false
      handleApiError(err)
      throw err
    }
  }

  async function fetchCustomer(id: number): Promise<ApiResponse<Customer>> {
    try {
      const response = await customerService.get(id)
      Object.assign(currentCustomer.value, response.data)
      setAddressStub(response.data as unknown as Record<string, unknown>)
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function addCustomer(data: CustomerForm): Promise<ApiResponse<Customer>> {
    try {
      const response = await customerService.create(data as unknown as CreateCustomerPayload)
      customers.value.push(response.data)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'customers.created_message',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateCustomer(data: CustomerForm): Promise<ApiResponse<Customer>> {
    try {
      const response = await customerService.update(
        data.id!,
        data as unknown as Partial<CreateCustomerPayload>
      )

      if (response.data) {
        const pos = customers.value.findIndex(
          (customer) => customer.id === response.data.id
        )
        if (pos !== -1) {
          customers.value[pos] = response.data
        }

        const notificationStore = useNotificationStore()
        notificationStore.showNotification({
          type: 'success',
          message: 'customers.updated_message',
        })
      }

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function deleteCustomer(payload: DeletePayload): Promise<{ success: boolean }> {
    try {
      const response = await customerService.delete(payload)

      const index = customers.value.findIndex(
        (customer) => customer.id === payload.ids[0]
      )
      if (index !== -1) {
        customers.value.splice(index, 1)
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'customers.deleted_message',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function deleteMultipleCustomers(): Promise<{ success: boolean }> {
    try {
      const response = await customerService.delete({ ids: selectedCustomers.value })

      selectedCustomers.value.forEach((customerId) => {
        const index = customers.value.findIndex(
          (_customer) => _customer.id === customerId
        )
        if (index !== -1) {
          customers.value.splice(index, 1)
        }
      })

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'customers.deleted_message',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  function setSelectAllState(data: boolean): void {
    selectAllField.value = data
  }

  function selectCustomer(data: number[]): void {
    selectedCustomers.value = data
    selectAllField.value = selectedCustomers.value.length === customers.value.length
  }

  function selectAllCustomers(): void {
    if (selectedCustomers.value.length === customers.value.length) {
      selectedCustomers.value = []
      selectAllField.value = false
    } else {
      selectedCustomers.value = customers.value.map((customer) => customer.id)
      selectAllField.value = true
    }
  }

  function setAddressStub(data: Record<string, unknown>): void {
    if (!data.billing) {
      currentCustomer.value.billing = createAddressStub()
    }
    if (!data.shipping) {
      currentCustomer.value.shipping = createAddressStub()
    }
  }

  return {
    customers,
    totalCustomers,
    selectAllField,
    selectedCustomers,
    selectedViewCustomer,
    isFetchingInitialSettings,
    isFetchingViewData,
    currentCustomer,
    editCustomer,
    isEdit,
    resetCurrentCustomer,
    copyAddress,
    fetchCustomerInitialSettings,
    fetchCustomers,
    fetchViewCustomer,
    fetchCustomer,
    addCustomer,
    updateCustomer,
    deleteCustomer,
    deleteMultipleCustomers,
    setSelectAllState,
    selectCustomer,
    selectAllCustomers,
    setAddressStub,
  }
})
