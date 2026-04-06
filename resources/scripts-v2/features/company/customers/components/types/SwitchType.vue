<template>
  <BaseSwitch v-model="inputValue" />
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ modelValue?: string | number | boolean | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: number): void }>()

const inputValue = computed({
  get: () => props.modelValue === 1,
  set: (value: boolean) => emit('update:modelValue', value ? 1 : 0),
})
</script>
