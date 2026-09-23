<template>
  <div
    :class="[wide || emphasis ? 'col-span-2 md:col-span-1' : '']"
    class="min-w-0 px-4 py-3.5 border-t border-s md:px-5 border-line-light"
  >
    <dt class="text-xs font-medium truncate text-muted">{{ label }}</dt>
    <dd
      :class="emphasis ? 'text-lg md:text-base font-semibold' : 'text-sm font-medium'"
      class="mt-1 truncate tabular text-heading"
    >
      <slot />
    </dd>
  </div>
</template>

<script setup lang="ts">
/**
 * One figure in a BaseStatStrip. `emphasis` marks the figure the record is
 * about (a balance, an amount due): larger, and a full row on phones. `wide`
 * gives any other cell a full row on phones.
 */
interface Props {
  label: string
  emphasis?: boolean
  wide?: boolean
}

withDefaults(defineProps<Props>(), {
  emphasis: false,
  wide: false,
})
</script>
