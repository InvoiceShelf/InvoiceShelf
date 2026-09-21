<template>
  <BaseTimePicker v-model="date" />
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ modelValue?: string | Date | Record<string, string> | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string | Date | Record<string, string> | null): void }>()

const date = computed({
  get: () => props.modelValue ?? null,
  set: (value) => emit('update:modelValue', value),
})
</script>
