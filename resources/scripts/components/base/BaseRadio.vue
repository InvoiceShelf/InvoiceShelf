<template>
  <!--
    A native radio: every BaseRadio with the same name forms one group, so the
    arrow keys move between options and screen readers count them. Wrap a set
    in a fieldset or role="radiogroup" with a heading to name the group.
  -->
  <label :for="String(id)" class="relative flex items-start gap-3 cursor-pointer">
    <input
      :id="String(id)"
      v-model="selected"
      v-bind="$attrs"
      type="radio"
      :name="String(name)"
      :value="value"
      class="mt-0.5 shrink-0"
    />
    <span
      :class="[
        selected === value ? checkedStateLabelClass : unCheckedStateLabelClass,
        optionGroupLabelClass,
      ]"
    >
      {{ label }}
    </span>
  </label>
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ inheritAttrs: false })

interface Props {
  id?: string | number
  label?: string
  modelValue?: string | number
  value?: string | number
  name?: string | number
  checkedStateClass?: string
  unCheckedStateClass?: string
  optionGroupActiveStateClass?: string
  checkedStateLabelClass?: string
  unCheckedStateLabelClass?: string
  optionGroupClass?: string
  optionGroupLabelClass?: string
}

const props = withDefaults(defineProps<Props>(), {
  id: () => `radio_${Math.random().toString(36).substr(2, 9)}`,
  label: '',
  modelValue: '',
  value: '',
  name: '',
  checkedStateClass: 'bg-primary-500',
  unCheckedStateClass: 'bg-surface ',
  optionGroupActiveStateClass: 'ring-2 ring-offset-2 ring-primary-500',
  checkedStateLabelClass: 'text-heading font-medium',
  unCheckedStateLabelClass: 'text-heading',
  optionGroupClass:
    'h-4 w-4 mt-0.5 cursor-pointer rounded-full border flex items-center justify-center',
  optionGroupLabelClass: 'block text-sm',
})

interface Emits {
  (e: 'update:modelValue', value: string | number): void
}

const emit = defineEmits<Emits>()

const selected = computed<string | number>({
  get: () => props.modelValue,
  set: (modelValue: string | number) => emit('update:modelValue', modelValue),
})
</script>
