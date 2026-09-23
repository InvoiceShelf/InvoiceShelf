<template>
  <div
    v-if="shouldShowPagination"
    class="flex items-center justify-between gap-3 px-4 py-3 border-t md:px-6 border-line-light"
  >
    <p class="text-sm text-muted tabular">
      <template v-if="pagination.limit && pagination.currentPage">
        <span class="md:hidden">
          {{ pagination.currentPage }} / {{ pagination.totalPages }}
        </span>
        <span class="hidden md:inline">
          {{ $t('general.pagination.showing') }}
          <span class="font-medium text-body">{{ firstItem }}</span>
          {{ $t('general.pagination.to') }}
          <span class="font-medium text-body">{{ lastItem }}</span>
          {{ $t('general.pagination.of') }}
          <span class="font-medium text-body">{{ pagination.totalCount }}</span>
          {{ $t('general.pagination.results') }}
        </span>
      </template>
    </p>

    <nav class="flex items-center gap-1" :aria-label="$t('general.pagination.label')">
      <button
        type="button"
        :class="navButtonClass"
        :disabled="pagination.currentPage === 1"
        :aria-label="$t('general.pagination.previous')"
        @click="pageClicked(pagination.currentPage - 1)"
      >
        <BaseIcon name="ChevronLeftIcon" class="w-4 h-4" />
      </button>

      <div class="items-center hidden gap-1 md:flex">
        <button
          v-if="hasFirst"
          type="button"
          :class="pageButtonClass(1)"
          :aria-current="isActive(1) ? 'page' : undefined"
          :aria-label="$t('general.pagination.page', { page: 1 })"
          @click="pageClicked(1)"
        >
          1
        </button>
        <span v-if="hasFirstEllipsis" class="px-1 text-sm text-subtle" aria-hidden="true">…</span>
        <button
          v-for="page in pages"
          :key="page"
          type="button"
          :class="pageButtonClass(page)"
          :aria-current="isActive(page) ? 'page' : undefined"
          :aria-label="$t('general.pagination.page', { page: page })"
          @click="pageClicked(page)"
        >
          {{ page }}
        </button>
        <span v-if="hasLastEllipsis" class="px-1 text-sm text-subtle" aria-hidden="true">…</span>
        <button
          v-if="hasLast"
          type="button"
          :class="pageButtonClass(pagination.totalPages)"
          :aria-current="isActive(pagination.totalPages) ? 'page' : undefined"
          :aria-label="$t('general.pagination.page', { page: pagination.totalPages })"
          @click="pageClicked(pagination.totalPages)"
        >
          {{ pagination.totalPages }}
        </button>
      </div>

      <button
        type="button"
        :class="navButtonClass"
        :disabled="pagination.currentPage === pagination.totalPages"
        :aria-label="$t('general.pagination.next')"
        @click="pageClicked(pagination.currentPage + 1)"
      >
        <BaseIcon name="ChevronRightIcon" class="w-4 h-4" />
      </button>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export interface PaginationInfo {
  currentPage: number
  totalPages: number
  totalCount: number
  count: number
  limit: number
}

interface Props {
  pagination: PaginationInfo
}

interface Emits {
  (e: 'pageChange', page: number): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const pages = computed<number[]>(() => {
  if (props.pagination.totalPages === undefined) return []
  return pageLinks()
})

const hasFirst = computed<boolean>(() => {
  return props.pagination.currentPage >= 4 || props.pagination.totalPages < 10
})

const hasLast = computed<boolean>(() => {
  return (
    props.pagination.currentPage <= props.pagination.totalPages - 3 ||
    props.pagination.totalPages < 10
  )
})

const hasFirstEllipsis = computed<boolean>(() => {
  return (
    props.pagination.currentPage >= 4 && props.pagination.totalPages >= 10
  )
})

const hasLastEllipsis = computed<boolean>(() => {
  return (
    props.pagination.currentPage <= props.pagination.totalPages - 3 &&
    props.pagination.totalPages >= 10
  )
})

const shouldShowPagination = computed<boolean>(() => {
  if (props.pagination.totalPages === undefined) {
    return false
  }
  if (props.pagination.count === 0) {
    return false
  }
  return props.pagination.totalPages > 1
})

const navButtonClass =
  'flex items-center justify-center w-10 h-10 md:w-8 md:h-8 rounded-lg border border-line-default text-body hover:bg-hover disabled:opacity-40 disabled:pointer-events-none'

function pageButtonClass(page: number): string {
  return [
    'min-w-8 h-8 px-2 rounded-lg text-sm font-medium tabular transition-colors',
    isActive(page) ? 'bg-primary-50 text-primary-700' : 'text-body hover:bg-hover',
  ].join(' ')
}

const firstItem = computed<number>(() => {
  return props.pagination.currentPage * props.pagination.limit - (props.pagination.limit - 1)
})

const lastItem = computed<number>(() => {
  return Math.min(props.pagination.currentPage * props.pagination.limit, props.pagination.totalCount)
})

function isActive(page: number): boolean {
  const currentPage = props.pagination.currentPage || 1
  return currentPage === page
}

function pageClicked(page: number): void {
  if (
    page === props.pagination.currentPage ||
    page > props.pagination.totalPages ||
    page < 1
  ) {
    return
  }

  emit('pageChange', page)
}

function pageLinks(): number[] {
  const pageList: number[] = []
  let left = 2
  let right = props.pagination.totalPages - 1
  if (props.pagination.totalPages >= 10) {
    left = Math.max(1, props.pagination.currentPage - 2)
    right = Math.min(
      props.pagination.currentPage + 2,
      props.pagination.totalPages
    )
  }
  for (let i = left; i <= right; i++) {
    pageList.push(i)
  }
  return pageList
}
</script>
