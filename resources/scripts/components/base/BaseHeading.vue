<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  type?: 'section-title' | 'heading-title'
}

const props = withDefaults(defineProps<Props>(), {
  type: 'section-title',
})

const typeClass = computed<Record<string, boolean>>(() => ({
  'text-heading text-lg font-medium': props.type === 'heading-title',
  'text-muted uppercase text-base': props.type === 'section-title',
}))
</script>

<template>
  <h6 :class="typeClass">
    <slot />
  </h6>
</template>
