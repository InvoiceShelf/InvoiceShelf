<template>
  <BaseDatePicker v-model="date" />
</template>

<script setup>
import { computed } from 'vue'
import moment from 'moment'
import BaseDatePicker from '@/scripts/components/base/BaseDatePicker.vue'

const props = defineProps({
  modelValue: {
    type: [String, Date],
    default: moment().format('YYYY-MM-DD'),
  },
})

const emit = defineEmits(['update:modelValue'])

const date = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value)
  },
})
</script>
