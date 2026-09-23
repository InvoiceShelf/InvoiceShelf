<template>
  <div class="relative" :class="wrapperClass">
    <BaseContentPlaceholders
      v-if="contentLoading"
      class="disabled pointer-events-none"
    >
      <BaseContentPlaceholdersBox
        :rounded="true"
        class="w-14"
        style="height: 40px"
      />
    </BaseContentPlaceholders>
    <DropdownMenuRoot v-else v-model:open="open" :modal="false">
      <span ref="trigger" :class="inActionBar ? 'flex w-full' : 'inline-flex'">
        <DropdownMenuTrigger as-child>
          <button
            type="button"
            :class="inActionBar ? 'w-full' : ''"
            :aria-label="triggerLabel"
            class="rounded-lg focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
          >
            <slot name="activator" />
          </button>
        </DropdownMenuTrigger>
      </span>

      <DropdownMenuPortal>
        <!-- Phones: an action sheet from the bottom edge, over a dimmed page -->
        <template v-if="isPhone">
          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <div v-if="open" class="fixed inset-0 z-50 bg-overlay" aria-hidden="true" />
          </transition>
          <DropdownMenuContent
            :reference="bottomEdge"
            side="top"
            :side-offset="0"
            :avoid-collisions="false"
            class="
              z-50 w-screen max-h-[80dvh] overflow-y-auto px-2 pt-2
              glass-strong rounded-t-2xl safe-drawer focus:outline-hidden
              data-[state=open]:animate-sheet-in data-[state=closed]:animate-sheet-out
            "
            :class="containerClass"
          >
            <div class="flex justify-center pb-2" aria-hidden="true">
              <span class="h-1 w-9 rounded-full bg-line-strong" />
            </div>
            <slot />
          </DropdownMenuContent>
        </template>

        <!-- Tablet and desktop: anchored to the activator -->
        <DropdownMenuContent
          v-else
          :side="placement.side"
          :align="placement.align"
          :side-offset="6"
          :collision-padding="8"
          class="
            z-50 p-1 rounded-xl border glass-strong focus:outline-hidden
            origin-(--reka-dropdown-menu-content-transform-origin)
            data-[state=open]:animate-pop-in data-[state=closed]:animate-pop-out
          "
          :class="[widthClass, containerClass]"
        >
          <slot />
        </DropdownMenuContent>
      </DropdownMenuPortal>
    </DropdownMenuRoot>
  </div>
</template>

<script setup lang="ts">
import {
  DropdownMenuContent,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuTrigger,
} from 'reka-ui'
import { computed, inject, onMounted, onUpdated, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import type { Placement } from '@popperjs/core'

interface Props {
  containerClass?: string
  widthClass?: string
  positionClass?: string
  /** Where the menu opens, in popper terms ("bottom-end" is under the trigger's end edge) */
  position?: Placement
  wrapperClass?: string
  contentLoading?: boolean
  /** The trigger's accessible name, for activators that show only an icon */
  label?: string
}

const props = withDefaults(defineProps<Props>(), {
  containerClass: '',
  widthClass: 'w-56',
  positionClass: 'absolute z-10 end-0',
  position: 'bottom-end',
  wrapperClass: 'inline-block h-full text-start',
  contentLoading: false,
  label: '',
})

const { t } = useI18n()
const { isPhone } = useBreakpoints()
const inActionBar = inject<boolean>('inActionBar', false)

const open = ref<boolean>(false)

// BaseDropdownItem renders taller, touch-sized rows inside the sheet
provide('dropdownIsSheet', isPhone)

type Side = 'top' | 'right' | 'bottom' | 'left'
type Align = 'start' | 'center' | 'end'

// "bottom-end" becomes side bottom, align end. Reka lays these out with
// floating-ui, which mirrors start and end in a right-to-left page.
const placement = computed<{ side: Side; align: Align }>(() => {
  const [side, align] = props.position.split('-') as [string, string | undefined]

  return {
    side: (side === 'auto' ? 'bottom' : side) as Side,
    align: (align ?? 'center') as Align,
  }
})

// The phone sheet hangs from the bottom edge of the screen
const bottomEdge = {
  getBoundingClientRect: (): DOMRect => new DOMRect(0, window.innerHeight, window.innerWidth, 0),
}

// An activator that shows only an icon still needs a name: without a label
// it falls back to "Actions"
const trigger = ref<HTMLElement | null>(null)
const activatorHasText = ref<boolean>(true)

function checkActivatorText(): void {
  activatorHasText.value = !!trigger.value?.textContent?.trim()
}

onMounted(checkActivatorText)
onUpdated(checkActivatorText)

const triggerLabel = computed<string | undefined>(() => {
  if (props.label) {
    return props.label
  }

  return activatorHasText.value ? undefined : t('general.actions')
})
</script>
