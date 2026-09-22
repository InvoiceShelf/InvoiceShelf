<template>
  <Teleport to="body">
    <TransitionRoot appear as="template" :show="show">
      <Dialog
        as="div"
        static
        class="relative z-40"
        :open="show"
        @close="$emit('close')"
      >
        <TransitionChild
          as="template"
          enter="ease-out duration-200"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="ease-in duration-150"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-overlay" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <!-- Phones: a sheet from the bottom edge. Wider screens: centred. -->
          <div class="flex items-end justify-center min-h-full md:items-center md:p-6">
            <TransitionChild
              as="template"
              enter="ease-out duration-200"
              enter-from="translate-y-full md:translate-y-2 md:opacity-0 md:scale-[0.98]"
              enter-to="translate-y-0 md:opacity-100 md:scale-100"
              leave="ease-in duration-150"
              leave-from="translate-y-0 md:opacity-100 md:scale-100"
              leave-to="translate-y-full md:translate-y-2 md:opacity-0 md:scale-[0.98]"
            >
              <div
                :class="modalSize"
                class="
                  relative flex flex-col w-full max-h-[92dvh] text-left shadow-lg bg-surface
                  rounded-t-2xl safe-bottom
                  md:block md:max-h-none md:rounded-2xl md:border md:border-line-light md:pb-0
                "
              >
                <div class="flex justify-center pt-2.5 md:hidden" aria-hidden="true">
                  <span class="h-1 rounded-full w-9 bg-line-strong" />
                </div>

                <div
                  v-if="hasHeaderSlot"
                  class="
                    flex items-center justify-between shrink-0 gap-3 px-5 py-3.5 md:px-6 md:py-4
                    font-semibold text-section text-heading border-b border-line-light
                  "
                >
                  <slot name="header" />
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain md:overflow-visible">
                  <slot />
                </div>

                <slot name="footer" />
              </div>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </Teleport>
</template>

<script setup lang="ts">
import { useModalStore } from '@/scripts/stores/modal.store'
import { computed, watch, useSlots } from 'vue'
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

watch(() => props.show, (newVal) => {
  if (newVal) {
    emit('open', newVal)
  }
})

const modalSize = computed<string>(() => {
  const size = modalStore.size
  switch (size) {
    case 'sm':
      return 'md:max-w-2xl'
    case 'md':
      return 'md:max-w-4xl'
    case 'lg':
      return 'md:max-w-6xl'

    default:
      return 'md:max-w-2xl'
  }
})

const hasHeaderSlot = computed<boolean>(() => {
  return !!slots.header
})
</script>
