<template>
  <BaseDatePicker v-model="date" />
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ modelValue?: string | Date | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string | Date | null): void }>()

const date = computed({
  get: () => props.modelValue ?? null,
  set: (value) => emit('update:modelValue', value),
})
</script>
