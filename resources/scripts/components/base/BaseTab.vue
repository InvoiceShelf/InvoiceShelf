<template>
  <TabsContent :value="value" :class="[tabPanelContainer, 'focus:outline-hidden']">
    <slot />
  </TabsContent>
</template>

<script setup lang="ts">
import { TabsContent } from 'reka-ui'

withDefaults(
  defineProps<{
    title?: string | number
    count?: string | number
    countVariant?: string | number
    tabPanelContainer?: string
    /** Set by BaseTabGroup: the panel's position, which pairs it with its tab */
    value?: number
  }>(),
  {
    title: 'Tab',
    count: '',
    countVariant: '',
    tabPanelContainer: 'pt-4 empty:hidden',
    value: 0,
  },
)
</script>
