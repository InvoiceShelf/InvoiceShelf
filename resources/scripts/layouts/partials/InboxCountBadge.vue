<template>
  <span
    v-if="count > 0"
    class="absolute top-0 end-0 min-w-4.5 h-4.5 px-1 flex items-center justify-center rounded-full bg-danger text-[10px] font-semibold leading-none text-white ring-2 ring-surface"
    aria-hidden="true"
  >
    {{ count > 99 ? '99+' : count }}
  </span>
</template>

<script setup lang="ts">
defineProps<{ count: number }>()
</script>
