<template>
  <div class="flex flex-col items-center justify-center px-6 py-14 text-center md:py-20">
    <div class="flex flex-col items-center justify-center mb-5 [&>svg]:h-24 [&>svg]:w-auto">
      <slot></slot>
    </div>
    <p class="font-semibold text-section text-heading">{{ title }}</p>
    <p v-if="description" class="max-w-sm mt-1.5 text-sm text-muted">
      {{ description }}
    </p>
    <div v-if="$slots.actions" class="mt-6">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  title?: string
  description?: string
}

withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
})
</script>
