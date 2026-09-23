<template>
  <!--
    A button that shows only an icon. The label is required: it is the name a
    screen reader announces, and the tooltip on hover.
  -->
  <button
    type="button"
    :aria-label="label"
    :title="label"
    :class="[sizeClass, toneClass]"
    class="
      inline-flex items-center justify-center shrink-0 rounded-lg transition-colors
      focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus
      disabled:opacity-50 disabled:cursor-not-allowed
    "
  >
    <BaseIcon :name="icon" :class="iconClass" />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  icon: string
  label: string
  size?: 'sm' | 'md'
  tone?: 'default' | 'danger'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  tone: 'default',
})

const sizeClass = computed<string>(() => (props.size === 'sm' ? 'w-8 h-8' : 'w-10 h-10 md:w-9 md:h-9'))

const iconClass = computed<string>(() => (props.size === 'sm' ? 'w-4.5 h-4.5' : 'w-5 h-5'))

const toneClass = computed<string>(() => (
  props.tone === 'danger'
    ? 'text-muted hover:text-danger hover:bg-hover-strong'
    : 'text-muted hover:text-heading hover:bg-hover-strong'
))
</script>
