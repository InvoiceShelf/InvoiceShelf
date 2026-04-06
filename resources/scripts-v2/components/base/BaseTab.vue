<template>
  <TabPanel :class="[tabPanelContainer, 'focus:outline-hidden']">
    <slot />
  </TabPanel>
</template>

<script setup lang="ts">
import { TabPanel } from '@headlessui/vue'

withDefaults(
  defineProps<{
    title?: string | number
    count?: string | number
    countVariant?: string | number
    tabPanelContainer?: string
  }>(),
  {
    title: 'Tab',
    count: '',
    countVariant: '',
    tabPanelContainer: 'py-4 mt-px',
  },
)
</script>
