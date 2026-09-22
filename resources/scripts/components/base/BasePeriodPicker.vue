<template>
  <div :class="block ? 'flex w-full' : 'inline-flex max-w-full'">
    <button
      ref="trigger"
      type="button"
      :class="[
        'flex items-center gap-2 min-w-0 px-3 text-sm font-medium transition-colors border rounded-xl',
        'bg-surface/80 border-line-default text-heading hover:border-line-strong',
        'focus:outline-hidden focus-visible:ring-3 focus-visible:ring-focus',
        block ? 'w-full h-11' : 'h-10 md:h-9',
        open ? 'border-line-strong' : '',
      ]"
      aria-haspopup="dialog"
      :aria-expanded="open"
      :aria-label="`${$t('dateRange.period')}: ${label}`"
      @click="open ? close() : (open = true)"
    >
      <BaseIcon name="CalendarDaysIcon" class="w-4.5 h-4.5 text-muted shrink-0" />
      <span class="truncate" :class="block ? 'flex-1 text-left' : ''">{{ label }}</span>
      <BaseIcon
        name="ChevronDownIcon"
        class="w-4 h-4 transition-transform text-subtle shrink-0"
        :class="open ? 'rotate-180' : ''"
      />
    </button>

    <!-- Tablet and desktop: a popover under the trigger -->
    <Teleport v-if="!isPhone" to="body">
      <div ref="container" class="fixed top-0 left-0 z-50 pointer-events-none">
        <transition
          enter-active-class="transition duration-100 ease-out"
          enter-from-class="scale-95 opacity-0"
          leave-active-class="transition duration-75 ease-in"
          leave-to-class="scale-95 opacity-0"
        >
          <div
            v-if="open"
            ref="panel"
            role="dialog"
            :aria-label="$t('dateRange.period')"
            class="p-2 border shadow-lg pointer-events-auto w-84 max-h-[calc(100dvh-1rem)] overflow-y-auto glass-strong rounded-2xl"
            @keydown.esc.stop="closeAndFocus"
          >
            <PeriodPickerPanel
              :model-value="modelValue"
              :presets="presets"
              :allow-custom="allowCustom"
              :summary="summary"
              @select="select"
            />
          </div>
        </transition>
      </div>
    </Teleport>

    <!-- Phones: the same choices in a bottom sheet -->
    <BaseSheet v-else :show="open" :title="$t('dateRange.period')" @close="close">
      <div class="px-3 pb-4">
        <PeriodPickerPanel
          :model-value="modelValue"
          :presets="presets"
          :allow-custom="allowCustom"
          :summary="summary"
          @select="select"
        />
      </div>
    </BaseSheet>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { onClickOutside, useResizeObserver } from '@vueuse/core'
import type { Placement } from '@popperjs/core'
import { usePopper } from '@/scripts/composables/use-popper'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useUserStore } from '@/scripts/stores/user.store'
import { formatPeriodLabel } from '@/scripts/utils/period'
import type { PeriodPreset, PeriodValue } from '@/scripts/utils/period'
import PeriodPickerPanel from './period-picker/PeriodPickerPanel.vue'

/**
 * Which stretch of time a chart or report covers: a compact trigger that reads
 * the current choice, opening a popover on wider screens and a bottom sheet on
 * phones with the presets and a custom range.
 *
 * The value is { preset, from, to }. Presets that only the server can resolve
 * (a company's fiscal year) leave the dates empty; the rest, and every custom
 * range, carry 'yyyy-MM-dd' dates. utils/period.ts has the preset lists and
 * turns a value into query parameters.
 */
interface Props {
  modelValue: PeriodValue
  presets: PeriodPreset[]
  allowCustom?: boolean
  /** The dates the current choice resolved to, shown above the presets */
  summary?: string
  /** Stretch the trigger to its container, as a form field */
  block?: boolean
  position?: Placement
}

const props = withDefaults(defineProps<Props>(), {
  allowCustom: true,
  summary: '',
  block: false,
  position: 'bottom-end',
})

interface Emits {
  (e: 'update:modelValue', value: PeriodValue): void
}

const emit = defineEmits<Emits>()

const { isPhone } = useBreakpoints()
const userStore = useUserStore()

const open = ref<boolean>(false)
const panel = ref<HTMLElement | null>(null)

const label = computed<string>(() => {
  return formatPeriodLabel(props.modelValue, props.presets, userStore.currentUserSettings.language)
})

const [trigger, container, popper] = usePopper({
  placement: props.position,
  strategy: 'fixed',
  modifiers: [
    { name: 'offset', options: { offset: [0, 6] } },
    { name: 'flip', options: { fallbackPlacements: ['top-end', 'bottom-start', 'top-start'] } },
  ],
})

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }

  await nextTick()
  popper.value?.update()
  panel.value?.querySelector<HTMLElement>('[aria-checked="true"], button')?.focus()
})

// The panel grows when the calendar opens; keep it on screen
useResizeObserver(panel, () => {
  popper.value?.update()
})

onClickOutside(panel, () => {
  if (open.value) {
    close()
  }
}, { ignore: [trigger] })

function close(): void {
  open.value = false
}

function closeAndFocus(): void {
  close()
  trigger.value?.focus()
}

function select(value: PeriodValue): void {
  emit('update:modelValue', value)
  closeAndFocus()
}
</script>
