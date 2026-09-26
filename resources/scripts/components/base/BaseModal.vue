<template>
  <DialogRoot :open="show" @update:open="onOpenChange">
    <DialogPortal>
      <!-- The overlay is also the scroll area, so a tall dialog scrolls and a click beside it closes it -->
      <DialogOverlay
        class="
          fixed inset-0 z-40 overflow-y-auto bg-overlay
          data-[state=open]:animate-overlay-in data-[state=closed]:animate-overlay-out
        "
      >
        <!-- Phones: a sheet from the bottom edge. Wider screens: centred. -->
        <div class="flex items-end justify-center min-h-full md:items-center md:p-6">
          <DialogContent
            ref="panel"
            :aria-describedby="undefined"
            :class="modalSize"
            class="
              relative flex flex-col w-full max-h-[92dvh] text-start glass-strong
              rounded-t-2xl safe-bottom focus:outline-hidden
              md:block md:max-h-none md:rounded-2xl md:border md:pb-0
              data-[state=open]:animate-sheet-in data-[state=closed]:animate-sheet-out
              md:data-[state=open]:animate-panel-in md:data-[state=closed]:animate-panel-out
            "
            @pointer-down-outside="keepForeignLayers"
            @focus-outside="keepForeignLayers"
            @interact-outside="keepForeignLayers"
            @open-auto-focus="focusReturn.remember"
            @close-auto-focus="focusReturn.restore"
          >
            <div class="flex justify-center pt-2.5 md:hidden" aria-hidden="true">
              <span class="h-1 rounded-full w-9 bg-line-strong" />
            </div>

            <!-- The header names the dialog for screen readers -->
            <DialogTitle
              v-if="hasHeaderSlot"
              as="div"
              :class="closable ? 'pe-14 md:pe-16' : ''"
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
              class="absolute top-6 end-3 md:top-3 md:end-4"
              @click="$emit('close')"
            />
          </DialogContent>
        </div>
      </DialogOverlay>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { useModalStore } from '@/scripts/stores/modal.store'
import { computed, provide, ref, watch, useSlots } from 'vue'
import type { ComponentPublicInstance } from 'vue'
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { DIALOG_LAYER, keepForeignLayers, useFocusReturn } from '@/scripts/utils/dialog-layers'

interface Props {
  show?: boolean
  /** A close button in the header, which emits `close` */
  closable?: boolean
  /** Width for a modal opened by its own `show`; otherwise the modal store's size applies */
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  show: false,
  closable: false,
  size: undefined,
})

const slots = useSlots()

interface Emits {
  (e: 'close'): void
  (e: 'open', value: boolean): void
}

const emit = defineEmits<Emits>()

const modalStore = useModalStore()

const panel = ref<ComponentPublicInstance | null>(null)

// Date pickers and selects inside open in place, within the focus trap
provide(DIALOG_LAYER, computed(() => (panel.value?.$el as HTMLElement | undefined) ?? null))

// Back to the control that opened it, even from a menu that has since closed
const focusReturn = useFocusReturn()

// Escape and a click beside the dialog ask to close; the caller decides
function onOpenChange(open: boolean): void {
  if (!open) {
    emit('close')
  }
}

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
  const element = panel.value?.$el as HTMLElement | undefined
  const active = document.activeElement

  if (
    !element
    || !window.matchMedia('(pointer: fine)').matches
    || (active instanceof HTMLElement && active.matches('input, textarea, select, [role=combobox]'))
  ) {
    return
  }

  element.querySelector<HTMLElement>(FIRST_FIELD)?.focus()
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    emit('open', newVal)
    // Once the dialog is in and its fields have rendered
    setTimeout(focusFirstField, 220)
  }
})

const modalSize = computed<string>(() => {
  const size = props.size ?? modalStore.size
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
