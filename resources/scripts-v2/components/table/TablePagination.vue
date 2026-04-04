<template>
  <div
    v-if="shouldShowPagination"
    class="
      flex
      items-center
      justify-between
      px-4
      py-3
      bg-surface
      border-t border-line-default
      sm:px-6
    "
  >
    <div class="flex justify-between flex-1 sm:hidden">
      <a
        href="#"
        :class="{
          'disabled cursor-normal pointer-events-none !bg-surface-tertiary !text-subtle':
            pagination.currentPage === 1,
        }"
        class="
          relative
          inline-flex
          items-center
          px-4
          py-2
          text-sm
          font-medium
          text-body
          bg-surface
          border border-line-default
          rounded-md
          hover:bg-hover
        "
        @click="pageClicked(pagination.currentPage - 1)"
      >
        {{ $t('general.pagination.previous') }}
      </a>
      <a
        href="#"
        :class="{
          'disabled cursor-default pointer-events-none !bg-surface-tertiary !text-subtle':
            pagination.currentPage === pagination.totalPages,
        }"
        class="
          relative
          inline-flex
          items-center
          px-4
          py-2
          ml-3
          text-sm
          font-medium
          text-body
          bg-surface
          border border-line-default
          rounded-md
          hover:bg-hover
        "
        @click="pageClicked(pagination.currentPage + 1)"
      >
        {{ $t('general.pagination.next') }}
      </a>
    </div>
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-body">
          {{ $t('general.pagination.showing') }}
          {{ ' ' }}
          <span
            v-if="pagination.limit && pagination.currentPage"
            class="font-medium"
          >
            {{
              pagination.currentPage * pagination.limit - (pagination.limit - 1)
            }}
          </span>
          {{ ' ' }}
          {{ $t('general.pagination.to') }}
          {{ ' ' }}
          <span
            v-if="pagination.limit && pagination.currentPage"
            class="font-medium"
          >
            <span
              v-if="
                pagination.currentPage * pagination.limit <=
                pagination.totalCount
              "
            >
              {{ pagination.currentPage * pagination.limit }}
            </span>
            <span v-else>
              {{ pagination.totalCount }}
            </span>
          </span>
          {{ ' ' }}
          {{ $t('general.pagination.of') }}
          {{ ' ' }}
          <span v-if="pagination.totalCount" class="font-medium">
            {{ pagination.totalCount }}
          </span>
          {{ ' ' }}
          {{ $t('general.pagination.results') }}
        </p>
      </div>
      <div>
        <nav
          class="relative z-0 inline-flex -space-x-px rounded-lg shadow-sm"
          aria-label="Pagination"
        >
          <a
            href="#"
            :class="{
              'disabled cursor-normal pointer-events-none !bg-surface-tertiary !text-subtle':
                pagination.currentPage === 1,
            }"
            class="
              relative
              inline-flex
              items-center
              px-2
              py-2
              text-sm
              font-medium
              text-muted
              bg-surface
              border border-line-default
              rounded-l-lg
              hover:bg-hover
            "
            @click="pageClicked(pagination.currentPage - 1)"
          >
            <span class="sr-only">Previous</span>
            <BaseIcon name="ChevronLeftIcon" />
          </a>
          <a
            v-if="hasFirst"
            href="#"
            aria-current="page"
            :class="{
              'z-10 bg-primary-500 border-primary-500 text-white':
                isActive(1),
              'bg-surface border-line-default text-muted hover:bg-hover':
                !isActive(1),
            }"
            class="
              relative
              inline-flex
              items-center
              px-4
              py-2
              text-sm
              font-medium
              border
            "
            @click="pageClicked(1)"
          >
            1
          </a>

          <span
            v-if="hasFirstEllipsis"
            class="
              relative
              inline-flex
              items-center
              px-4
              py-2
              text-sm
              font-medium
              text-body
              bg-surface
              border border-line-default
            "
          >
            ...
          </span>
          <a
            v-for="page in pages"
            :key="page"
            href="#"
            :class="{
              'z-10 bg-primary-500 border-primary-500 text-white':
                isActive(page),
              'bg-surface border-line-default text-muted hover:bg-hover':
                !isActive(page),
            }"
            class="
              relative
              items-center
              hidden
              px-4
              py-2
              text-sm
              font-medium
              border
              md:inline-flex
            "
            @click="pageClicked(page)"
          >
            {{ page }}
          </a>

          <span
            v-if="hasLastEllipsis"
            class="
              relative
              inline-flex
              items-center
              px-4
              py-2
              text-sm
              font-medium
              text-body
              bg-surface
              border border-line-default
            "
          >
            ...
          </span>
          <a
            v-if="hasLast"
            href="#"
            aria-current="page"
            :class="{
              'z-10 bg-primary-500 border-primary-500 text-white':
                isActive(pagination.totalPages),
              'bg-surface border-line-default text-muted hover:bg-hover':
                !isActive(pagination.totalPages),
            }"
            class="
              relative
              inline-flex
              items-center
              px-4
              py-2
              text-sm
              font-medium
              border
            "
            @click="pageClicked(pagination.totalPages)"
          >
            {{ pagination.totalPages }}
          </a>
          <a
            href="#"
            class="
              relative
              inline-flex
              items-center
              px-2
              py-2
              text-sm
              font-medium
              text-muted
              bg-surface
              border border-line-default
              rounded-r-lg
              hover:bg-hover
            "
            :class="{
              'disabled cursor-default pointer-events-none !bg-surface-tertiary !text-subtle':
                pagination.currentPage === pagination.totalPages,
            }"
            @click="pageClicked(pagination.currentPage + 1)"
          >
            <span class="sr-only">Next</span>
            <BaseIcon name="ChevronRightIcon" />
          </a>
        </nav>
      </div>
    </div>
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
