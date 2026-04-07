<template>
  <div class="flex flex-col items-center justify-center mt-16">
    <div class="flex flex-col items-center justify-center">
      <slot></slot>
    </div>
    <div class="mt-2">
      <label class="font-medium">{{ title }}</label>
    </div>
    <div class="mt-2">
      <label class="text-muted">
        {{ description }}
      </label>
    </div>
    <div class="mt-6">
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
