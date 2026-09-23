<template>
  <DialogRoot :open="dialogStore.active" @update:open="(open) => !open && dialogStore.cancel()">
    <DialogPortal>
      <DialogOverlay
        class="
          fixed inset-0 z-50 overflow-y-auto bg-overlay
          data-[state=open]:animate-overlay-in data-[state=closed]:animate-overlay-out
        "
      >
        <div class="flex items-end justify-center min-h-full md:items-center md:p-6">
          <DialogContent
            :role="dialogStore.variant === 'danger' ? 'alertdialog' : 'dialog'"
            class="
              relative w-full px-5 pt-6 text-start glass-strong rounded-t-2xl safe-drawer focus:outline-hidden
              md:p-6 md:rounded-2xl md:border
              data-[state=open]:animate-sheet-in data-[state=closed]:animate-sheet-out
              md:data-[state=open]:animate-panel-in md:data-[state=closed]:animate-panel-out
            "
            :class="dialogSizeClasses"
            @pointer-down-outside="keepForeignLayers"
            @focus-outside="keepForeignLayers"
            @interact-outside="keepForeignLayers"
            @open-auto-focus="focusReturn.remember"
            @close-auto-focus="focusReturn.restore"
          >
            <div class="flex items-start gap-4">
              <div
                class="flex items-center justify-center w-10 h-10 rounded-full shrink-0"
                :class="dialogStore.variant === 'danger' ? 'bg-status-red-bg' : 'bg-status-green-bg'"
              >
                <BaseIcon
                  v-if="dialogStore.variant === 'primary'"
                  name="CheckCircleIcon"
                  class="w-5 h-5 text-status-green"
                />
                <BaseIcon
                  v-else
                  name="ExclamationTriangleIcon"
                  class="w-5 h-5 text-status-red"
                  aria-hidden="true"
                />
              </div>
              <div class="min-w-0 pt-1.5">
                <DialogTitle
                  as="h3"
                  class="font-semibold text-section text-heading"
                >
                  {{ dialogStore.title }}
                </DialogTitle>
                <DialogDescription as="p" class="mt-1.5 text-sm text-muted">
                  {{ dialogStore.message }}
                </DialogDescription>
              </div>
            </div>
            <div
              class="flex flex-col-reverse gap-2 mt-6 md:flex-row md:justify-end"
            >
              <base-button
                v-if="!dialogStore.hideNoButton"
                class="justify-center"
                variant="white"
                @click="resolveDialog(false)"
              >
                {{ dialogStore.noLabel }}
              </base-button>

              <base-button
                class="justify-center"
                :variant="dialogStore.variant"
                @click="resolveDialog(true)"
              >
                {{ dialogStore.yesLabel }}
              </base-button>
            </div>
          </DialogContent>
        </div>
      </DialogOverlay>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { keepForeignLayers, useFocusReturn } from '@/scripts/utils/dialog-layers'

const dialogStore = useDialogStore()

// Back to the control that opened it, even from a menu that has since closed
const focusReturn = useFocusReturn()

function resolveDialog(resValue: boolean): void {
  dialogStore.resolve(resValue)
  dialogStore.closeDialog()
}

const dialogSizeClasses = computed<string>(() => {
  const size = dialogStore.size

  switch (size) {
    case 'sm':
      return 'md:max-w-sm'
    case 'md':
      return 'md:max-w-md'
    case 'lg':
      return 'md:max-w-lg'

    default:
      return 'md:max-w-md'
  }
})
</script>
