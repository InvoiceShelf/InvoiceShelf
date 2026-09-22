<template>
  <div
    class="relative flex flex-col overflow-hidden border bg-surface border-line-light rounded-xl shadow-card"
  >
    <!-- Phones: tappable rows built from the same cell slots -->
    <template v-if="isList">
      <ul
        v-if="loadingType === 'placeholder' && (loading || isLoading)"
        class="divide-y divide-line-light"
      >
        <li v-for="placeRow in placeholderCount" :key="placeRow" class="px-4 py-4">
          <ContentPlaceholder :rounded="true">
            <ContentPlaceholderText class="w-2/3 h-4" :lines="1" />
            <ContentPlaceholderText class="w-1/3 h-3 mt-2" :lines="1" />
          </ContentPlaceholder>
        </li>
      </ul>
      <ul v-else class="divide-y divide-line-light">
        <li
          v-for="(row, index) in sortedRows"
          :key="row.data?.id ?? index"
          :class="rowTo ? 'cursor-pointer active:bg-hover' : ''"
          class="flex items-center gap-3 px-4 py-3.5"
          @click="onRowClick(row, $event)"
        >
          <div class="flex-1 min-w-0">
            <div
              v-if="mobileColumns.title"
              class="text-[15px] font-medium leading-5 truncate text-heading [&_a]:text-heading [&_a]:font-medium"
            >
              <slot :name="'cell-' + mobileColumns.title.key" :row="row">
                {{ lodashGet(row.data, mobileColumns.title.key) }}
              </slot>
            </div>
            <div
              v-if="mobileColumns.subtitle.length"
              class="flex flex-wrap items-center gap-x-2.5 gap-y-1 mt-1 text-[13px] leading-4 text-muted [&_a]:text-muted"
            >
              <span
                v-for="column in mobileColumns.subtitle"
                :key="column.key"
                class="min-w-0 truncate"
              >
                <slot :name="'cell-' + column.key" :row="row">
                  {{ lodashGet(row.data, column.key) }}
                </slot>
              </span>
            </div>
          </div>

          <div
            v-if="mobileColumns.trailing || mobileColumns.trailingSub.length || mobileColumns.badge"
            class="flex flex-col items-end gap-1 text-right shrink-0"
          >
            <div
              v-if="mobileColumns.trailing"
              class="text-[15px] font-medium leading-5 tabular text-heading"
            >
              <slot :name="'cell-' + mobileColumns.trailing.key" :row="row">
                {{ lodashGet(row.data, mobileColumns.trailing.key) }}
              </slot>
            </div>
            <div
              v-for="column in mobileColumns.trailingSub"
              :key="column.key"
              class="text-[13px] leading-4 tabular text-muted"
            >
              <slot :name="'cell-' + column.key" :row="row">
                {{ lodashGet(row.data, column.key) }}
              </slot>
            </div>
            <div v-if="mobileColumns.badge">
              <slot :name="'cell-' + mobileColumns.badge.key" :row="row">
                {{ lodashGet(row.data, mobileColumns.badge.key) }}
              </slot>
            </div>
          </div>

          <div v-if="mobileColumns.actions" class="-mr-2 shrink-0">
            <slot :name="'cell-' + mobileColumns.actions.key" :row="row" />
          </div>
        </li>
      </ul>
    </template>

    <!-- Tablet and desktop -->
    <template v-else>
      <slot name="header" />
      <div class="overflow-x-auto">
        <table :class="tableClass">
          <thead :class="theadClass">
            <tr>
              <th
                v-for="column in visibleColumns"
                :key="column.key"
                :class="[
                  getThClass(column),
                  { 'text-heading': sort.fieldName === column.key },
                ]"
                :aria-sort="ariaSort(column)"
                @click="changeSorting(column)"
              >
                {{ column.label }}
                <BaseIcon
                  v-if="sort.fieldName === column.key && sort.order"
                  :name="sort.order === 'asc' ? 'ChevronUpIcon' : 'ChevronDownIcon'"
                  class="inline-block w-3.5 h-3.5 ml-0.5 -mt-0.5"
                />
              </th>
            </tr>
          </thead>
          <tbody
            v-if="loadingType === 'placeholder' && (loading || isLoading)"
            class="divide-y divide-line-light"
          >
            <tr v-for="placeRow in placeholderCount" :key="placeRow">
              <td
                v-for="column in visibleColumns"
                :key="column.key"
                :class="getTdClass(column)"
              >
                <ContentPlaceholder
                  :class="getPlaceholderClass(column)"
                  :rounded="true"
                >
                  <ContentPlaceholderText
                    class="w-full h-5"
                    :lines="1"
                  />
                </ContentPlaceholder>
              </td>
            </tr>
          </tbody>
          <tbody v-else :class="['divide-y divide-line-light', tbodyClass]">
            <tr
              v-for="(row, index) in sortedRows"
              :key="row.data?.id ?? index"
              :class="rowTo ? 'cursor-pointer' : ''"
              class="transition-colors hover:bg-hover"
              @click="onRowClick(row, $event)"
            >
              <td
                v-for="column in visibleColumns"
                :key="column.key"
                :class="getTdClass(column)"
              >
                <slot :name="'cell-' + column.key" :row="row">
                  {{ lodashGet(row.data, column.key) }}
                </slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div
      v-if="loadingType === 'spinner' && (loading || isLoading)"
      class="absolute inset-0 z-10 flex items-center justify-center bg-surface/60"
    >
      <SpinnerIcon class="w-8 h-8 text-subtle" />
    </div>

    <div
      v-else-if="
        !loading && !isLoading && sortedRows && sortedRows.length === 0
      "
      class="flex flex-col items-center justify-center gap-2 py-12 text-sm text-center text-muted"
    >
      <BaseIcon name="InboxIcon" class="w-6 h-6 text-subtle" />
      <span>{{ $t('general.no_data_found') }}</span>
    </div>

    <TablePagination
      v-if="pagination"
      :pagination="pagination"
      @page-change="pageChange"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch, ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import { get } from 'lodash'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import TablePagination from './TablePagination.vue'
import { ContentPlaceholder, ContentPlaceholderText } from '../layout'
import SpinnerIcon from '@/scripts/components/icons/SpinnerIcon.vue'

export interface ColumnDef {
  key: string
  label: string
  thClass?: string
  defaultThClass?: string
  tdClass?: string
  defaultTdClass?: string
  placeholderClass?: string
  sortBy?: string
  sortable?: boolean
  /** Not a table column: only feeds the phone list (with a `mobile` role) */
  hidden?: boolean
  dataType?: string
  filterOn?: string
  /** 'end' right-aligns the column and sets its figures tabular (amounts, counts) */
  align?: 'start' | 'end'
  /**
   * Where the cell goes when the table renders as a list on phones. Columns
   * without a role are left out there; a table with no roles at all shows
   * its first column as the title.
   */
  mobile?: MobileRole | false
}

export type MobileRole = 'title' | 'subtitle' | 'trailing' | 'trailing-sub' | 'badge' | 'actions'

interface MobileColumns {
  title: ColumnDef | null
  subtitle: ColumnDef[]
  trailing: ColumnDef | null
  trailingSub: ColumnDef[]
  badge: ColumnDef | null
  actions: ColumnDef | null
}

interface TableColumn extends ColumnDef {
  sortable: boolean
  dataType: string
}

export interface RowData {
  id?: number | string
  [key: string]: unknown
}

interface TableRow {
  data: RowData
  columns: TableColumn[]
  getValue(columnName: string): unknown
  getColumn(columnName: string): TableColumn | undefined
  getSortableValue(columnName: string): string | number
}

export interface PaginationData {
  currentPage: number
  totalPages: number
  totalCount: number
  count: number
  limit: number
}

interface SortState {
  fieldName: string
  order: 'asc' | 'desc' | ''
}

type ServerDataFn = (params: { sort: SortState; page: number }) => Promise<{
  data: RowData[]
  pagination: PaginationData
}>

interface Props {
  columns: ColumnDef[]
  data: RowData[] | ServerDataFn
  sortBy?: string
  sortOrder?: string
  tableClass?: string
  theadClass?: string
  tbodyClass?: string
  noResultsMessage?: string
  loading?: boolean
  loadingType?: 'placeholder' | 'spinner'
  placeholderCount?: number
  /** Makes each row open this location; clicks on links, buttons and inputs inside a row still go to them */
  rowTo?: ((row: RowData) => RouteLocationRaw | null) | null
  /** Render as a table even on phones (for narrow tables that already fit) */
  keepTableOnPhone?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  sortBy: '',
  sortOrder: '',
  tableClass: 'min-w-full',
  theadClass: 'bg-surface-secondary border-b border-line-light',
  tbodyClass: '',
  noResultsMessage: 'No Results Found',
  loading: false,
  loadingType: 'placeholder',
  placeholderCount: 3,
  rowTo: null,
  keepTableOnPhone: false,
})

const router = useRouter()
const { isPhone } = useBreakpoints()

const isList = computed<boolean>(() => isPhone.value && !props.keepTableOnPhone)

const visibleColumns = computed<TableColumn[]>(() => tableColumns.filter((column) => !column.hidden))

const mobileColumns = computed<MobileColumns>(() => {
  const result: MobileColumns = {
    title: null,
    subtitle: [],
    trailing: null,
    trailingSub: [],
    badge: null,
    actions: null,
  }

  const annotated = tableColumns.filter((column) => column.mobile)

  // Unannotated tables: the first two labelled columns (selection columns
  // have no label) as title and subtitle, and the row menu if there is one.
  if (annotated.length === 0) {
    const labelled = tableColumns.filter((column) => column.label && column.key !== 'actions')
    result.title = labelled[0] ?? null
    result.subtitle = labelled.slice(1, 2)
    result.actions = tableColumns.find((column) => column.key === 'actions') ?? null
    return result
  }

  for (const column of annotated) {
    switch (column.mobile) {
      case 'title':
        result.title = column
        break
      case 'subtitle':
        result.subtitle.push(column)
        break
      case 'trailing':
        result.trailing = column
        break
      case 'trailing-sub':
        result.trailingSub.push(column)
        break
      case 'badge':
        result.badge = column
        break
      case 'actions':
        result.actions = column
        break
    }
  }

  return result
})

const INTERACTIVE = 'a, button, input, select, textarea, label, [role="menuitem"], [role="button"], [role="checkbox"]'

function onRowClick(row: TableRow, event: MouseEvent): void {
  if (!props.rowTo) {
    return
  }

  const target = event.target as HTMLElement | null

  if (target?.closest(INTERACTIVE) || window.getSelection()?.toString()) {
    return
  }

  const to = props.rowTo(row.data)

  if (to) {
    router.push(to)
  }
}

function ariaSort(column: TableColumn): 'ascending' | 'descending' | undefined {
  if (sort.fieldName !== column.key || !sort.order) {
    return undefined
  }

  return sort.order === 'asc' ? 'ascending' : 'descending'
}

function createColumn(columnObj: ColumnDef): TableColumn {
  const col: TableColumn = {
    ...columnObj,
    dataType: columnObj.dataType ?? 'string',
    sortable: columnObj.sortable ?? true,
  }
  return col
}

function createRow(data: RowData, columns: TableColumn[]): TableRow {
  return {
    data,
    columns,
    getValue(columnName: string): unknown {
      return getNestedValue(data, columnName)
    },
    getColumn(columnName: string): TableColumn | undefined {
      return columns.find((c) => c.key === columnName)
    },
    getSortableValue(columnName: string): string | number {
      const col = columns.find((c) => c.key === columnName)
      if (!col) return ''
      const dataType = col.dataType
      let value: unknown = getNestedValue(data, columnName)

      if (value === undefined || value === null) {
        return ''
      }

      if (typeof value === 'string') {
        value = value.toLowerCase()
      }

      if (dataType === 'numeric') {
        return value as number
      }

      return String(value)
    },
  }
}

function getNestedValue(object: unknown, path: string): unknown {
  if (!path) return object
  if (object === null || typeof object !== 'object') return object
  const [head, ...rest] = path.split('.')
  return getNestedValue((object as Record<string, unknown>)[head], rest.join('.'))
}

function getSortPredicate(
  column: TableColumn,
  sortOrder: string,
  allColumns: TableColumn[]
): (a: TableRow, b: TableRow) => number {
  const sortFieldName = column.sortBy || column.key
  const sortColumn = allColumns.find((c) => c.key === sortFieldName)
  if (!sortColumn) return () => 0
  const dataType = sortColumn.dataType

  if (dataType.startsWith('date') || dataType === 'numeric') {
    return (row1: TableRow, row2: TableRow) => {
      const value1 = row1.getSortableValue(sortFieldName)
      const value2 = row2.getSortableValue(sortFieldName)
      if (sortOrder === 'desc') {
        return value2 < value1 ? -1 : 1
      }
      return value1 < value2 ? -1 : 1
    }
  }

  return (row1: TableRow, row2: TableRow) => {
    const value1 = String(row1.getSortableValue(sortFieldName))
    const value2 = String(row2.getSortableValue(sortFieldName))
    if (sortOrder === 'desc') {
      return value2.localeCompare(value1)
    }
    return value1.localeCompare(value2)
  }
}

const rows = ref<TableRow[]>([])
const isLoading = ref<boolean>(false)

const tableColumns = reactive<TableColumn[]>(
  props.columns.map((column) => createColumn(column))
)

// Columns were read once at setup, so a caller whose set arrives later --
// the item list, whose custom-field columns are fetched -- never showed them.
// Keyed on the column set rather than watched deeply, so a re-rendered but
// unchanged list leaves the sort state alone.
watch(
  () => props.columns.map((column) => `${column.key}:${column.label ?? ''}`).join('|'),
  () => {
    tableColumns.splice(
      0,
      tableColumns.length,
      ...props.columns.map((column) => createColumn(column))
    )
  }
)

const sort = reactive<SortState>({
  fieldName: '',
  order: '',
})

const pagination = ref<PaginationData | null>(null)

const usesLocalData = computed<boolean>(() => {
  return Array.isArray(props.data)
})

const sortedRows = computed<TableRow[]>(() => {
  if (!usesLocalData.value) {
    return rows.value
  }

  if (sort.fieldName === '') {
    return rows.value
  }

  if (tableColumns.length === 0) {
    return rows.value
  }

  const sortColumn = tableColumns.find((c) => c.key === sort.fieldName)

  if (!sortColumn) {
    return rows.value
  }

  const sorted = [...rows.value].sort(
    getSortPredicate(sortColumn, sort.order, tableColumns)
  )

  return sorted
})

function getThClass(column: TableColumn): string {
  let classes =
    'whitespace-nowrap px-4 first:pl-6 last:pr-6 py-2.5 text-left text-xs font-medium text-muted select-none'

  if (column.align === 'end') {
    classes = `${classes} text-right`
  }

  if (column.defaultThClass) {
    classes = column.defaultThClass
  }

  if (column.sortable) {
    classes = `${classes} cursor-pointer`
  } else {
    classes = `${classes} pointer-events-none`
  }

  if (column.thClass) {
    classes = `${classes} ${column.thClass}`
  }

  return classes
}

function getTdClass(column: ColumnDef): string {
  let classes = 'px-4 first:pl-6 last:pr-6 py-3 text-sm text-body whitespace-nowrap'

  if (column.align === 'end') {
    classes = `${classes} text-right tabular`
  }

  if (column.defaultTdClass) {
    classes = column.defaultTdClass
  }

  if (column.tdClass) {
    classes = `${classes} ${column.tdClass}`
  }

  return classes
}

function getPlaceholderClass(column: ColumnDef): string {
  let classes = 'w-full'

  if (column.placeholderClass) {
    classes = `${classes} ${column.placeholderClass}`
  }

  return classes
}

function prepareLocalData(): RowData[] {
  pagination.value = null
  return props.data as RowData[]
}

async function fetchServerData(): Promise<RowData[] | null> {
  const page = pagination.value?.currentPage ?? 1

  isLoading.value = true

  const response = await (props.data as ServerDataFn)({
    sort,
    page,
  })

  isLoading.value = false

  const currentPage = pagination.value?.currentPage ?? 1
  if (page !== currentPage) {
    return null
  }

  pagination.value = response.pagination
  return response.data
}

function changeSorting(column: TableColumn): void {
  if (sort.fieldName !== column.key) {
    sort.fieldName = column.key
    sort.order = 'asc'
  } else {
    sort.order = sort.order === 'asc' ? 'desc' : 'asc'
  }

  if (!usesLocalData.value) {
    if (pagination.value) {
      pagination.value.currentPage = 1
    }
    mapDataToRows()
  }
}

async function mapDataToRows(): Promise<void> {
  let data: RowData[] | null

  if (usesLocalData.value) {
    data = prepareLocalData()
  } else {
    data = await fetchServerData()
    if (data === null) {
      return
    }
  }

  rows.value = data.map((rowData) => createRow(rowData, tableColumns))
}

async function pageChange(page: number): Promise<void> {
  if (pagination.value) {
    pagination.value.currentPage = page
  }
  await mapDataToRows()
}

async function refresh(isPreservePage = false): Promise<void> {
  if (pagination.value && !isPreservePage) {
    pagination.value.currentPage = 1
  }
  await mapDataToRows()
}

function lodashGet(obj: unknown, key: string): unknown {
  return get(obj, key)
}

watch(
  () => props.data,
  () => {
    mapDataToRows()
  },
  { deep: true }
)

onMounted(async () => {
  await mapDataToRows()
})

defineExpose({ refresh })
</script>
