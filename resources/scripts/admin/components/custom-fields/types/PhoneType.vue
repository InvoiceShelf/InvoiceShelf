<template>
  <BaseInput v-model="inputValue" type="tel" />
</template>

<script setup>
import { computed } from 'vue'
import BaseInput from '@/scripts/components/base/BaseInput.vue'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

const inputValue = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value)
  },
})
</script>
