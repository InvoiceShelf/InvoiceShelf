<script setup lang="ts">
import { computed, Comment, Fragment, ref, useSlots } from 'vue'
import type { VNode } from 'vue'
import { TabsList, TabsRoot, TabsTrigger } from 'reka-ui'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'

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

// The BaseTab children, flattened out of v-for fragments and without the
// placeholders a false v-if leaves, so each tab lines up with its panel
function flatten(nodes: VNode[]): VNode[] {
  return nodes.flatMap((node) => {
    if (node.type === Comment) return []
    if (node.type === Fragment && Array.isArray(node.children)) return flatten(node.children as VNode[])
    return [node]
  })
}

const tabs = computed<TabData[]>(() => flatten(slots.default?.() ?? []).map((tab) => (tab.props ?? {}) as TabData))

// Tabs and panels pair up by position
const current = ref<number>(props.defaultIndex)

function onChange(index: string | number): void {
  current.value = Number(index)
  emit('change', tabs.value[current.value])
}
</script>

<template>
  <div class="w-full">
    <TabsRoot :model-value="current" @update:model-value="onChange">
      <TabsList
        :class="[
          'relative flex overflow-x-auto overflow-y-hidden',
          isPhone
            ? 'gap-2 -mx-4 px-4 pb-1 [scrollbar-width:none]'
            : 'gap-6 border-b border-line-light',
        ]"
      >
        <template v-for="(tab, index) in tabs" :key="index">
          <TabsTrigger
            v-if="isPhone"
            :value="index"
            :class="[
              'flex items-center shrink-0 h-8 px-3.5 text-sm font-medium rounded-full border whitespace-nowrap transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus',
              current === index
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
          </TabsTrigger>
          <TabsTrigger
            v-else
            :value="index"
            :class="[
              'relative flex items-center -mb-px py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition-colors focus:outline-hidden focus-visible:text-heading',
              current === index
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
          </TabsTrigger>
        </template>
      </TabsList>

      <slot name="before-tabs" />

      <component
        :is="panel"
        v-for="(panel, index) in flatten($slots.default?.() ?? [])"
        :key="index"
        :value="index"
      />
    </TabsRoot>
  </div>
</template>
