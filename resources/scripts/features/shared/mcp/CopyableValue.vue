<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'

/**
 * A value to paste somewhere else, such as a server address or a command,
 * with a button that copies it. Long values scroll inside the box rather
 * than widening the page.
 */
const props = defineProps<{
  value: string
  label: string
  multiline?: boolean
}>()

const { t } = useI18n()
const notificationStore = useNotificationStore()
const box = ref<HTMLElement | null>(null)

async function copy(): Promise<void> {
  try {
    await navigator.clipboard.writeText(props.value)
  } catch {
    // Without clipboard access, select the text so the user can copy it.
    const selection = window.getSelection()
    if (box.value && selection) {
      const range = document.createRange()
      range.selectNodeContents(box.value)
      selection.removeAllRanges()
      selection.addRange(range)
    }

    return
  }

  notificationStore.showNotification({ type: 'success', message: t('mcp.copied') })
}
</script>

<template>
  <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
    <pre
      ref="box"
      dir="ltr"
      tabindex="0"
      :aria-label="label"
      class="
        min-w-0
        w-full
        sm:flex-1
        overflow-x-auto
        rounded-lg
        border
        border-line-default
        bg-surface-secondary
        px-3
        py-2
        text-start
        font-mono
        text-sm
        text-heading
        focus:outline-hidden
        focus-visible:ring-2
        focus-visible:ring-focus
      "
      :class="multiline ? 'whitespace-pre' : 'whitespace-nowrap'"
    >{{ value }}</pre>

    <BaseButton
      variant="primary-outline"
      size="sm"
      class="self-start shrink-0 sm:mt-0.5"
      :aria-label="`${$t('general.copy_to_clipboard')}: ${label}`"
      @click="copy"
    >
      <template #left="slotProps">
        <BaseIcon name="ClipboardDocumentIcon" :class="slotProps.class" />
      </template>
      {{ $t('general.copy_to_clipboard') }}
    </BaseButton>
  </div>
</template>
