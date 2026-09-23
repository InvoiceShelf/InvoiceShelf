<template>
  <span
    class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-md bg-status-green-bg text-status-green"
    :style="{ backgroundColor: bgColor ?? undefined, color: color ?? undefined }"
  >
    <slot />
  </span>
</template>

<script setup lang="ts">
interface Props {
  bgColor?: string | null
  color?: string | null
}

withDefaults(defineProps<Props>(), {
  bgColor: null,
  color: null,
})
</script>
