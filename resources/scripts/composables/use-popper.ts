import { ref, onMounted, watchEffect, type Ref } from 'vue'
import { createPopper, type Instance, type Options } from '@popperjs/core'
import { flipPlacement, isRtl } from '@/scripts/utils/direction'

export function usePopper(options: Partial<Options> = {}) {
  const trigger = ref<HTMLElement | null>(null)
  const container = ref<HTMLElement | null>(null)
  const popper = ref<Instance | null>(null)

  onMounted(() => {
    watchEffect((onCleanup) => {
      if (!trigger.value || !container.value) return

      const instance = createPopper(trigger.value, container.value, logicalOptions())
      popper.value = instance

      onCleanup(() => instance.destroy())
    })
  })

  // Placements are written for left to right ("bottom-end" = under the
  // trigger's right edge); in a right-to-left page they mirror. Reading
  // isRtl here re-creates the popper when the direction changes.
  function logicalOptions(): Partial<Options> {
    if (!isRtl.value) {
      return options
    }

    return {
      ...options,
      placement: options.placement ? flipPlacement(options.placement) : options.placement,
      modifiers: options.modifiers?.map((modifier) => {
        const fallbacks = (modifier.options as { fallbackPlacements?: string[] } | undefined)?.fallbackPlacements

        return fallbacks
          ? { ...modifier, options: { ...modifier.options, fallbackPlacements: fallbacks.map(flipPlacement) } }
          : modifier
      }),
    }
  }

  return [trigger, container, popper] as const
}
