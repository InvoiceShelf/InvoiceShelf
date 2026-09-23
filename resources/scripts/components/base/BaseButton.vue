<script setup lang="ts">
import { computed, inject, ref, useAttrs, useSlots } from 'vue'
import type { Ref } from 'vue'
import SpinnerIcon from '@/scripts/components/icons/SpinnerIcon.vue'

type ButtonSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type ButtonVariant =
  | 'primary'
  | 'secondary'
  | 'primary-outline'
  | 'white'
  | 'danger'
  | 'gray'

interface Props {
  contentLoading?: boolean
  defaultClass?: string
  tag?: string
  disabled?: boolean
  rounded?: boolean
  loading?: boolean
  size?: ButtonSize
  variant?: ButtonVariant
}

const props = withDefaults(defineProps<Props>(), {
  contentLoading: false,
  defaultClass:
    'inline-flex items-center justify-center whitespace-nowrap border font-medium transition-colors focus:outline-hidden focus-visible:ring-3 focus-visible:ring-focus disabled:opacity-55 disabled:cursor-not-allowed',
  tag: 'button',
  disabled: false,
  rounded: false,
  loading: false,
  size: 'md',
  variant: 'primary',
})

const slots = useSlots()
const attrs = useAttrs()

// Inside a page header on a phone, a button with an icon shows only the icon;
// its label stays in the markup for screen readers. A form's submit button
// keeps its label: "Save" is not something to guess from an icon.
const inCompactHeader = inject<Ref<boolean>>('pageHeaderCompact', ref(false))

const iconOnly = computed<boolean>(() => {
  return inCompactHeader.value
    && attrs.type !== 'submit'
    && (!!slots.left || !!slots.right)
})

// Phones get 44px touch targets from md size up; wider screens stay compact.
const sizeClass = computed<Record<string, boolean> | string>(() => {
  if (iconOnly.value) {
    return 'w-10 h-10 p-0 rounded-xl'
  }

  return {
    'h-7 px-2.5 text-xs rounded-md': props.size === 'xs',
    'h-8 px-3 text-sm rounded-lg': props.size == 'sm',
    'h-11 md:h-9 px-3.5 text-sm rounded-lg': props.size === 'md',
    'h-11 md:h-10 px-4 text-base md:text-sm rounded-lg': props.size === 'lg',
    'h-12 px-5 text-base rounded-lg': props.size === 'xl',
  }
})

const placeHolderSize = computed<string>(() => {
  switch (props.size) {
    case 'xs':
      return '28'
    case 'sm':
      return '32'
    case 'md':
      return '36'
    case 'lg':
      return '40'
    case 'xl':
      return '48'
    default:
      return ''
  }
})

const variantClass = computed<Record<string, boolean>>(() => {
  return {
    'border-transparent shadow-xs bg-btn-primary text-on-primary hover:bg-btn-primary-hover':
      props.variant === 'primary',
    'border-transparent bg-primary-50 text-primary-700 hover:bg-primary-100':
      props.variant === 'secondary',
    'border-line-default bg-surface text-heading shadow-xs hover:bg-hover hover:border-line-strong':
      props.variant == 'primary-outline',
    'border-line-default bg-surface text-body shadow-xs hover:bg-hover hover:text-heading':
      props.variant == 'white',
    'border-transparent shadow-xs bg-danger text-white hover:bg-danger-hover':
      props.variant === 'danger',
    'border-transparent bg-surface-muted text-body hover:bg-hover-strong':
      props.variant === 'gray',
  }
})

const roundedClass = computed<string>(() => {
  return props.rounded ? '!rounded-full' : ''
})

const iconLeftClass = computed<Record<string, boolean> | string>(() => {
  if (iconOnly.value) {
    return 'h-5 w-5'
  }

  return {
    '-ml-0.5 mr-1.5 h-4 w-4': props.size == 'sm' || props.size === 'xs',
    '-ml-1 mr-2 h-4.5 w-4.5': props.size === 'md',
    '-ml-1 mr-2 h-5 w-5': props.size === 'lg' || props.size === 'xl',
  }
})

const iconVariantClass = computed<Record<string, boolean>>(() => {
  return {
    'text-on-primary': props.variant === 'primary' || props.variant === 'danger',
    'text-primary-600': props.variant === 'secondary',
    'text-muted': props.variant === 'white' || props.variant === 'primary-outline' || props.variant === 'gray',
  }
})

const iconRightClass = computed<Record<string, boolean> | string>(() => {
  if (iconOnly.value) {
    return 'h-5 w-5'
  }

  return {
    'ml-1.5 -mr-0.5 h-4 w-4': props.size == 'sm' || props.size === 'xs',
    'ml-2 -mr-1 h-4.5 w-4.5': props.size === 'md',
    'ml-2 -mr-1 h-5 w-5': props.size === 'lg' || props.size === 'xl',
  }
})
</script>

<template>
  <BaseContentPlaceholders
    v-if="contentLoading"
    class="disabled pointer-events-none"
  >
    <BaseContentPlaceholdersBox
      :rounded="true"
      style="width: 96px"
      :style="`height: ${placeHolderSize}px;`"
    />
  </BaseContentPlaceholders>

  <BaseCustomTag
    v-else
    :tag="tag"
    :disabled="disabled"
    :data-icon-only="iconOnly ? '' : undefined"
    :class="[defaultClass, sizeClass, variantClass, roundedClass]"
  >
    <SpinnerIcon v-if="loading" :class="[iconLeftClass, iconVariantClass]" />

    <slot v-else name="left" :class="[iconLeftClass, iconVariantClass]"></slot>

    <span v-if="iconOnly" class="sr-only"><slot /></span>
    <slot v-else />

    <slot name="right" :class="[iconRightClass, iconVariantClass]"></slot>
  </BaseCustomTag>
</template>
