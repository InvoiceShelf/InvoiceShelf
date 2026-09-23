<template>
  <!--
    A native switch: a button with role="switch". Headless UI's Switch set its
    own aria-labelledby last, which wiped out any label passed in.
  -->
  <div class="flex flex-row items-start" :class="$attrs.class" :style="$attrs.style as StyleValue">
    <label v-if="labelLeft" :id="labelId" :for="switchId" class="me-4 cursor-pointer">
      {{ labelLeft }}
    </label>

    <button
      :id="switchId"
      type="button"
      role="switch"
      :aria-checked="enabled"
      :class="enabled ? 'bg-btn-primary' : 'bg-control-border'"
      class="
        relative
        inline-flex
        items-center
        h-6
        transition-colors
        rounded-full
        w-11
        shrink-0 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus
        disabled:opacity-55 disabled:cursor-not-allowed
      "
      v-bind="{ ...(labelLeft || labelRight ? { 'aria-labelledby': labelId } : fieldAttrs), ...controlAttrs }"
      @click="enabled = !enabled"
    >
      <span
        :class="enabled ? 'translate-x-6 rtl:-translate-x-6' : 'translate-x-1 rtl:-translate-x-1'"
        class="
          inline-block
          w-4
          h-4
          transition-transform
          bg-on-primary
          rounded-full
          shadow-xs
        "
      />
    </button>

    <label v-if="labelRight" :id="labelId" :for="switchId" class="ms-4 cursor-pointer">
      {{ labelRight }}
    </label>
  </div>
</template>

<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue'
import type { StyleValue } from 'vue'
import { useFormField } from '@/scripts/composables/use-form-field'

interface Props {
  labelLeft?: string
  labelRight?: string
  modelValue?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  labelLeft: '',
  labelRight: '',
  modelValue: false,
})

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

const emit = defineEmits<Emits>()

const switchId = `switch-${useId()}`
const labelId = `${switchId}-label`

// With no label of its own, the switch is named by the surrounding group's label
const { attrs: fieldAttrs } = useFormField({ labelledBy: true })

// Layout classes style the wrapper; everything else (aria-*, id, disabled)
// belongs to the switch itself
defineOptions({ inheritAttrs: false })

const attrs = useAttrs()

const controlAttrs = computed<Record<string, unknown>>(() => {
  const { class: _class, style: _style, ...rest } = attrs

  return rest
})

const enabled = computed<boolean>({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
</script>
