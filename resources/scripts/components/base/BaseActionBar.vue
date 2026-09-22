<template>
  <!--
    A page's own actions, pinned to the bottom edge on phones where they take
    the tab bar's place. Wider screens keep the same actions in the page
    header, so nothing renders there.
  -->
  <Teleport v-if="isPhone" defer to="#app-action-bar">
    <div
      class="flex items-center gap-2 px-4 pt-3 border-t glass-bar border-(--glass-edge) safe-drawer"
    >
      <slot />
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useGlobalStore } from '@/scripts/stores/global.store'

const { isPhone } = useBreakpoints()
const globalStore = useGlobalStore()

let release: (() => void) | null = null

watch(
  isPhone,
  (phone) => {
    if (phone && !release) {
      release = globalStore.registerActionBar()
    } else if (!phone && release) {
      release()
      release = null
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  release?.()
  release = null
})
</script>
