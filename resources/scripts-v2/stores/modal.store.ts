import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl'

export interface OpenModalPayload {
  componentName: string
  title: string
  id?: string | number
  content?: string
  data?: unknown
  refreshData?: (() => void) | null
  variant?: string
  size?: ModalSize
}

export const useModalStore = defineStore('modal', () => {
  // State
  const active = ref<boolean>(false)
  const content = ref<string>('')
  const title = ref<string>('')
  const componentName = ref<string>('')
  const id = ref<string | number>('')
  const size = ref<ModalSize>('md')
  const data = ref<unknown>(null)
  const refreshData = ref<(() => void) | null>(null)
  const variant = ref<string>('')

  // Getters
  const isEdit = computed<boolean>(() => {
    return id.value !== '' && id.value !== 0
  })

  // Actions
  function openModal(payload: OpenModalPayload): void {
    componentName.value = payload.componentName
    active.value = true

    if (payload.id) {
      id.value = payload.id
    }

    title.value = payload.title

    if (payload.content) {
      content.value = payload.content
    }

    if (payload.data) {
      data.value = payload.data
    }

    if (payload.refreshData) {
      refreshData.value = payload.refreshData
    }

    if (payload.variant) {
      variant.value = payload.variant
    }

    if (payload.size) {
      size.value = payload.size
    }
  }

  function resetModalData(): void {
    content.value = ''
    title.value = ''
    componentName.value = ''
    id.value = ''
    data.value = null
    refreshData.value = null
  }

  function closeModal(): void {
    active.value = false

    setTimeout(() => {
      resetModalData()
    }, 300)
  }

  return {
    active,
    content,
    title,
    componentName,
    id,
    size,
    data,
    refreshData,
    variant,
    isEdit,
    openModal,
    resetModalData,
    closeModal,
  }
})
