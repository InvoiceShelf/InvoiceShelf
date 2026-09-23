<template>
  <TransitionRoot as="template" :show="show">
    <Dialog as="div" class="relative z-40" @close="emit('close')">
      <TransitionChild
        as="template"
        enter="ease-out duration-200"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-150"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-overlay" aria-hidden="true" />
      </TransitionChild>

      <div class="fixed inset-x-0 bottom-0 flex justify-center">
        <TransitionChild
          as="template"
          enter="ease-out duration-250"
          enter-from="translate-y-full"
          enter-to="translate-y-0"
          leave="ease-in duration-200"
          leave-from="translate-y-0"
          leave-to="translate-y-full"
        >
          <DialogPanel
            class="
              flex flex-col w-full max-w-lg max-h-[92dvh] glass-strong
              rounded-t-2xl safe-bottom focus:outline-hidden
            "
          >
            <div class="flex justify-center pt-2.5 pb-1" aria-hidden="true">
              <span class="h-1 w-9 rounded-full bg-line-strong" />
            </div>

            <div
              v-if="title || $slots.header"
              class="flex items-center justify-between gap-3 px-5 pt-1 pb-2"
            >
              <DialogTitle
                v-if="title"
                class="text-section font-semibold text-heading"
              >
                {{ title }}
              </DialogTitle>
              <slot name="header" />
              <BaseIconButton
                icon="XMarkIcon"
                :label="$t('general.close')"
                size="sm"
                class="-mr-1.5 ms-auto"
                @click="emit('close')"
              />
            </div>

            <div class="flex-1 min-h-0 px-2 pb-3 overflow-y-auto overscroll-contain">
              <slot />
            </div>

            <div
              v-if="$slots.footer"
              class="px-4 py-3 border-t border-line-light"
            >
              <slot name="footer" />
            </div>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'

/**
 * A bottom sheet for phones: the screen dims, the panel slides up from the
 * bottom edge and clears the home indicator. Desktop layouts use popovers or
 * BaseModal instead; components that need both switch on useBreakpoints().
 */
interface Props {
  show: boolean
  title?: string
}

withDefaults(defineProps<Props>(), {
  title: '',
})

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>
