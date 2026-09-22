<template>
  <div
    class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between md:gap-6"
  >
    <div class="flex flex-col min-w-0">
      <h1 v-if="title" class="font-semibold text-left break-words text-title text-heading">
        {{ title }}
      </h1>
      <slot />
    </div>
    <div
      v-if="$slots.actions"
      class="flex flex-wrap items-center gap-2 shrink-0 md:justify-end md:gap-3 *:ml-0"
    >
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  title?: string
}

withDefaults(defineProps<Props>(), {
  title: '',
})
</script>
