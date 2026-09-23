import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl'

export interface OpenModalPayload {
  componentName: string
  title: string
  id?: string | number
  content?: string
  data?: unknown
  refreshData?: ((...args: unknown[]) => void) | null
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
  const refreshData = ref<((...args: unknown[]) => void) | null>(null)
  const variant = ref<string>('')

  // Getters
  const isEdit = computed<boolean>(() => {
    return id.value !== '' && id.value !== 0
  })

  // Actions
  // Everything the caller leaves out starts empty, so a modal never picks
  // up the previous one's record, data or callback
  function openModal(payload: OpenModalPayload): void {
    componentName.value = payload.componentName
    active.value = true
    id.value = payload.id ?? ''
    title.value = payload.title
    content.value = payload.content ?? ''
    data.value = payload.data ?? null
    refreshData.value = payload.refreshData ?? null
    variant.value = payload.variant ?? ''
    size.value = payload.size ?? 'md'
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

    // After the closing animation, unless another modal opened meanwhile
    setTimeout(() => {
      if (!active.value) {
        resetModalData()
      }
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
