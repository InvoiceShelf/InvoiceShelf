import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { itemService } from '../../../api/services/item.service'
import type {
  ItemListParams,
  ItemListResponse,
  CreateItemPayload,
  CreateUnitPayload,
} from '../../../api/services/item.service'
import { useNotificationStore } from '../../../stores/notification.store'
import { handleApiError } from '../../../utils/error-handling'
import type { Item, Unit } from '../../../types/domain/item'
import type { Tax } from '../../../types/domain/tax'
import type { ApiResponse, DeletePayload } from '../../../types/api'

export interface ItemForm {
  id?: number
  name: string
  description: string
  price: number
  unit_id: string | number | null
  unit: Unit | null
  taxes: Tax[]
  tax_per_item?: boolean | number | string
}

export interface ItemUnitForm {
  id: number | null
  name: string
}

function createItemStub(): ItemForm {
  return {
    name: '',
    description: '',
    price: 0,
    unit_id: '',
    unit: null,
    taxes: [],
    tax_per_item: false,
  }
}

export const useItemStore = defineStore('item', () => {
  // State
  const items = ref<Item[]>([])
  const totalItems = ref<number>(0)
  const selectAllField = ref<boolean>(false)
  const selectedItems = ref<number[]>([])
  const itemUnits = ref<Unit[]>([])
  const currentItemUnit = ref<ItemUnitForm>({
    id: null,
    name: '',
  })
  const currentItem = ref<ItemForm>(createItemStub())

  // Getters
  const isEdit = computed<boolean>(() => !!currentItem.value.id)

  const isItemUnitEdit = computed<boolean>(() => !!currentItemUnit.value.id)

  // Actions
  function resetCurrentItem(): void {
    currentItem.value = createItemStub()
  }

  async function fetchItems(params?: ItemListParams): Promise<ItemListResponse> {
    try {
      const response = await itemService.list(params)
      items.value = response.data
      totalItems.value = response.meta.item_total_count
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchItem(id: number): Promise<ApiResponse<Item>> {
    try {
      const response = await itemService.get(id)
      if (response.data) {
        Object.assign(currentItem.value, response.data)
      }
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function addItem(data: Record<string, unknown>): Promise<ApiResponse<Item>> {
    try {
      const response = await itemService.create(data as unknown as CreateItemPayload)
      items.value.push(response.data)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'items.created_message',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateItem(data: Record<string, unknown>): Promise<ApiResponse<Item>> {
    try {
      const id = data.id as number
      const response = await itemService.update(id, data as unknown as Partial<CreateItemPayload>)

      if (response.data) {
        const pos = items.value.findIndex(
          (item) => item.id === response.data.id
        )
        if (pos !== -1) {
          items.value[pos] = response.data
        }

        const notificationStore = useNotificationStore()
        notificationStore.showNotification({
          type: 'success',
          message: 'items.updated_message',
        })
      }

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function deleteItem(payload: DeletePayload): Promise<{ success: boolean }> {
    try {
      const response = await itemService.delete(payload)

      const index = items.value.findIndex(
        (item) => item.id === payload.ids[0]
      )
      if (index !== -1) {
        items.value.splice(index, 1)
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'items.deleted_message',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function deleteMultipleItems(): Promise<{ success: boolean }> {
    try {
      const response = await itemService.delete({ ids: selectedItems.value })

      selectedItems.value.forEach((itemId) => {
        const index = items.value.findIndex((_item) => _item.id === itemId)
        if (index !== -1) {
          items.value.splice(index, 1)
        }
      })

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'items.deleted_message',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  function selectItem(data: number[]): void {
    selectedItems.value = data
    selectAllField.value = selectedItems.value.length === items.value.length
  }

  function selectAllItems(): void {
    if (selectedItems.value.length === items.value.length) {
      selectedItems.value = []
      selectAllField.value = false
    } else {
      selectedItems.value = items.value.map((item) => item.id)
      selectAllField.value = true
    }
  }

  async function addItemUnit(data: CreateUnitPayload): Promise<ApiResponse<Unit>> {
    try {
      const response = await itemService.createUnit(data)
      itemUnits.value.push(response.data)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.customization.items.item_unit_added',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateItemUnit(data: { id: number; name: string }): Promise<ApiResponse<Unit>> {
    try {
      const response = await itemService.updateUnit(data.id, { name: data.name })

      const pos = itemUnits.value.findIndex(
        (unit) => unit.id === response.data.id
      )
      if (pos !== -1) {
        itemUnits.value[pos] = response.data
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.customization.items.item_unit_updated',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchItemUnits(params?: { limit?: string | number; search?: string }): Promise<{ data: { data: Unit[] } }> {
    try {
      const response = await itemService.listUnits(params)
      itemUnits.value = response.data as unknown as Unit[]
      return { data: { data: response.data as unknown as Unit[] } }
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchItemUnit(id: number): Promise<ApiResponse<Unit>> {
    try {
      const response = await itemService.getUnit(id)
      currentItemUnit.value = {
        id: response.data.id,
        name: response.data.name,
      }
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function deleteItemUnit(id: number): Promise<{ success: boolean }> {
    try {
      const response = await itemService.deleteUnit(id)

      if (response.success) {
        const index = itemUnits.value.findIndex((unit) => unit.id === id)
        if (index !== -1) {
          itemUnits.value.splice(index, 1)
        }

        const notificationStore = useNotificationStore()
        notificationStore.showNotification({
          type: 'success',
          message: 'settings.customization.items.deleted_message',
        })
      }

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  return {
    items,
    totalItems,
    selectAllField,
    selectedItems,
    itemUnits,
    currentItemUnit,
    currentItem,
    isEdit,
    isItemUnitEdit,
    resetCurrentItem,
    fetchItems,
    fetchItem,
    addItem,
    updateItem,
    deleteItem,
    deleteMultipleItems,
    selectItem,
    selectAllItems,
    addItemUnit,
    updateItemUnit,
    fetchItemUnits,
    fetchItemUnit,
    deleteItemUnit,
  }
})
