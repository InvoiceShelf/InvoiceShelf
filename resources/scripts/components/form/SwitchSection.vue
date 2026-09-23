<template>
  <div class="py-4 flex items-center justify-between">
    <div class="flex flex-col">
      <!-- Names the switch without toggling it when clicked -->
      <p
        :id="`${id}-label`"
        class="p-0 mb-1 text-sm leading-snug text-heading font-medium"
      >
        {{ title }}
      </p>
      <p :id="`${id}-description`" class="text-sm text-muted">
        {{ description }}
      </p>
    </div>
    <SwitchRoot
      :disabled="disabled"
      :model-value="modelValue"
      :aria-labelledby="`${id}-label`"
      :aria-describedby="description ? `${id}-description` : undefined"
      :class="[
        modelValue ? 'bg-primary-500' : 'bg-control-border',
        'ms-4 relative inline-flex shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-focus',
      ]"
      @update:model-value="onUpdate"
    >
      <SwitchThumb
        :class="[
          modelValue ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0',
          'inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition ease-in-out duration-200',
        ]"
      />
    </SwitchRoot>
  </div>
</template>

<script setup lang="ts">
import { useId } from 'vue'
import { SwitchRoot, SwitchThumb } from 'reka-ui'

interface Props {
  title: string
  description?: string
  modelValue?: boolean
  disabled?: boolean
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

withDefaults(defineProps<Props>(), {
  description: '',
  modelValue: false,
  disabled: false,
})

const emit = defineEmits<Emits>()

const id = `switch-${useId()}`

function onUpdate(value: boolean): void {
  emit('update:modelValue', value)
}
</script>
