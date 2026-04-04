<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  layout?: 'one-column' | 'two-column'
}

const props = withDefaults(defineProps<Props>(), {
  layout: 'two-column',
})

const formLayout = computed<string>(() => {
  if (props.layout === 'two-column') {
    return 'grid gap-y-6 gap-x-4 grid-cols-1 md:grid-cols-2'
  }
  return 'grid gap-y-6 gap-x-4 grid-cols-1'
})
</script>

<template>
  <div :class="formLayout">
    <slot />
  </div>
</template>
