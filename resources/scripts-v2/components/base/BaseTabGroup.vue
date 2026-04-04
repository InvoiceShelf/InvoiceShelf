<script setup lang="ts">
import { computed, useSlots } from 'vue'
import type { VNode } from 'vue'
import { TabGroup, TabList, Tab, TabPanels } from '@headlessui/vue'

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

const tabs = computed<TabData[]>(() => {
  const defaultSlot = slots.default?.()
  if (!defaultSlot) return []
  return defaultSlot.map((tab: VNode) => (tab.props ?? {}) as TabData)
})

function onChange(d: number): void {
  emit('change', tabs.value[d])
}
</script>

<template>
  <div>
    <TabGroup :default-index="defaultIndex" @change="onChange">
      <TabList
        :class="[
          'flex border-b border-line-default',
          'relative overflow-x-auto overflow-y-hidden',
          'lg:pb-0 lg:ml-0',
        ]"
      >
        <Tab
          v-for="(tab, index) in tabs"
          v-slot="{ selected }"
          :key="index"
          as="template"
        >
          <button
            :class="[
              'px-8 py-2 text-sm leading-5 font-medium flex items-center relative border-b-2 mt-4 focus:outline-hidden whitespace-nowrap',
              selected
                ? ' border-primary-400 text-heading font-medium'
                : 'border-transparent text-muted hover:text-body hover:border-line-strong',
            ]"
          >
            {{ tab.title }}

            <BaseBadge
              v-if="tab.count"
              class="!rounded-full overflow-hidden ml-2"
              :variant="tab['count-variant']"
              default-class="flex items-center justify-center w-5 h-5 p-1 rounded-full text-medium"
            >
              {{ tab.count }}
            </BaseBadge>
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
