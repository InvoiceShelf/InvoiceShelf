<template>
  <div :class="containerClasses" class="relative w-full text-start">
    <ContentPlaceholder v-if="contentLoading">
      <ContentPlaceholderText :lines="1" :class="contentLoadClass" />
    </ContentPlaceholder>
    <div
      v-else-if="label"
      :class="labelClasses"
      class="flex items-center justify-between gap-2 text-sm font-medium text-heading"
    >
      <label :id="ids.labelId" :for="ids.controlId">
        {{ label }}
        <span v-show="required" class="text-danger" aria-hidden="true">*</span>
      </label>
      <slot v-if="hasRightLabelSlot" name="labelRight" />
      <BaseIcon
        v-if="tooltip"
        v-tooltip="{ content: tooltip }"
        name="InformationCircleIcon"
        class="w-4 h-4 cursor-help text-subtle hover:text-body"
      />
    </div>
    <div :class="inputContainerClasses">
      <slot></slot>
      <!-- The tooltip's text, for keyboard and screen reader users -->
      <span v-if="tooltip" :id="ids.hintId" class="sr-only">{{ tooltip }}</span>
      <span v-if="helpText" :id="ids.helpId" class="mt-1.5 text-xs text-muted">
        {{ helpText }}
      </span>
      <span v-if="error" :id="ids.errorId" class="block mt-1.5 text-xs font-medium text-danger">
        {{ error }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { ContentPlaceholder, ContentPlaceholderText } from '../layout'
import { provideFormField } from '@/scripts/composables/use-form-field'

interface Props {
  contentLoading?: boolean
  contentLoadClass?: string
  label?: string
  variant?: 'vertical' | 'horizontal'
  error?: string | boolean | null
  required?: boolean
  tooltip?: string | null
  helpText?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  contentLoading: false,
  contentLoadClass: 'w-16 h-5',
  label: '',
  variant: 'vertical',
  error: null,
  required: false,
  tooltip: null,
  helpText: null,
})

const containerClasses = computed<string>(() => {
  if (props.variant === 'horizontal') {
    return 'grid md:grid-cols-12 items-center'
  }
  return ''
})

const labelClasses = computed<string>(() => {
  if (props.variant === 'horizontal') {
    return 'relative pe-0 pt-1 me-3 text-sm md:col-span-4 md:text-end mb-1  md:mb-0'
  }
  return ''
})

const inputContainerClasses = computed<string>(() => {
  if (props.variant === 'horizontal') {
    return 'md:col-span-8 md:col-start-5 md:col-ends-12'
  }
  return 'flex flex-col mt-1.5'
})

const slots = useSlots()

// Label, hint, help and error are tied to the control inside by id
const ids = provideFormField({
  hasLabel: computed(() => !!props.label),
  hasHelp: computed(() => !!props.helpText),
  hasHint: computed(() => !!props.tooltip),
  hasError: computed(() => !!props.error),
  required: computed(() => props.required),
})

const hasRightLabelSlot = computed<boolean>(() => {
  return !!slots.labelRight
})
</script>
