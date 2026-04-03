<template>
  <router-link v-bind="$attrs" :class="containerClass">
    <span v-if="hasIconSlot" class="mr-3">
      <slot name="icon" />
    </span>
    <span>{{ title }}</span>
  </router-link>
</template>

<script>
import { ref, computed } from 'vue'

export default {
  name: 'ListItem',
  props: {
    title: {
      type: String,
      required: false,
      default: '',
    },
    active: {
      type: Boolean,
      required: true,
    },
    index: {
      type: Number,
      default: null,
    },
  },
  setup(props, { slots }) {
    const defaultClass = `cursor-pointer px-3 py-2 mb-0.5 text-sm font-medium leading-5 flex items-center rounded-lg transition-colors`
    let hasIconSlot = computed(() => {
      return !!slots.icon
    })
    let containerClass = computed(() => {
      if (props.active) return `${defaultClass} text-primary-600 bg-primary-50 font-semibold`
      else return `${defaultClass} text-gray-600 hover:bg-gray-50 hover:text-gray-900`
    })
    return {
      hasIconSlot,
      containerClass,
    }
  },
}
</script>
