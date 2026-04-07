import { ref, onMounted, watchEffect, type Ref } from 'vue'
import { createPopper, type Instance, type Options } from '@popperjs/core'

export function usePopper(options: Partial<Options> = {}) {
  const trigger = ref<HTMLElement | null>(null)
  const container = ref<HTMLElement | null>(null)
  const popper = ref<Instance | null>(null)

  onMounted(() => {
    watchEffect((onCleanup) => {
      if (!trigger.value || !container.value) return

      const instance = createPopper(trigger.value, container.value, options)
      popper.value = instance

      onCleanup(() => instance.destroy())
    })
  })

  return [trigger, container, popper] as const
}
