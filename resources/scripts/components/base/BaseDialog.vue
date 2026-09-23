<template>
  <TransitionRoot as="template" :show="dialogStore.active" @after-leave="restoreFocus">
    <Dialog
      as="div"
      static
      class="relative z-50"
      :open="dialogStore.active"
      :role="dialogStore.variant === 'danger' ? 'alertdialog' : 'dialog'"
      @close="dialogStore.cancel"
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
              class="
                relative w-full px-5 pt-6 text-start glass-strong rounded-t-2xl safe-drawer
                md:p-6 md:rounded-2xl md:border
              "
              :class="dialogSizeClasses"
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
            </div>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useReturnFocus } from '@/scripts/composables/use-return-focus'
import {
  Dialog,
  DialogOverlay,
  DialogTitle,
  DialogDescription,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'

const dialogStore = useDialogStore()

const { restoreFocus } = useReturnFocus(() => dialogStore.active)

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
