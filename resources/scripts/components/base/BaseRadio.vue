<template>
  <RadioGroup v-model="selected">
    <RadioGroupLabel class="sr-only"> Privacy setting </RadioGroupLabel>
    <div class="-space-y-px rounded-md">
      <RadioGroupOption
        :id="id"
        v-slot="{ checked, active }"
        as="template"
        :value="value"
        :name="name"
        v-bind="$attrs"
      >
        <div class="relative flex cursor-pointer focus:outline-hidden">
          <span
            :class="[
              checked ? checkedStateClass : unCheckedStateClass,
              active ? optionGroupActiveStateClass : '',
              optionGroupClass,
            ]"
            aria-hidden="true"
          >
            <span class="rounded-full bg-white w-1.5 h-1.5" />
          </span>
          <div class="flex flex-col ml-3">
            <RadioGroupLabel
              as="span"
              :class="[
                checked ? checkedStateLabelClass : unCheckedStateLabelClass,
                optionGroupLabelClass,
              ]"
            >
              {{ label }}
            </RadioGroupLabel>
          </div>
        </div>
      </RadioGroupOption>
    </div>
  </RadioGroup>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RadioGroup, RadioGroupLabel, RadioGroupOption } from '@headlessui/vue'

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
  checkedStateLabelClass: 'text-primary-500 ',
  unCheckedStateLabelClass: 'text-heading',
  optionGroupClass:
    'h-4 w-4 mt-0.5 cursor-pointer rounded-full border flex items-center justify-center',
  optionGroupLabelClass: 'block text-sm font-light',
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
