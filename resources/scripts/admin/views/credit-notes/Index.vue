<template>
  <BasePage class="credit-notes">
    <SendCreditNoteModal />
    <BasePageHeader :title="$t('credit_notes.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('credit_notes.credit_note', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="creditNoteStore.creditNoteTotalCount"
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
          v-if="userStore.hasAbilities(abilities.CREATE_CREDIT_NOTE)"
          variant="primary"
          class="ml-4"
          @click="$router.push('/admin/credit-notes/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>

          {{ $t('credit_notes.add_credit_note') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" class="mt-3" @clear="clearFilter">
      <BaseInputGroup :label="$t('credit_notes.customer')">
        <BaseCustomerSelectInput
          v-model="filters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('credit_notes.credit_note_number')">
        <BaseInput v-model="filters.credit_note_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>

    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-if="showEmptyScreen"
      :title="$t('credit_notes.no_credit_notes')"
      :description="$t('credit_notes.list_of_credit_notes')"
    >
      <CapsuleIcon class="mt-5 mb-4" />

      <template
        v-if="userStore.hasAbilities(abilities.CREATE_CREDIT_NOTE)"
        #actions
      >
        <BaseButton
          variant="primary-outline"
          @click="$router.push('/admin/credit-notes/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('credit_notes.add_new_credit_note') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <!-- Multiple Select Actions -->
      <div class="relative flex items-center justify-end h-5">
        <BaseDropdown v-if="creditNoteStore.selectedCreditNotes.length">
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
          <BaseDropdownItem @click="removeMultipleCreditNotes">
            <BaseIcon name="TrashIcon" class="mr-3 text-gray-600" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableComponent"
        :data="fetchData"
        :columns="creditNoteColumns"
        :placeholder-count="creditNoteStore.creditNoteTotalCount >= 20 ? 10 : 5"
        class="mt-3"
      >
        <!-- Select All Checkbox -->
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="selectAllFieldStatus"
              variant="primary"
              @change="creditNoteStore.selectAllCreditNotes"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
              variant="primary"
            />
          </div>
        </template>

        <template #cell-credit_note_date="{ row }">
          {{ row.data.formatted_credit_note_date }}
        </template>

        <template #cell-credit_note_number="{ row }">
          <router-link
            :to="{ path: `credit-notes/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.credit_note_number }}
          </router-link>
        </template>

        <template #cell-name="{ row }">
          <BaseText :text="row.data.customer.name" tag="span" />
        </template>

        <template #cell-invoice_number="{ row }">
          <span>
            {{
              row?.data?.invoice?.invoice_number
                ? row?.data?.invoice?.invoice_number
                : '-'
            }}
          </span>
        </template>

        <template #cell-amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.amount"
            :currency="row.data.customer.currency"
          />
        </template>

        <template v-if="hasAtleastOneAbility()" #cell-actions="{ row }">
          <CreditNoteDropdown :row="row.data" :table="tableComponent" />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { debouncedWatch } from '@vueuse/core'

import { ref, reactive, computed, onUnmounted } from 'vue'

import { useI18n } from 'vue-i18n'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useCreditNoteStore } from '@/scripts/admin/stores/credit-note'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'
import CapsuleIcon from '@/scripts/components/icons/empty/CapsuleIcon.vue'
import CreditNoteDropdown from '@/scripts/admin/components/dropdowns/CreditNoteIndexDropdown.vue'
import SendCreditNoteModal from '@/scripts/admin/components/modal-components/SendCreditNoteModal.vue'

const { t } = useI18n()
let showFilters = ref(false)
let isFetchingInitialData = ref(true)
let tableComponent = ref(null)

const filters = reactive({
  customer: '',
  credit_note_number: '',
})

const creditNoteStore = useCreditNoteStore()
const companyStore = useCompanyStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const showEmptyScreen = computed(() => {
  return !creditNoteStore.creditNoteTotalCount && !isFetchingInitialData.value
})

const creditNoteColumns = computed(() => {
  return [
    {
      key: 'status',
      sortable: false,
      thClass: 'extra w-10',
      tdClass: 'text-left text-sm font-medium extra',
    },
    {
      key: 'credit_note_date',
      label: t('credit_notes.date'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    { key: 'credit_note_number', label: t('credit_notes.credit_note_number') },
    { key: 'name', label: t('credit_notes.customer') },
    { key: 'invoice_number', label: t('credit_notes.invoice') },
    { key: 'amount', label: t('credit_notes.amount') },
    {
      key: 'actions',
      label: '',
      tdClass: 'text-right text-sm font-medium',
      sortable: false,
    },
  ]
})

const selectField = computed({
  get: () => creditNoteStore.selectedCreditNotes,
  set: (value) => {
    return creditNoteStore.selectCreditNote(value)
  },
})

const selectAllFieldStatus = computed({
  get: () => creditNoteStore.selectAllField,
  set: (value) => {
    return creditNoteStore.setSelectAllState(value)
  },
})

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

onUnmounted(() => {
  if (creditNoteStore.selectAllField) {
    creditNoteStore.selectAllCreditNotes()
  }
})

function hasAtleastOneAbility() {
  return userStore.hasAbilities([
    abilities.DELETE_CREDIT_NOTE,
    abilities.EDIT_CREDIT_NOTE,
    abilities.VIEW_CREDIT_NOTE,
    abilities.SEND_CREDIT_NOTE,
  ])
}

async function fetchData({ page, filter, sort }) {
  let data = {
    customer_id: filters.customer_id,
    credit_note_number: filters.credit_note_number,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true

  let response = await creditNoteStore.fetchCreditNotes(data)

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

function refreshTable() {
  tableComponent.value && tableComponent.value.refresh()
}

function setFilters() {
  refreshTable()
}

function clearFilter() {
  filters.customer_id = ''
  filters.credit_note_number = ''
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}

function removeMultipleCreditNotes() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('credit_notes.confirm_delete', 2),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        creditNoteStore.deleteMultipleCreditNotes().then((response) => {
          if (response.data.success) {
            refreshTable()
          }
        })
      }
    })
}
</script>
