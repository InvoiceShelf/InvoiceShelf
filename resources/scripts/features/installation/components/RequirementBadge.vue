<template>
  <span
    :class="[
      'inline-flex items-center justify-center h-6 w-6 rounded-full',
      ok
        ? 'bg-green-100 text-green-600'
        : 'bg-red-100 text-red-600',
    ]"
    :aria-label="ok ? 'OK' : 'Missing'"
  >
    <BaseIcon
      :name="ok ? 'CheckIcon' : 'XMarkIcon'"
      class="h-4 w-4"
    />
  </span>
</template>

<script setup lang="ts">
defineProps<{
  ok: boolean
}>()
</script>
