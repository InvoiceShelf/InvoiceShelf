<script setup lang="ts">
import type { ColumnDef } from '@/scripts/components/table/DataTable.vue'
import { ref, computed, reactive, onUnmounted } from 'vue'
import { debouncedWatch } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useItemStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'
import ItemDropdown from '../components/ItemDropdown.vue'
import { useCustomFieldDefinitions } from '@/scripts/features/shared/custom-fields/use-custom-fields'

type TableColumn = Omit<ColumnDef, 'label'> & { label?: string }

interface FetchParams {
  page: number
  filter: Record<string, unknown>
  sort: { fieldName: string; order: string }
}

interface FetchResult {
  data: unknown[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

interface ItemFilters {
  name: string
  unit_id: string | number
  price: string | number
}

const ABILITIES = {
  CREATE_ITEM: 'create-item',
  DELETE_ITEM: 'delete-item',
  EDIT_ITEM: 'edit-item',
} as const

const itemStore = useItemStore()
const companyStore = useCompanyStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const { t } = useI18n()
const showFilters = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(true)

const filters = reactive<ItemFilters>({
  name: '',
  unit_id: '',
  price: '',
})

const table = ref<{ refresh: () => void } | null>(null)

const showEmptyScreen = computed<boolean>(
  () => !itemStore.totalItems && !isFetchingInitialData.value
)

const selectField = computed<number[]>({
  get: () => itemStore.selectedItems,
  set: (value: number[]) => {
    itemStore.selectItem(value)
  },
})

/**
 * Item definitions the author marked as printed also become columns here.
 * Reusing that flag rather than adding a second one keeps "where does this
 * field show up" a single decision: it is on the document and in the list,
 * or it is neither.
 */
const itemCustomFields = useCustomFieldDefinitions('Item')

const printedFields = computed(() =>
  itemCustomFields.value.filter((field) => field.placement === 'document')
)

/** The answer this row holds for a definition, already formatted. */
function customFieldAnswer(row: Record<string, any>, id: number): string {
  const answer = (row.fields ?? []).find(
    (value: Record<string, any>) => value.custom_field_id === id
  )

  return answer?.default_formatted_answer ?? ''
}

const itemColumns = computed<TableColumn[]>(() => [
  {
    key: 'status',
    thClass: 'extra w-10',
    tdClass: 'font-medium text-heading',
    placeholderClass: 'w-10',
    sortable: false,
  },
  {
    key: 'name',
    label: t('items.name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
    mobile: 'title',
  },
  { key: 'unit_name', label: t('items.unit'), mobile: 'subtitle' },
  { key: 'price', label: t('items.price'), align: 'end', mobile: 'trailing' },
  { key: 'created_at', label: t('items.added_on') },
  ...printedFields.value.map((field) => ({
    key: `custom_field_${field.id}`,
    label: field.label,
    sortable: false,
  })),
  {
    key: 'actions',
    thClass: 'text-right',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
    mobile: 'actions',
  },
])

function itemLink(row: { id?: number | string }): string {
  return `/admin/items/${row.id}/edit`
}

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

itemStore.fetchItemUnits({ limit: 'all' })

onUnmounted(() => {
  if (itemStore.selectAllField) {
    itemStore.selectAllItems()
  }
})

function clearFilter(): void {
  filters.name = ''
  filters.unit_id = ''
  filters.price = ''
}

function hasAbilities(): boolean {
  return userStore.hasAbilities([ABILITIES.DELETE_ITEM, ABILITIES.EDIT_ITEM])
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

function refreshTable(): void {
  table.value?.refresh()
}

function setFilters(): void {
  refreshTable()
}

async function searchUnits(search: string): Promise<unknown[]> {
  const res = await itemStore.fetchItemUnits({ search })
  return res.data.data
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    search: filters.name,
    unit_id: filters.unit_id !== null ? filters.unit_id : '',
    price: Math.round(Number(filters.price) * 100),
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true
  const response = await itemStore.fetchItems(data)
  isFetchingInitialData.value = false

  return {
    data: response.data,
    pagination: {
      totalPages: response.meta.last_page,
      currentPage: page,
      totalCount: response.meta.total,
      limit: 10,
    },
  }
}

function removeMultipleItems(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('items.confirm_delete', 2),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res: boolean) => {
      if (res) {
        itemStore.deleteMultipleItems().then((response) => {
          if (response.success) {
            table.value?.refresh()
          }
        })
      }
    })
}
</script>

<template>
  <BasePage>
    <BasePageHeader :title="$t('items.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('items.item', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton
            v-show="itemStore.totalItems"
            variant="primary-outline"
            @click="toggleFilter"
          >
            {{ $t('general.filter') }}
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                :class="slotProps.class"
                name="FunnelIcon"
              />
              <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
            </template>
          </BaseButton>

          <BaseButton
            v-if="userStore.hasAbilities(ABILITIES.CREATE_ITEM)"
            @click="$router.push('/admin/items/create')"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('items.add_item') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" @clear="clearFilter">
      <BaseInputGroup :label="$t('items.name')" class="text-left">
        <BaseInput
          v-model="filters.name"
          type="text"
          name="name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('items.unit')" class="text-left">
        <BaseMultiselect
          v-model="filters.unit_id"
          :placeholder="$t('items.select_a_unit')"
          value-prop="id"
          track-by="name"
          :filter-results="false"
          label="name"
          resolve-on-load
          :delay="500"
          searchable
          class="w-full"
          :options="searchUnits"
        />
      </BaseInputGroup>

      <BaseInputGroup class="text-left" :label="$t('items.price')">
        <BaseMoney v-model="filters.price" />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      icon="CubeIcon"
      :title="$t('items.no_items')"
      :description="$t('items.list_of_items')"
    >
      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(ABILITIES.CREATE_ITEM)"
          variant="primary-outline"
          @click="$router.push('/admin/items/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('items.add_new_item') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="itemColumns"
        :placeholder-count="itemStore.totalItems >= 20 ? 10 : 5"
        :row-to="itemLink"
        :selected-count="
          userStore.hasAbilities(ABILITIES.DELETE_ITEM)
            ? itemStore.selectedItems.length
            : 0
        "
      >
        <template #bulk-actions>
          <BaseButton size="xs" variant="white" @click="removeMultipleItems">
            <template #left="slotProps">
              <BaseIcon name="TrashIcon" :class="slotProps.class" />
            </template>
            {{ $t('general.delete') }}
          </BaseButton>
        </template>

        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="itemStore.selectAllField"
              variant="primary"
              @change="itemStore.selectAllItems"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <router-link
            :to="{ path: `items/${row.data.id}/edit` }"
            class="font-medium text-heading hover:text-primary-600"
          >
            <BaseText :text="row.data.name" />
          </router-link>
        </template>

        <template #cell-unit_name="{ row }">
          <span>
            {{ row.data.unit ? row.data.unit.name : '-' }}
          </span>
        </template>

        <template #cell-price="{ row }">
          <BaseFormatMoney
            :amount="row.data.price"
            :currency="companyStore.selectedCompanyCurrency"
          />
        </template>

        <template
          v-for="field in printedFields"
          :key="field.id"
          #[`cell-custom_field_${field.id}`]="{ row }"
        >
          <span>{{ customFieldAnswer(row.data, field.id) }}</span>
        </template>

        <template #cell-created_at="{ row }">
          <span>{{ row.data.formatted_created_at }}</span>
        </template>

        <template v-if="hasAbilities()" #cell-actions="{ row }">
          <ItemDropdown
            :row="row.data"
            :table="table"
            :load-data="refreshTable"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>
