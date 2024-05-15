<template>
  <BasePage>
    <!-- Page Header Section -->
    <BasePageHeader :title="$t('installers.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('installers.installer', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton
            v-show="installerStore.totalInstallers"
            variant="primary-outline"
            @click="toggleFilter"
          >
            {{ $t('general.filter') }}
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                name="FilterIcon"
                :class="slotProps.class"
              />
              <BaseIcon v-else name="XIcon" :class="slotProps.class" />
            </template>
          </BaseButton>

          <BaseButton
            v-if="userStore.hasAbilities(abilities.CREATE_INSTALLER)"
            @click="$router.push('installers/create')"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('installers.new_installer') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" class="mt-5" @clear="clearFilter">
      <BaseInputGroup :label="$t('installers.display_name')" class="text-left">
        <BaseInput
          v-model="filters.display_name"
          type="text"
          name="name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('installers.contact_name')" class="text-left">
        <BaseInput
          v-model="filters.contact_name"
          type="text"
          name="address_name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('installers.phone')" class="text-left">
        <BaseInput
          v-model="filters.phone"
          type="text"
          name="phone"
          autocomplete="off"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('installers.no_installers')"
      :description="$t('installers.list_of_installers')"
    >
      <AstronautIcon class="mt-5 mb-4" />

      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(abilities.CREATE_INSTALLER)"
          variant="primary-outline"
          @click="$router.push('/admin/installers/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('installers.add_new_installer') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <!-- Total no of Installers in Table -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <div class="relative flex items-center justify-end h-5">
        <BaseDropdown v-if="installerStore.selectedInstallers.length">
          <template #activator>
            <span
              class="
                flex
                text-sm
                font-medium
                cursor-pointer
                select-none
                text-primary-400
              "
            >
              {{ $t('general.actions') }}

              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>
          <BaseDropdownItem @click="removeMultipleInstallers">
            <BaseIcon name="TrashIcon" class="mr-3 text-gray-600" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <!-- Table Section -->
      <BaseTable
        ref="tableComponent"
        class="mt-3"
        :data="fetchData"
        :columns="installerColumns"
      >
        <!-- Select All Checkbox -->
        <template #header>
          <div class="absolute z-10 items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="selectAllFieldStatus"
              variant="primary"
              @change="installerStore.selectAllInstallers"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.data.id"
              v-model="selectField"
              :value="row.data.id"
              variant="primary"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <router-link :to="{ path: `installers/${row.data.id}/view` }">
            <BaseText
              :text="row.data.name"
              :length="30"
              tag="span"
              class="font-medium text-primary-500 flex flex-col"
            />
            <BaseText
              :text="row.data.contact_name ? row.data.contact_name : ''"
              :length="30"
              tag="span"
              class="text-xs text-gray-400"
            />
          </router-link>
        </template>

        <template #cell-phone="{ row }">
          <span>
            {{ row.data.phone ? row.data.phone : '-' }}
          </span>
        </template>

        <!-- <template #cell-due_amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.due_amount || 0"
            :currency="row.data.currency"
          />
        </template> -->

        <!-- <template #cell-address="{ row }">
          <span>{{ row.data.phone ? row.data.phone : '-' }}</span>                      
        </template> -->

        <template #cell-created_at="{ row }">
          <span>{{ row.data.formatted_created_at }}</span>
        </template>

        <template v-if="hasAtleastOneAbility()" #cell-actions="{ row }">
          <InstallerDropdown
            :row="row.data"
            :table="tableComponent"
            :load-data="refreshTable"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { debouncedWatch } from '@vueuse/core'
import moment from 'moment'
import { reactive, ref, inject, computed, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useInstallerStore } from '@/scripts/admin/stores/installer'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'

import abilities from '@/scripts/admin/stub/abilities'

import InstallerDropdown from '@/scripts/admin/components/dropdowns/InstallerIndexDropdown.vue'
import AstronautIcon from '@/scripts/components/icons/empty/AstronautIcon.vue'

const companyStore = useCompanyStore()
const dialogStore = useDialogStore()
const installerStore = useInstallerStore()
const userStore = useUserStore()

let tableComponent = ref(null)
let showFilters = ref(false)
let isFetchingInitialData = ref(true)
const { t } = useI18n()

let filters = reactive({
  display_name: '',
  contact_name: '',
  phone: '',
})

const showEmptyScreen = computed(
  () => !installerStore.totalInstallers && !isFetchingInitialData.value
)

const selectField = computed({
  get: () => installerStore.selectedInstallers,
  set: (value) => {
    return installerStore.selectInstaller(value)
  },
})

const selectAllFieldStatus = computed({
  get: () => installerStore.selectAllField,
  set: (value) => {
    return installerStore.setSelectAllState(value)
  },
})

const installerColumns = computed(() => {
  return [
    {
      key: 'status',
      thClass: 'extra w-10 pr-0',
      sortable: false,
      tdClass: 'font-medium text-gray-900 pr-0',
    },
    {
      key: 'name',
      label: t('installers.name'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    { key: 'phone', label: t('installers.phone') },
    //{ key: 'due_amount', label: t('installers.amount_due') },
    {
      key: 'created_at',
      label: t('items.added_on'),
    },
    {
      key: 'actions',
      tdClass: 'text-right text-sm font-medium pl-0',
      thClass: 'pl-0',
      sortable: false,
    },
  ]
})

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

onUnmounted(() => {
  if (installerStore.selectAllField) {
    installerStore.selectAllInstallers()
  }
})

function refreshTable() {
  tableComponent.value.refresh()
}

function setFilters() {
  refreshTable()
}

function hasAtleastOneAbility() {
  return userStore.hasAbilities([
    abilities.DELETE_INSTALLER,
    abilities.EDIT_INSTALLER,
    abilities.VIEW_INSTALLER,
  ])
}

async function fetchData({ page, filter, sort }) {
  let data = {
    display_name: filters.display_name,
    contact_name: filters.contact_name,
    phone: filters.phone,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true
  let response = await installerStore.fetchInstallers(data)
  isFetchingInitialData.value = false
  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta.last_page,
      currentPage: page,
      totalCount: response.data.meta.total,
      limit: 10,
    },
  }
}

function clearFilter() {
  filters.display_name = ''
  filters.contact_name = ''
  filters.phone = ''
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}

let date = ref(new Date())

date.value = moment(date).format('YYYY-MM-DD')

function removeMultipleInstallers() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('installers.confirm_delete', 2),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        installerStore.deleteMultipleInstallers().then((response) => {
          if (response.data) {
            refreshTable()
          }
        })
      }
    })
}
</script>
