<script setup lang="ts">
import { computed } from 'vue'
import { sanitizeHtml } from '@/scripts/utils/markdown'

const props = withDefaults(
  defineProps<{
    /** Raw HTML to render. Always DOMPurify-sanitized before it is bound. */
    html?: string | null
  }>(),
  { html: '' }
)

const clean = computed(() => sanitizeHtml(props.html))
</script>

<template>
  <!--
    The single, audited v-html sink in the app. `clean` is DOMPurify-sanitized
    above, so any HTML (server-, registry-, or update-server-provided) rendered
    through this component is safe. Do NOT use v-html anywhere else — route it
    here instead. This is the only place vue/no-v-html is disabled.
  -->
  <!-- eslint-disable-next-line vue/no-v-html -->
  <div v-html="clean" />
</template>
