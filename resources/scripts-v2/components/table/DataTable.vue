<template>
  <div class="flex flex-col">
    <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8 pb-4 lg:pb-0">
      <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
        <div
          class="
            relative
            overflow-hidden
            bg-surface/70 backdrop-blur-lg
            border border-white/15
            shadow-sm
            rounded-xl
          "
        >
          <slot name="header" />
          <table :class="tableClass">
            <thead :class="theadClass">
              <tr>
                <th
                  v-for="column in tableColumns"
                  :key="column.key"
                  :class="[
                    getThClass(column),
                    {
                      'text-bold text-heading': sort.fieldName === column.key,
                    },
                  ]"
                  @click="changeSorting(column)"
                >
                  {{ column.label }}
                  <span
                    v-if="sort.fieldName === column.key && sort.order === 'asc'"
                    class="asc-direction"
                  >
                    ↑
                  </span>
                  <span
                    v-if="
                      sort.fieldName === column.key && sort.order === 'desc'
                    "
                    class="desc-direction"
                  >
                    ↓
                  </span>
                </th>
              </tr>
            </thead>
            <tbody
              v-if="loadingType === 'placeholder' && (loading || isLoading)"
            >
              <tr
                v-for="placeRow in placeholderCount"
                :key="placeRow"
                :class="placeRow % 2 === 0 ? 'bg-surface' : 'bg-surface-secondary'"
              >
                <td
                  v-for="column in columns"
                  :key="column.key"
                  :class="getTdClass(column)"
                >
                  <ContentPlaceholder
                    :class="getPlaceholderClass(column)"
                    :rounded="true"
                  >
                    <ContentPlaceholderText
                      class="w-full h-6"
                      :lines="1"
                    />
                  </ContentPlaceholder>
                </td>
              </tr>
            </tbody>
            <tbody v-else>
              <tr
                v-for="(row, index) in sortedRows"
                :key="row.data?.id ?? index"
                :class="index % 2 === 0 ? 'bg-surface' : 'bg-surface-secondary'"
              >
                <td
                  v-for="column in columns"
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

          <div
            v-if="loadingType === 'spinner' && (loading || isLoading)"
            class="
              absolute
              top-0
              left-0
              z-10
              flex
              items-center
              justify-center
              w-full
              h-full
              bg-white/60
            "
          >
            <SpinnerIcon class="w-10 h-10 text-primary-500" />
          </div>

          <div
            v-else-if="
              !loading && !isLoading && sortedRows && sortedRows.length === 0
            "
            class="
              text-center text-muted
              pb-2
              flex
              h-[160px]
              justify-center
              items-center
              flex-col
            "
          >
            <BaseIcon
              name="ExclamationCircleIcon"
              class="w-6 h-6 text-subtle"
            />

            <span class="block mt-1">{{ $t('general.no_data_found') }}</span>
          </div>

          <TablePagination
            v-if="pagination"
            :pagination="pagination"
            @pageChange="pageChange"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch, ref, reactive } from 'vue'
import { get } from 'lodash'
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
  hidden?: boolean
  dataType?: string
  filterOn?: string
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
}

const props = withDefaults(defineProps<Props>(), {
  sortBy: '',
  sortOrder: '',
  tableClass: 'min-w-full divide-y divide-line-default',
  theadClass: 'bg-surface-secondary',
  tbodyClass: '',
  noResultsMessage: 'No Results Found',
  loading: false,
  loadingType: 'placeholder',
  placeholderCount: 3,
})

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
    'whitespace-nowrap px-6 py-3 text-left text-xs font-medium text-muted uppercase tracking-wider'

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
  let classes = 'px-6 py-4 text-sm text-muted whitespace-nowrap'

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
