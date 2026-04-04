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
      <MenuButton ref="trigger" class="focus:outline-hidden" @click="onClick">
        <slot name="activator" />
      </MenuButton>

      <div ref="container" class="z-10" :class="widthClass">
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
    </Menu>
  </div>
</template>

<script setup lang="ts">
import { Menu, MenuButton, MenuItems } from '@headlessui/vue'
import { computed, ref } from 'vue'
import { usePopper } from '@v2/composables/use-popper'

interface Props {
  containerClass?: string
  widthClass?: string
  positionClass?: string
  position?: string
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
  return `${baseClass} ${props.containerClass}`
})

const [trigger, container, popper] = usePopper({
  placement: 'bottom-end',
  strategy: 'fixed',
  modifiers: [{ name: 'offset', options: { offset: [0, 10] } }],
})

function onClick(): void {
  popper.value.update()
}
</script>
