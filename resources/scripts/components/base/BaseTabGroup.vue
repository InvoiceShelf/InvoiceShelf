<script setup lang="ts">
import { computed, useSlots } from 'vue'
import type { VNode } from 'vue'
import { TabGroup, TabList, Tab, TabPanels } from '@headlessui/vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { isRtl } from '@/scripts/utils/direction'

interface TabData {
  title: string
  count?: number | string
  'count-variant'?: string
  [key: string]: unknown
}

interface Props {
  defaultIndex?: number
  filter?: string | null
}

interface Emits {
  (e: 'change', tab: TabData): void
}

const props = withDefaults(defineProps<Props>(), {
  defaultIndex: 0,
  filter: null,
})

const emit = defineEmits<Emits>()

const slots = useSlots()

// Underlined tabs on wider screens; a scrolling row of chips on phones
const { isPhone } = useBreakpoints()

const tabs = computed<TabData[]>(() => {
  const defaultSlot = slots.default?.()
  if (!defaultSlot) return []
  return defaultSlot.map((tab: VNode) => (tab.props ?? {}) as TabData)
})

function onChange(d: number): void {
  emit('change', tabs.value[d])
}

// Headless UI's tabs treat ArrowLeft as "previous" whatever the direction.
// On a right-to-left page previous is to the right, so swap the two before
// the tab sees them. Our own re-sent key is untrusted and passes through.
function mirrorArrowKeys(event: KeyboardEvent): void {
  if (!isRtl.value || !event.isTrusted || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
    return
  }

  event.preventDefault()
  event.stopPropagation()
  event.target?.dispatchEvent(new KeyboardEvent('keydown', {
    key: event.key === 'ArrowLeft' ? 'ArrowRight' : 'ArrowLeft',
    bubbles: true,
    cancelable: true,
  }))
}
</script>

<template>
  <div class="w-full">
    <TabGroup :default-index="defaultIndex" @change="onChange">
      <TabList
        :class="[
          'relative flex overflow-x-auto overflow-y-hidden',
          isPhone
            ? 'gap-2 -mx-4 px-4 pb-1 [scrollbar-width:none]'
            : 'gap-6 border-b border-line-light',
        ]"
        @keydown.capture="mirrorArrowKeys"
      >
        <Tab
          v-for="(tab, index) in tabs"
          v-slot="{ selected }"
          :key="index"
          as="template"
        >
          <button
            v-if="isPhone"
            :class="[
              'flex items-center shrink-0 h-8 px-3.5 text-sm font-medium rounded-full border whitespace-nowrap transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus',
              selected
                ? 'bg-heading text-surface border-transparent'
                : 'bg-surface text-body border-line-default',
            ]"
          >
            {{ tab.title }}
            <span
              v-if="tab.count"
              class="ms-1.5 text-xs tabular opacity-70"
            >
              {{ tab.count }}
            </span>
          </button>
          <button
            v-else
            :class="[
              'relative flex items-center -mb-px py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition-colors focus:outline-hidden focus-visible:text-heading',
              selected
                ? 'border-primary-600 text-heading'
                : 'border-transparent text-muted hover:text-heading',
            ]"
          >
            {{ tab.title }}
            <span
              v-if="tab.count"
              class="ms-2 px-1.5 min-w-5 h-5 inline-flex items-center justify-center text-xs rounded-full tabular bg-surface-muted text-body"
            >
              {{ tab.count }}
            </span>
          </button>
        </Tab>
      </TabList>

      <slot name="before-tabs" />

      <TabPanels>
        <slot />
      </TabPanels>
    </TabGroup>
  </div>
</template>
