<script setup lang="ts">
import { computed } from 'vue'
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
    'inline-flex whitespace-nowrap items-center border font-medium focus:outline-hidden focus:ring-2 focus:ring-offset-2',
  tag: 'button',
  disabled: false,
  rounded: false,
  loading: false,
  size: 'md',
  variant: 'primary',
})

const sizeClass = computed<Record<string, boolean>>(() => {
  return {
    'px-2.5 py-1.5 text-xs leading-4 rounded-lg': props.size === 'xs',
    'px-3 py-2 text-sm leading-4 rounded-lg': props.size == 'sm',
    'px-4 py-2 text-sm leading-5 rounded-lg': props.size === 'md',
    'px-4 py-2 text-base leading-6 rounded-lg': props.size === 'lg',
    'px-6 py-3 text-base leading-6 rounded-lg': props.size === 'xl',
  }
})

const placeHolderSize = computed<string>(() => {
  switch (props.size) {
    case 'xs':
      return '32'
    case 'sm':
      return '38'
    case 'md':
      return '42'
    case 'lg':
      return '42'
    case 'xl':
      return '46'
    default:
      return ''
  }
})

const variantClass = computed<Record<string, boolean>>(() => {
  return {
    'border-transparent shadow-xs text-white bg-btn-primary hover:bg-btn-primary-hover focus:ring-primary-500':
      props.variant === 'primary',
    'border-transparent text-primary-700 bg-primary-100 hover:bg-primary-200 focus:ring-primary-500':
      props.variant === 'secondary',
    'border-solid border-primary-500 font-normal transition ease-in-out duration-150 text-primary-500 hover:bg-primary-200 shadow-inner focus:ring-primary-500':
      props.variant == 'primary-outline',
    'border-line-default text-body bg-surface hover:bg-hover focus:ring-primary-500 focus:ring-offset-0':
      props.variant == 'white',
    'border-transparent shadow-xs text-white bg-red-600 hover:bg-red-700 focus:ring-red-500':
      props.variant === 'danger',
    'border-transparent bg-surface-muted border hover:bg-surface-muted/60 focus:ring-gray-500 focus:ring-offset-0':
      props.variant === 'gray',
  }
})

const roundedClass = computed<string>(() => {
  return props.rounded ? '!rounded-full' : ''
})

const iconLeftClass = computed<Record<string, boolean>>(() => {
  return {
    '-ml-0.5 mr-2 h-4 w-4': props.size == 'sm',
    '-ml-1 mr-2 h-5 w-5': props.size === 'md',
    '-ml-1 mr-3 h-5 w-5': props.size === 'lg' || props.size === 'xl',
  }
})

const iconVariantClass = computed<Record<string, boolean>>(() => {
  return {
    'text-white': props.variant === 'primary',
    'text-primary-700': props.variant === 'secondary',
    'text-body': props.variant === 'white',
    'text-subtle': props.variant === 'gray',
  }
})

const iconRightClass = computed<Record<string, boolean>>(() => {
  return {
    'ml-2 -mr-0.5 h-4 w-4': props.size == 'sm',
    'ml-2 -mr-1 h-5 w-5': props.size === 'md',
    'ml-3 -mr-1 h-5 w-5': props.size === 'lg' || props.size === 'xl',
  }
})
</script>

<template>
  <BaseContentPlaceholders
    v-if="contentLoading"
    class="disabled cursor-normal pointer-events-none"
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
    :class="[defaultClass, sizeClass, variantClass, roundedClass]"
  >
    <SpinnerIcon v-if="loading" :class="[iconLeftClass, iconVariantClass]" />

    <slot v-else name="left" :class="iconLeftClass"></slot>

    <slot />

    <slot name="right" :class="[iconRightClass, iconVariantClass]"></slot>
  </BaseCustomTag>
</template>
