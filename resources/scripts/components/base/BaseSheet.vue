<template>
  <DialogRoot :open="show" @update:open="(open) => !open && emit('close')">
    <DialogPortal>
      <DialogOverlay
        class="
          fixed inset-0 z-40 bg-overlay
          data-[state=open]:animate-overlay-in data-[state=closed]:animate-overlay-out
        "
      >
        <div class="fixed inset-x-0 bottom-0 flex justify-center">
          <DialogContent
            ref="panel"
            :aria-describedby="undefined"
            class="
              flex flex-col w-full max-w-lg max-h-[92dvh] glass-strong
              rounded-t-2xl safe-bottom focus:outline-hidden
              data-[state=open]:animate-sheet-in data-[state=closed]:animate-sheet-out
            "
            @pointer-down-outside="keepForeignLayers"
            @focus-outside="keepForeignLayers"
            @interact-outside="keepForeignLayers"
          >
            <div class="flex justify-center pt-2.5 pb-1" aria-hidden="true">
              <span class="h-1 w-9 rounded-full bg-line-strong" />
            </div>

            <!-- A sheet with no visible title is still named for screen readers -->
            <DialogTitle v-if="!title && label" class="sr-only">{{ label }}</DialogTitle>

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
                class="-me-1.5 ms-auto"
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
          </DialogContent>
        </div>
      </DialogOverlay>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { computed, provide, ref } from 'vue'
import type { ComponentPublicInstance } from 'vue'
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { DIALOG_LAYER, keepForeignLayers } from '@/scripts/utils/dialog-layers'

/**
 * A bottom sheet for phones: the screen dims, the panel slides up from the
 * bottom edge and clears the home indicator. Desktop layouts use popovers or
 * BaseModal instead; components that need both switch on useBreakpoints().
 */
interface Props {
  show: boolean
  title?: string
  /** The sheet's name for screen readers when it shows no title */
  label?: string
}

withDefaults(defineProps<Props>(), {
  title: '',
  label: '',
})

const panel = ref<ComponentPublicInstance | null>(null)

// Date pickers and selects inside open in place, within the focus trap
provide(DIALOG_LAYER, computed(() => (panel.value?.$el as HTMLElement | undefined) ?? null))

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>
