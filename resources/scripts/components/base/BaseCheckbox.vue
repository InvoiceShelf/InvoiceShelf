<template>
  <div class="relative flex items-start">
    <div class="flex items-center h-5">
      <input
        :id="id"
        v-model="checked"
        v-bind="$attrs"
        :disabled="disabled"
        type="checkbox"
        :class="[checkboxClass, disabledClass]"
      />
    </div>
    <div class="ml-3 text-sm">
      <label
        v-if="label"
        :for="id"
        :class="`font-medium ${
          disabled ? 'text-subtle cursor-not-allowed' : 'text-body'
        } cursor-pointer `"
      >
        {{ label }}
      </label>
      <p v-if="description" class="text-muted">{{ description }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  modelValue: {
    type: [Boolean, Array],
    default: false,
  },
  id: {
    type: [Number, String],
    default: () => `check_${Math.random().toString(36).substr(2, 9)}`,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  checkboxClass: {
    type: String,
    default: 'w-4 h-4 border-line-strong rounded cursor-pointer',
  },
  setInitialValue: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

if (props.setInitialValue) {
  emit('update:modelValue', props.modelValue)
}

const checked = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value)
    emit('change', value)
  },
})

const disabledClass = computed(() => {
  if (props.disabled) {
    return 'text-subtle cursor-not-allowed'
  }

  return 'text-primary-600 focus:ring-primary-500'
})
</script>
