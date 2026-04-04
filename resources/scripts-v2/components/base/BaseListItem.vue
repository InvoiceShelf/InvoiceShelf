<template>
  <router-link v-bind="$attrs" :class="containerClass">
    <span v-if="hasIconSlot" class="mr-3">
      <slot name="icon" />
    </span>
    <span>{{ title }}</span>
  </router-link>
</template>

<script setup lang="ts">
import { computed, useSlots } from 'vue'

interface Props {
  title?: string
  active: boolean
  index?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  index: null,
})

const slots = useSlots()

const defaultClass =
  'cursor-pointer px-3 py-2 mb-0.5 text-sm font-medium leading-5 flex items-center rounded-lg transition-colors'

const hasIconSlot = computed<boolean>(() => {
  return !!slots.icon
})

const containerClass = computed<string>(() => {
  if (props.active) {
    return `${defaultClass} text-primary-600 bg-primary-50 font-semibold`
  }
  return `${defaultClass} text-body hover:bg-hover hover:text-heading`
})
</script>
