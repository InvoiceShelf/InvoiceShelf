<script setup lang="ts">
import { computed } from 'vue'
import type { AiChatMessage } from '@/scripts/types/ai-config'
import { renderMarkdown } from '@/scripts/utils/markdown'

const props = defineProps<{
  message: AiChatMessage
}>()

const isUser = computed(() => props.message.role === 'user')

// Assistant messages get rendered as markdown → sanitized HTML so GFM
// features (code blocks, lists, tables, inline formatting) display as
// the model intended. User messages stay as plain text because the
// user typed them verbatim and markdown syntax would be surprising.
const renderedHtml = computed(() =>
  isUser.value ? '' : renderMarkdown(props.message.content ?? ''),
)
</script>

<template>
  <div
    class="flex"
    :class="isUser ? 'justify-end' : 'justify-start'"
  >
    <div
      class="max-w-[85%] rounded-lg px-4 py-2 text-sm"
      :class="
        isUser
          ? 'bg-primary-500 text-white'
          : 'bg-surface-tertiary text-body'
      "
    >
      <p v-if="isUser" class="whitespace-pre-wrap break-words">
        {{ message.content ?? '' }}
      </p>
      <!-- Assistant output is sanitized via DOMPurify in renderMarkdown
           before it reaches v-html — see resources/scripts/utils/markdown.ts. -->
      <BaseSanitizedHtml v-else class="prose prose-sm max-w-none break-words" :html="renderedHtml" />
    </div>
  </div>
</template>
