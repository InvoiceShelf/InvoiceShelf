<template>
  <BaseMultiselect
    v-model="inputValue"
    :options="options"
    label="name"
    value-prop="name"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  modelValue?: string | Record<string, unknown> | number | null
  options?: Array<{ name: string }> | string[]
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  options: () => [],
})

const emit = defineEmits<{ (e: 'update:modelValue', value: unknown): void }>()

const inputValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
</script>
