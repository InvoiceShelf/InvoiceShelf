<template>
  <div :class="containerClasses" class="relative w-full text-left">
    <ContentPlaceholder v-if="contentLoading">
      <ContentPlaceholderText :lines="1" :class="contentLoadClass" />
    </ContentPlaceholder>
    <label
      v-else-if="label"
      :class="labelClasses"
      class="
        flex
        text-sm
        not-italic
        items-center
        font-medium
        text-heading
        whitespace-nowrap
        justify-between
      "
    >
      <div>
        {{ label }}
        <span v-show="required" class="text-sm text-red-500"> * </span>
      </div>
      <slot v-if="hasRightLabelSlot" name="labelRight" />
      <BaseIcon
        v-if="tooltip"
        v-tooltip="{ content: tooltip }"
        name="InformationCircleIcon"
        class="h-4 text-subtle cursor-pointer hover:text-body"
      />
    </label>
    <div :class="inputContainerClasses">
      <slot></slot>
      <span v-if="helpText" class="text-muted text-xs mt-1 font-light">
        {{ helpText }}
      </span>
      <span v-if="error" class="block mt-0.5 text-sm text-red-500">
        {{ error }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { ContentPlaceholder, ContentPlaceholderText } from '../layout'

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
    return 'relative pr-0 pt-1 mr-3 text-sm md:col-span-4 md:text-right mb-1  md:mb-0'
  }
  return ''
})

const inputContainerClasses = computed<string>(() => {
  if (props.variant === 'horizontal') {
    return 'md:col-span-8 md:col-start-5 md:col-ends-12'
  }
  return 'flex flex-col mt-1'
})

const slots = useSlots()

const hasRightLabelSlot = computed<boolean>(() => {
  return !!slots.labelRight
})
</script>
