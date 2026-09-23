<template>
  <div
    v-if="demo"
    class="
      flex flex-wrap items-center justify-center shrink-0 gap-x-3 gap-y-1 px-4 py-2
      text-sm font-medium bg-alert-warning-bg text-alert-warning-text
      border-b border-line-light
    "
    role="status"
  >
    <span class="flex items-center gap-2">
      <BaseIcon name="BeakerIcon" class="w-4 h-4 shrink-0" />
      {{ $t('demo.banner') }}
    </span>
    <span v-if="remaining" class="font-normal">
      {{ remaining.hours > 0
        ? $t('demo.resets_in_hours', { hours: remaining.hours, minutes: remaining.minutes })
        : $t('demo.resets_in_minutes', { minutes: remaining.minutes }) }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { demoState, timeUntilReset } from '@/scripts/utils/demo'

/**
 * Tells visitors of the public demo that everyone shares it and that their
 * changes are wiped at the next reset, counting down to it.
 */
const demo = demoState()
const now = ref<Date>(new Date())
let timer: ReturnType<typeof setInterval> | undefined

const remaining = computed(() => timeUntilReset(demo, now.value))

onMounted(() => {
  if (demo) {
    timer = setInterval(() => {
      now.value = new Date()
    }, 30000)
  }
})

onBeforeUnmount(() => {
  if (timer) {
    clearInterval(timer)
  }
})
</script>
