<template>
  <!--
    The figures a record is about, as one glass strip: two per row on phones
    (an emphasised figure takes a full row), one row on wider screens. The
    inner grid is pulled up and left by a pixel so each cell's top and left
    hairlines divide the cells without doubling the strip's own border.
  -->
  <div class="overflow-hidden border glass rounded-xl">
    <dl :class="columnsClass" class="grid grid-cols-2 -mt-px -ms-px">
      <slot />
    </dl>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  /** Cells per row from the md breakpoint up */
  columns?: 2 | 3 | 4 | 5 | 6
}

const props = withDefaults(defineProps<Props>(), {
  columns: 4,
})

const COLUMNS: Record<number, string> = {
  2: 'md:grid-cols-2',
  3: 'md:grid-cols-3',
  4: 'md:grid-cols-4',
  5: 'md:grid-cols-5',
  6: 'md:grid-cols-6',
}

const columnsClass = computed<string>(() => COLUMNS[props.columns])
</script>
