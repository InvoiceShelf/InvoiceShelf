<template>
  <!--
    The other records beside the one on screen, on wide screens only: search,
    sort field, order, and a scrolling list the page fills with
    RecordListItem rows. It sits in the page's flex row (not fixed), so it
    follows the sidebar's width and never overlaps the content. Pages place it
    after the record in the markup, so Tab reaches the record first; order-first
    keeps it on the left.
  -->
  <aside
    :aria-label="label || $t('general.list')"
    class="
      order-first sticky top-(--app-top-inset) hidden xl:flex flex-col w-80 shrink-0 mt-3 ml-8 mb-10
      border rounded-2xl glass h-[calc(100dvh-var(--app-top-inset)-1.5rem)] overflow-hidden
    "
  >
    <div class="flex items-center gap-2 p-3 border-b border-line-light">
      <BaseInput
        :model-value="search ?? ''"
        :placeholder="$t('general.search')"
        :aria-label="$t('general.search')"
        type="search"
        container-class="flex-1"
        @update:model-value="onSearch"
      >
        <template #left>
          <BaseIcon name="MagnifyingGlassIcon" class="w-4 h-4 text-subtle" aria-hidden="true" />
        </template>
      </BaseInput>

      <BaseDropdown
        v-if="sortOptions.length"
        position="bottom-start"
        width-class="w-56"
        :label="$t('general.sort_by')"
      >
        <template #activator>
          <span
            class="flex items-center justify-center border rounded-lg w-9 h-9 border-line-default text-muted hover:bg-hover"
          >
            <BaseIcon name="FunnelIcon" class="w-4 h-4" />
          </span>
        </template>
        <p class="px-3 pt-2 pb-1 text-xs font-medium text-muted">
          {{ $t('general.sort_by') }}
        </p>
        <BaseDropdownItem
          v-for="option in sortOptions"
          :key="option.value"
          @click="emit('update:sortField', option.value)"
        >
          <span class="flex-1">{{ option.label }}</span>
          <BaseIcon
            v-if="sortField === option.value"
            name="CheckIcon"
            class="w-4 h-4 text-primary-600"
          />
        </BaseDropdownItem>
      </BaseDropdown>

      <button
        type="button"
        class="flex items-center justify-center border rounded-lg w-9 h-9 shrink-0 border-line-default text-muted hover:bg-hover"
        :aria-label="ascending ? $t('general.sort_ascending') : $t('general.sort_descending')"
        @click="emit('toggleOrder')"
      >
        <BaseIcon :name="ascending ? 'BarsArrowUpIcon' : 'BarsArrowDownIcon'" class="w-4 h-4" />
      </button>
    </div>

    <div ref="listEl" class="flex-1 min-h-0 overflow-y-auto">
      <slot />

      <div v-if="loading" class="flex items-center justify-center p-4" role="status">
        <BaseSpinner class="w-5 h-5 text-subtle" aria-hidden="true" />
        <span class="sr-only">{{ $t('general.loading') }}</span>
      </div>
      <p v-if="empty && !loading" class="px-4 py-8 text-sm text-center text-muted" role="status">
        {{ emptyText }}
      </p>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref } from 'vue'

export interface SortOption {
  value: string
  label: string
}

interface Props {
  search?: string | null
  sortOptions?: SortOption[]
  sortField?: string | null
  ascending?: boolean
  loading?: boolean
  empty?: boolean
  emptyText?: string
  /** The landmark's name, such as "Invoices" */
  label?: string
}

withDefaults(defineProps<Props>(), {
  search: '',
  sortOptions: () => [],
  sortField: null,
  ascending: false,
  label: '',
  loading: false,
  empty: false,
  emptyText: '',
})

const emit = defineEmits<{
  (e: 'update:search', value: string): void
  (e: 'update:sortField', value: string): void
  (e: 'toggleOrder'): void
}>()

const listEl = ref<HTMLElement | null>(null)

function onSearch(value: string | number): void {
  emit('update:search', String(value))
}

defineExpose({ listEl })
</script>
