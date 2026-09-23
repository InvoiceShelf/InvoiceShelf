<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  type?: 'section-title' | 'heading-title'
  /** The heading's place in the page outline; the look comes from `type` */
  level?: 1 | 2 | 3 | 4 | 5 | 6
}

const props = withDefaults(defineProps<Props>(), {
  type: 'section-title',
  level: 2,
})

const typeClass = computed<Record<string, boolean>>(() => ({
  'text-heading text-lg font-medium': props.type === 'heading-title',
  'text-heading text-section font-semibold': props.type === 'section-title',
}))
</script>

<template>
  <component :is="`h${level}`" :class="typeClass">
    <slot />
  </component>
</template>
