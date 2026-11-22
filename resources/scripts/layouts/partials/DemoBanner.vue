<template>
  <div
    v-if="demo"
    class="
      shrink-0 text-sm font-medium bg-alert-warning-bg text-alert-warning-text
      border-b border-line-light
    "
    role="status"
  >
    <!-- Phones: one line, with the full notice a tap away -->
    <template v-if="isPhone">
      <button
        type="button"
        class="flex items-center w-full gap-2 px-4 py-2 text-start"
        :aria-expanded="expanded"
        :aria-controls="detailsId"
        :aria-label="expanded ? $t('demo.hide_details') : $t('demo.show_details')"
        @click="expanded = !expanded"
      >
        <BaseIcon name="BeakerIcon" class="w-4 h-4 shrink-0" />
        <span class="flex-1 min-w-0 truncate">{{ shortLabel }}</span>
        <BaseIcon
          name="ChevronDownIcon"
          class="w-4 h-4 shrink-0 transition-transform"
          :class="expanded ? 'rotate-180' : ''"
        />
      </button>
      <p
        v-show="expanded"
        :id="detailsId"
        class="px-4 pb-2.5 -mt-0.5 font-normal ps-10"
      >
        {{ $t('demo.banner') }}
      </p>
    </template>

    <div
      v-else
      class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2"
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
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { demoState, timeUntilReset } from '@/scripts/utils/demo'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'

/**
 * Tells visitors of the public demo that everyone shares it and that their
 * changes are wiped at the next reset, counting down to it. On a phone the
 * full notice would take three lines above every page, so it folds into one
 * that opens on a tap.
 */
const { t } = useI18n()
const { isPhone } = useBreakpoints()
const demo = demoState()
const now = ref<Date>(new Date())
const expanded = ref<boolean>(false)
const detailsId = useId()
let timer: ReturnType<typeof setInterval> | undefined

const remaining = computed(() => timeUntilReset(demo, now.value))

const shortLabel = computed<string>(() => {
  if (!remaining.value) {
    return t('demo.short')
  }

  return remaining.value.hours > 0
    ? t('demo.short_resets_in_hours', { hours: remaining.value.hours, minutes: remaining.value.minutes })
    : t('demo.short_resets_in_minutes', { minutes: remaining.value.minutes })
})

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
