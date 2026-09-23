<template>
  <div
    class="relative isolate flex flex-col items-center justify-center overflow-hidden text-center"
    :class="[
      compact ? 'px-4 py-10' : 'px-6 py-14 md:py-20',
      ghostColumns && !compact ? 'min-h-[26rem] border glass rounded-xl' : '',
      ghostColumns && compact ? 'min-h-64' : '',
    ]"
  >
    <!-- Faded rows shaped like the table that will be here: a hint, not content -->
    <template v-if="ghostColumns">
      <div class="empty-ghost" aria-hidden="true">
        <template v-if="isPhone">
          <div
            v-for="row in rowCount"
            :key="row"
            class="grid gap-2 py-3.5 border-b border-line-light"
          >
            <div class="flex justify-between gap-3">
              <span class="h-2 rounded bg-line-light" :style="{ width: `${barWidth(row, 0) * 0.55}%` }" />
              <span class="h-2 rounded bg-line-light w-[22%]" />
            </div>
            <div class="flex justify-between gap-3 opacity-70">
              <span class="h-2 rounded bg-line-light" :style="{ width: `${barWidth(row, 3) * 0.4}%` }" />
              <span class="h-2 rounded bg-line-light w-[16%]" />
            </div>
          </div>
        </template>
        <template v-else>
          <!-- A header row of its own only when it stands in for the whole table -->
          <div
            v-for="row in rows"
            :key="row"
            class="grid items-center gap-5 border-b border-line-light"
            :class="isHeader(row) ? 'py-2.5' : 'py-3.5'"
            :style="{ gridTemplateColumns: `repeat(${ghostColumns}, minmax(0, 1fr))` }"
          >
            <span
              v-for="column in ghostColumns"
              :key="column"
              class="rounded"
              :class="[
                isHeader(row) ? 'h-1.5 bg-line-default' : 'h-2 bg-line-light',
                column === ghostColumns ? 'justify-self-end' : '',
              ]"
              :style="{ width: `${isHeader(row) ? 46 + ((column * 13) % 30) : barWidth(row, column)}%` }"
            />
          </div>
        </template>
      </div>
      <div class="empty-veil" aria-hidden="true" />
    </template>

    <EmptyArt v-if="art" :name="art" :class="compact ? 'scale-[0.8] -my-2' : 'mb-2'" />

    <!-- The entity's icon on a tinted glass tile, with a soft halo -->
    <div v-else-if="icon" class="relative mb-6" aria-hidden="true">
      <div class="absolute rounded-full -inset-6 bg-primary-500/10 blur-2xl" />
      <div
        class="
          relative flex items-center justify-center w-16 h-16 rounded-2xl
          glass border ring-1 ring-inset ring-primary-600/10
        "
      >
        <div class="absolute inset-0 rounded-2xl bg-linear-to-br from-primary-500/15 to-transparent" />
        <BaseIcon :name="icon" class="relative w-7 h-7 text-primary-600" />
      </div>
    </div>
    <div v-else-if="$slots.default" class="flex flex-col items-center justify-center mb-5 [&>svg]:h-24 [&>svg]:w-auto">
      <slot></slot>
    </div>

    <p class="font-semibold text-heading text-balance" :class="compact ? 'text-base' : 'text-section'">{{ title }}</p>
    <p v-if="description" class="max-w-sm mt-1.5 text-sm text-muted text-pretty">
      {{ description }}
    </p>
    <div v-if="$slots.actions" :class="compact ? 'mt-4' : 'mt-6'">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import EmptyArt from '@/scripts/components/empty-art/EmptyArt.vue'

interface Props {
  title?: string
  description?: string
  /** A heroicon name for the entity; replaces the default slot's illustration */
  icon?: string
  /** A line illustration from EmptyArt (invoice, customer, tax...); takes the icon's place */
  art?: string
  /**
   * Faded placeholder rows behind the message, shaped like the table that
   * will be here: true for five columns, or the table's column count. On its
   * own the empty state then draws the table's card as well.
   */
  ghost?: boolean | number
  /** Smaller, for a card or table that is already on the page */
  compact?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
  icon: '',
  art: '',
  ghost: false,
  compact: false,
})

const { isPhone } = useBreakpoints()

const ghostColumns = computed<number>(() => {
  if (props.ghost === true) {
    return 5
  }

  return typeof props.ghost === 'number' ? Math.max(2, props.ghost) : 0
})

const rowCount = computed<number>(() => (props.compact ? 3 : 6))

// Rows to draw on wider screens: the first is a header unless compact
const rows = computed<number>(() => rowCount.value + (props.compact ? 0 : 1))

function isHeader(row: number): boolean {
  return !props.compact && row === 1
}

// Widths that vary by row and column, so the rows read as data
const SHAPES = [72, 50, 86, 60, 78, 44]

function barWidth(row: number, column: number): number {
  return SHAPES[(row + column * 2) % SHAPES.length]
}
</script>
