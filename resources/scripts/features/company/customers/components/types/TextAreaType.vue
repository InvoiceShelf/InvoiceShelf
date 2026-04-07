<template>
  <BaseTextarea v-model="inputValue" rows="2" />
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ modelValue?: string | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string | null): void }>()

const inputValue = computed({
  get: () => props.modelValue ?? null,
  set: (value) => emit('update:modelValue', value),
})
</script>
