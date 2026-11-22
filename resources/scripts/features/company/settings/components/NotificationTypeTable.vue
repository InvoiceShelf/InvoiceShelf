<script setup lang="ts" generic="Row extends { type: string; group: string }">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * The kinds of notice grouped by area, one switch per column: a person's own
 * channels, or the owner's defaults for the company.
 */
interface Column {
  key: string
  label: string
}

const props = defineProps<{
  rows: Row[]
  columns: Column[]
  /** While a save is in flight */
  busy?: boolean
  /** A row whose switches cannot be used, such as a type switched off */
  rowDisabled?: (row: Row) => boolean
  /** A switch that cannot be used on its own, such as a channel under a switched-off type */
  cellDisabled?: (row: Row, key: string) => boolean
}>()

const emit = defineEmits<{
  (e: 'change', row: Row, key: string, value: boolean): void
}>()

const { t } = useI18n()

const groups = computed<Array<{ group: string; items: Row[] }>>(() => {
  const grouped = new Map<string, Row[]>()
  for (const row of props.rows) {
    grouped.set(row.group, [...(grouped.get(row.group) ?? []), row])
  }
  return [...grouped.entries()].map(([group, items]) => ({ group, items }))
})

const gridStyle = computed<string>(
  () => `grid-template-columns: minmax(0, 1fr) repeat(${props.columns.length}, 4.5rem)`,
)

function value(row: Row, key: string): boolean {
  return Boolean((row as Record<string, unknown>)[key])
}

function disabled(row: Row, key: string): boolean {
  return Boolean(props.busy || props.rowDisabled?.(row) || props.cellDisabled?.(row, key))
}

function switchLabel(row: Row, column: Column): string {
  return `${t(`inbox.types.${row.type}.label`)}: ${column.label}`
}
</script>

<template>
  <div class="border-t border-line-default">
    <div
      class="grid items-center gap-3 py-2 text-xs font-medium tracking-wide uppercase text-subtle"
      :style="gridStyle"
      aria-hidden="true"
    >
      <span>{{ $t('inbox.preferences.type') }}</span>
      <span v-for="column in columns" :key="column.key" class="text-center">{{ column.label }}</span>
    </div>

    <section v-for="section in groups" :key="section.group" class="border-t border-line-default">
      <h3 class="pt-4 pb-1 text-sm font-semibold text-heading">
        {{ $t(`inbox.groups.${section.group}`) }}
      </h3>

      <div
        v-for="row in section.items"
        :key="row.type"
        class="grid items-center gap-3 py-3"
        :style="gridStyle"
      >
        <div class="min-w-0" :class="rowDisabled?.(row) ? 'opacity-60' : ''">
          <p class="text-sm font-medium text-body">
            {{ $t(`inbox.types.${row.type}.label`) }}
          </p>
          <p class="mt-0.5 text-sm text-muted">
            {{ $t(`inbox.types.${row.type}.description`) }}
          </p>
          <slot name="note" :row="row" />
        </div>

        <div v-for="column in columns" :key="column.key" class="flex justify-center">
          <BaseSwitch
            :model-value="value(row, column.key)"
            :aria-label="switchLabel(row, column)"
            :disabled="disabled(row, column.key)"
            @update:model-value="emit('change', row, column.key, $event)"
          />
        </div>
      </div>
    </section>
  </div>
</template>
