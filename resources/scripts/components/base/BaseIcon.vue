<template>
  <component
    :is="heroIcons[name]"
    v-if="isLoaded"
    class="h-5 w-5"
    :class="MIRRORED.has(name) ? 'rtl:-scale-x-100' : ''"
  />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Component } from 'vue'
import * as heroIcons from '@heroicons/vue/24/outline'

const isLoaded = ref<boolean>(false)

/**
 * Icons that point along the line of reading (back, next, send, sign out)
 * mirror in a right-to-left page. Up, down, refresh and the like do not.
 */
const MIRRORED = new Set<string>([
  'ArrowLeftIcon',
  'ArrowRightIcon',
  'ArrowLongLeftIcon',
  'ArrowLongRightIcon',
  'ArrowSmallLeftIcon',
  'ArrowSmallRightIcon',
  'ArrowUturnLeftIcon',
  'ArrowUturnRightIcon',
  'ArrowLeftOnRectangleIcon',
  'ArrowRightOnRectangleIcon',
  'ArrowLeftStartOnRectangleIcon',
  'ArrowLeftEndOnRectangleIcon',
  'ArrowRightStartOnRectangleIcon',
  'ArrowRightEndOnRectangleIcon',
  'ChevronLeftIcon',
  'ChevronRightIcon',
  'ChevronDoubleLeftIcon',
  'ChevronDoubleRightIcon',
  'PaperAirplaneIcon',
])

interface Props {
  name: string
}

defineProps<Props>()

onMounted(() => {
  isLoaded.value = true
})
</script>
