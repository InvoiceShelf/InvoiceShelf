<template>
  <Teleport to="body">
    <TransitionRoot appear as="template" :show="show">
      <Dialog
        as="div"
        static
        class="fixed inset-0 z-20 overflow-y-auto"
        :open="show"
        @close="$emit('close')"
      >
        <div
          class="
            flex
            items-end
            justify-center
            min-h-screen
            px-4
            text-center
            sm:block sm:px-2
          "
        >
          <TransitionChild
            as="template"
            enter="ease-out duration-300"
            enter-from="opacity-0"
            enter-to="opacity-100"
            leave="ease-in duration-200"
            leave-from="opacity-100"
            leave-to="opacity-0"
          >
            <DialogOverlay
              class="fixed inset-0 transition-opacity bg-black/50"
            />
          </TransitionChild>

          <!-- This element is to trick the browser into centering the modal contents. -->
          <span
            class="hidden sm:inline-block sm:align-middle sm:h-screen"
            aria-hidden="true"
            >&#8203;</span
          >
          <TransitionChild
            as="template"
            enter="ease-out duration-300"
            enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            enter-to="opacity-100 translate-y-0 sm:scale-100"
            leave="ease-in duration-200"
            leave-from="opacity-100 translate-y-0 sm:scale-100"
            leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          >
            <div
              :class="`inline-block
              align-middle
              bg-surface/95 backdrop-blur-xl backdrop-saturate-150
              rounded-xl border border-line-default
              text-left
              overflow-hidden
              relative
              shadow-2xl
              transition-all
              my-4
              ${modalSize}
              sm:w-full
              border-t-8 border-solid rounded border-primary-500`"
            >
              <div
                v-if="hasHeaderSlot"
                class="
                  flex
                  items-center
                  justify-between
                  px-6
                  py-4
                  text-lg
                  font-medium
                  text-heading
                  border-b border-line-default border-solid
                "
              >
                <slot name="header" />
              </div>

              <slot />

              <slot name="footer" />
            </div>
          </TransitionChild>
        </div>
      </Dialog>
    </TransitionRoot>
  </Teleport>
</template>

<script setup lang="ts">
import { useModalStore } from '@v2/stores/modal.store'
import { computed, watchEffect, useSlots } from 'vue'
import {
  Dialog,
  DialogOverlay,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'

interface Props {
  show?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  show: false,
})

const slots = useSlots()

interface Emits {
  (e: 'close'): void
  (e: 'open', value: boolean): void
}

const emit = defineEmits<Emits>()

const modalStore = useModalStore()

watchEffect(() => {
  if (props.show) {
    emit('open', props.show)
  }
})

const modalSize = computed<string>(() => {
  const size = modalStore.size
  switch (size) {
    case 'sm':
      return 'sm:max-w-2xl w-full'
    case 'md':
      return 'sm:max-w-4xl w-full'
    case 'lg':
      return 'sm:max-w-6xl w-full'

    default:
      return 'sm:max-w-2xl w-full'
  }
})

const hasHeaderSlot = computed<boolean>(() => {
  return !!slots.header
})
</script>
