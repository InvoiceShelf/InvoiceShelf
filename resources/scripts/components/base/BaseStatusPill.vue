<template>
  <span
    :class="[toneClass, 'inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-xs font-medium whitespace-nowrap']"
  >
    <span class="w-1.5 h-1.5 rounded-full shrink-0 bg-current" aria-hidden="true" />
    <slot />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * The one status shape: a tinted pill with a dot, on the status token pairs.
 * The document badges (invoice, estimate, paid, recurring) map their
 * statuses to a tone and render through this.
 */
export type StatusTone = 'green' | 'yellow' | 'red' | 'blue' | 'purple' | 'gray'

interface Props {
  tone?: StatusTone
}

const props = withDefaults(defineProps<Props>(), {
  tone: 'gray',
})

const TONES: Record<StatusTone, string> = {
  green: 'bg-status-green-bg text-status-green',
  yellow: 'bg-status-yellow-bg text-status-yellow',
  red: 'bg-status-red-bg text-status-red',
  blue: 'bg-status-blue-bg text-status-blue',
  purple: 'bg-status-purple-bg text-status-purple',
  gray: 'bg-status-gray-bg text-status-gray',
}

const toneClass = computed<string>(() => TONES[props.tone])
</script>
