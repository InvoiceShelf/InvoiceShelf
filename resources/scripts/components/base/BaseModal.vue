<template>
  <Teleport to="body">
    <TransitionRoot appear as="template" :show="show" @after-leave="restoreFocus">
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
              @after-enter="focusFirstField"
            >
              <div
                ref="panel"
                :class="modalSize"
                class="
                  relative flex flex-col w-full max-h-[92dvh] text-left glass-strong
                  rounded-t-2xl safe-bottom
                  md:block md:max-h-none md:rounded-2xl md:border md:pb-0
                "
              >
                <div class="flex justify-center pt-2.5 md:hidden" aria-hidden="true">
                  <span class="h-1 rounded-full w-9 bg-line-strong" />
                </div>

                <!-- The header names the dialog for screen readers -->
                <DialogTitle
                  v-if="hasHeaderSlot"
                  as="div"
                  :class="closable ? 'pr-14 md:pr-16' : ''"
                  class="
                    flex items-center justify-between shrink-0 gap-3 px-5 py-3.5 md:px-6 md:py-4
                    font-semibold text-section text-heading border-b border-line-light
                  "
                >
                  <slot name="header" />
                </DialogTitle>

                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain md:overflow-visible">
                  <slot />
                </div>

                <slot name="footer" />

                <!--
                  Last in the DOM but drawn in the header's corner: focus
                  opens on the first field, and the close button is outside
                  the title, so the dialog's name is the title alone.
                -->
                <BaseIconButton
                  v-if="closable && hasHeaderSlot"
                  icon="XMarkIcon"
                  :label="$t('general.close')"
                  class="absolute top-6 right-3 md:top-3 md:right-4"
                  @click="$emit('close')"
                />
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
import { useReturnFocus } from '@/scripts/composables/use-return-focus'
import { computed, ref, watch, useSlots } from 'vue'
import {
  Dialog,
  DialogOverlay,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'

interface Props {
  show?: boolean
  /** A close button in the header, which emits `close` */
  closable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  show: false,
  closable: false,
})

const slots = useSlots()

interface Emits {
  (e: 'close'): void
  (e: 'open', value: boolean): void
}

const emit = defineEmits<Emits>()

const modalStore = useModalStore()

const { restoreFocus } = useReturnFocus(() => props.show)

const panel = ref<HTMLElement | null>(null)

// Text fields only: a combobox or date picker would open its list on focus
const FIRST_FIELD = [
  'input:not([type]):not([role]):not(.flatpickr-input)',
  'input[type=text]:not([role]):not(.flatpickr-input)',
  'input[type=email]',
  'input[type=number]',
  'input[type=password]',
  'textarea',
].map((selector) => `${selector}:not([disabled]):not([readonly])`).join(', ')

/**
 * A form's fields often render after the dialog has placed focus, which then
 * lands on the first button. Once the dialog is in, move it to the first
 * text field, unless the user has already moved it into a field. Not on
 * touch screens, where focusing a field raises the on-screen keyboard.
 */
function focusFirstField(): void {
  const active = document.activeElement

  if (
    !panel.value
    || !window.matchMedia('(pointer: fine)').matches
    || (active instanceof HTMLElement && active.matches('input, textarea, select, [role=combobox]'))
  ) {
    return
  }

  panel.value.querySelector<HTMLElement>(FIRST_FIELD)?.focus()
}

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
