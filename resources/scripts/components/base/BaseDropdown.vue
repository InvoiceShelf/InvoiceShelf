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
    <Menu v-else v-slot="{ open }">
      <span ref="trigger" :class="inActionBar ? 'flex w-full' : 'inline-flex'">
        <MenuButton
          :class="inActionBar ? 'w-full' : ''"
          :aria-label="triggerLabel"
          class="rounded-lg focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
          @click="onClick"
          @keydown="onClick"
        >
          <slot name="activator" />
        </MenuButton>
      </span>

      <Teleport to="body">
        <!-- Phones: an action sheet from the bottom edge -->
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
          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
          >
            <MenuItems
              class="
                fixed inset-x-0 bottom-0 z-50 max-h-[80dvh] overflow-y-auto px-2 pt-2
                glass-strong rounded-t-2xl safe-drawer focus:outline-hidden
              "
              :class="containerClass"
            >
              <div class="flex justify-center pb-2" aria-hidden="true">
                <span class="h-1 w-9 rounded-full bg-line-strong" />
              </div>
              <slot />
            </MenuItems>
          </transition>
        </template>

        <!-- Tablet and desktop: a popover anchored to the activator -->
        <div
          v-else
          ref="container"
          class="fixed top-0 left-0 z-50 pointer-events-none"
          :class="widthClass"
        >
          <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="scale-95 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-95 opacity-0"
          >
            <MenuItems :class="containerClasses">
              <slot />
            </MenuItems>
          </transition>
        </div>
      </Teleport>
    </Menu>
  </div>
</template>

<script setup lang="ts">
import { Menu, MenuButton, MenuItems } from '@headlessui/vue'
import { computed, inject, nextTick, onMounted, onUpdated, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePopper } from '@/scripts/composables/use-popper'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import type { Placement } from '@popperjs/core'

interface Props {
  containerClass?: string
  widthClass?: string
  positionClass?: string
  position?: Placement
  wrapperClass?: string
  contentLoading?: boolean
  /** The trigger's accessible name, for activators that show only an icon */
  label?: string
}

const props = withDefaults(defineProps<Props>(), {
  containerClass: '',
  widthClass: 'w-56',
  positionClass: 'absolute z-10 right-0',
  position: 'bottom-end',
  wrapperClass: 'inline-block h-full text-left',
  contentLoading: false,
  label: '',
})

const { t } = useI18n()
const { isPhone } = useBreakpoints()
const inActionBar = inject<boolean>('inActionBar', false)

// BaseDropdownItem renders taller, touch-sized rows inside the sheet
provide('dropdownIsSheet', isPhone)

const containerClasses = computed<string>(() => {
  const baseClass =
    'origin-top-right p-1 rounded-xl border glass-strong focus:outline-hidden'
  return `${baseClass} pointer-events-auto ${props.containerClass}`
})

const [trigger, container, popper] = usePopper({
  placement: props.position,
  strategy: 'fixed',
  modifiers: [{ name: 'offset', options: { offset: [0, 6] } }],
})

// An activator that shows only an icon still needs a name: without a label
// it falls back to "Actions"
const activatorHasText = ref<boolean>(true)

function checkActivatorText(): void {
  activatorHasText.value = !!(trigger.value as HTMLElement | null)?.textContent?.trim()
}

onMounted(checkActivatorText)
onUpdated(checkActivatorText)

const triggerLabel = computed<string | undefined>(() => {
  if (props.label) {
    return props.label
  }

  return activatorHasText.value ? undefined : t('general.actions')
})

async function onClick(): Promise<void> {
  await nextTick()
  requestAnimationFrame(() => {
    popper.value?.update()
  })
}
</script>
