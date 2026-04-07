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

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  label?: string
  description?: string
  modelValue?: boolean | unknown[]
  id?: number | string
  disabled?: boolean
  checkboxClass?: string
  setInitialValue?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: '',
  description: '',
  modelValue: false,
  id: () => `check_${Math.random().toString(36).substr(2, 9)}`,
  disabled: false,
  checkboxClass: 'w-4 h-4 border-line-strong rounded cursor-pointer',
  setInitialValue: false,
})

interface Emits {
  (e: 'update:modelValue', value: boolean | unknown[]): void
  (e: 'change', value: boolean | unknown[]): void
}

const emit = defineEmits<Emits>()

if (props.setInitialValue) {
  emit('update:modelValue', props.modelValue)
}

const checked = computed<boolean | unknown[]>({
  get: () => props.modelValue,
  set: (value: boolean | unknown[]) => {
    emit('update:modelValue', value)
    emit('change', value)
  },
})

const disabledClass = computed<string>(() => {
  if (props.disabled) {
    return 'text-subtle cursor-not-allowed'
  }

  return 'text-primary-600 focus:ring-primary-500'
})
</script>
