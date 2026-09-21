<template>
  <BaseInput v-model="inputValue" type="number" />
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ modelValue?: string | number | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string | number | null): void }>()

const inputValue = computed({
  get: () => props.modelValue ?? null,
  set: (value) => emit('update:modelValue', value),
})
</script>
