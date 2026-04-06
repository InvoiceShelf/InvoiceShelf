<template>
  <div class="relative" :class="wrapperClass">
    <BaseContentPlaceholders
      v-if="contentLoading"
      class="disabled cursor-normal pointer-events-none"
    >
      <BaseContentPlaceholdersBox
        :rounded="true"
        class="w-14"
        style="height: 42px"
      />
    </BaseContentPlaceholders>
    <Menu v-else>
      <span ref="trigger" class="inline-flex">
        <MenuButton class="focus:outline-hidden" @click="onClick">
          <slot name="activator" />
        </MenuButton>
      </span>

      <Teleport to="body">
        <div
          ref="container"
          class="fixed top-0 left-0 z-10"
          :class="[widthClass, !contentLoading ? 'pointer-events-none' : '']"
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
              <div class="py-1">
                <slot />
              </div>
            </MenuItems>
          </transition>
        </div>
      </Teleport>
    </Menu>
  </div>
</template>

<script setup lang="ts">
import { Menu, MenuButton, MenuItems } from '@headlessui/vue'
import { computed, nextTick } from 'vue'
import { usePopper } from '@v2/composables/use-popper'
import type { Placement } from '@popperjs/core'

interface Props {
  containerClass?: string
  widthClass?: string
  positionClass?: string
  position?: Placement
  wrapperClass?: string
  contentLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  containerClass: '',
  widthClass: 'w-56',
  positionClass: 'absolute z-10 right-0',
  position: 'bottom-end',
  wrapperClass: 'inline-block h-full text-left',
  contentLoading: false,
})

const containerClasses = computed<string>(() => {
  const baseClass = `origin-top-right rounded-xl shadow-xl bg-surface/80 backdrop-blur-xl border border-white/15 divide-y divide-line-light focus:outline-hidden`
  return `${baseClass} pointer-events-auto ${props.containerClass}`
})

const [trigger, container, popper] = usePopper({
  placement: props.position,
  strategy: 'fixed',
  modifiers: [{ name: 'offset', options: { offset: [0, 10] } }],
})

async function onClick(): Promise<void> {
  await nextTick()
  requestAnimationFrame(() => {
    popper.value?.update()
  })
}
</script>
