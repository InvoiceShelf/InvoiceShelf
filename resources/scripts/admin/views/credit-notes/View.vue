<template>
  <SendCreditNoteModal />
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(abilities.SEND_CREDIT_NOTE)"
          :content-loading="isFetching"
          variant="primary"
          @click="onCreditNoteSend"
        >
          {{ $t('credit_notes.send_credit_note') }}
        </BaseButton>

        <CreditNoteDropdown
          :content-loading="isFetching"
          class="ml-3"
          :row="creditNote"
        />
      </template>
    </BasePageHeader>

    <!-- Sidebar -->
    <div
      class="
        fixed
        top-0
        left-0
        hidden
        h-full
        pt-16
        pb-[6rem]
        ml-56
        bg-white
        xl:ml-64
        w-88
        xl:block
      "
    >
      <div
        class="
          flex
          items-center
          justify-between
          px-4
          pt-8
          pb-6
          border border-gray-200 border-solid
        "
      >
        <BaseInput
          v-model="searchData.searchText"
          :placeholder="$t('general.search')"
          type="text"
          @input="onSearch"
        >
          <BaseIcon name="MagnifyingGlassIcon" class="h-5" />
        </BaseInput>

        <div class="flex ml-3" role="group" aria-label="First group">
          <BaseDropdown
            position="bottom-start"
            width-class="w-50"
            position-class="left-0"
          >
            <template #activator>
              <BaseButton variant="gray">
                <BaseIcon name="FunnelIcon" />
              </BaseButton>
            </template>

            <div
              class="
                px-4
                py-1
                pb-2
                mb-2
                text-sm
                border-b border-gray-200 border-solid
              "
            >
              {{ $t('general.sort_by') }}
            </div>

            <div class="px-2">
              <BaseDropdownItem class="pt-3 rounded-md hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_invoice_number"
                    v-model="searchData.orderByField"
                    :label="$t('invoices.title')"
                    size="sm"
                    name="filter"
                    value="invoice_number"
                    @update:modelValue="onSearch"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="pt-3 rounded-md hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    v-model="searchData.orderByField"
                    :label="$t('credit_notes.date')"
                    size="sm"
                    name="filter"
                    value="credit_note_date"
                    @update:modelValue="onSearch"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="pt-3 rounded-md hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_credit_note_number"
                    v-model="searchData.orderByField"
                    :label="$t('credit_notes.credit_note_number')"
                    size="sm"
                    name="filter"
                    value="credit_note_number"
                    @update:modelValue="onSearch"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="SortAscendingIcon" />
            <BaseIcon v-else name="SortDescendingIcon" />
          </BaseButton>
        </div>
      </div>

      <div
        ref="creditNoteListSection"
        class="h-full overflow-y-scroll border-l border-gray-200 border-solid"
      >
        <div v-for="(creditNote, index) in creditNoteList" :key="index">
          <router-link
            v-if="creditNote"
            :id="'creditNote-' + creditNote.id"
            :to="`/admin/credit-notes/${creditNote.id}/view`"
            :class="[
              'flex justify-between p-4 items-center cursor-pointer hover:bg-gray-100 border-l-4 border-transparent',
              {
                'bg-gray-100 border-l-4 border-primary-500 border-solid':
                  hasActiveUrl(creditNote.id),
              },
            ]"
            style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
          >
            <div class="flex-2">
              <BaseText
                :text="creditNote?.customer?.name"
                class="
                  pr-2
                  mb-2
                  text-sm
                  not-italic
                  font-normal
                  leading-5
                  text-black
                  capitalize
                  truncate
                "
              />

              <div
                class="
                  mb-1
                  text-xs
                  not-italic
                  font-medium
                  leading-5
                  text-gray-500
                  capitalize
                "
              >
                {{ creditNote?.credit_note_number }}
              </div>

              <div
                class="
                  mb-1
                  text-xs
                  not-italic
                  font-medium
                  leading-5
                  text-gray-500
                  capitalize
                "
              >
                {{ creditNote?.invoice_number }}
              </div>
            </div>

            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                class="
                  block
                  mb-2
                  text-xl
                  not-italic
                  font-semibold
                  leading-8
                  text-right text-gray-900
                "
                :amount="creditNote?.amount"
                :currency="creditNote.customer?.currency"
              />

              <div class="text-sm text-right text-gray-500 non-italic">
                {{ creditNote.formatted_credit_note_date }}
              </div>
            </div>
          </router-link>
        </div>
        <div v-if="isLoading" class="flex justify-center p-4 items-center">
          <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p
          v-if="!creditNoteList?.length && !isLoading"
          class="flex justify-center px-4 mt-5 text-sm text-gray-600"
        >
          {{ $t('credit_notes.no_matching_credit_notes') }}
        </p>
      </div>
    </div>

    <!-- pdf -->
    <div
      class="flex flex-col min-h-0 mt-8 overflow-hidden"
      style="height: 75vh"
    >
      <iframe
        v-if="shareableLink"
        :src="shareableLink"
        class="flex-1 border border-gray-400 border-solid rounded-md"
      />
    </div>
  </BasePage>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { debounce } from 'lodash'
import { ref, reactive, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import moment from 'moment'

import { useDialogStore } from '@/scripts/stores/dialog'
import { useCreditNoteStore } from '@/scripts/admin/stores/credit-note'
import { useModalStore } from '@/scripts/stores/modal'
import { useUserStore } from '@/scripts/admin/stores/user'

import CreditNoteDropdown from '@/scripts/admin/components/dropdowns/CreditNoteIndexDropdown.vue'
import SendCreditNoteModal from '@/scripts/admin/components/modal-components/SendCreditNoteModal.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'

import abilities from '@/scripts/admin/stub/abilities'

const route = useRoute()

const { t } = useI18n()
let creditNote = reactive({})
let searchData = reactive({
  orderBy: null,
  orderByField: null,
  searchText: null,
})

let isLoading = ref(false)
let isFetching = ref(false)

const creditNoteStore = useCreditNoteStore()
const modalStore = useModalStore()
const userStore = useUserStore()

const creditNoteList = ref(null)
const currentPageNumber = ref(1)
const lastPageNumber = ref(1)
const creditNoteListSection = ref(null)

const pageTitle = computed(() => {
  return creditNote.credit_note_number || ''
})

const getOrderBy = computed(() => {
  if (searchData.orderBy === 'asc' || searchData.orderBy == null) {
    return true
  }
  return false
})

const getOrderName = computed(() =>
  getOrderBy.value ? t('general.ascending') : t('general.descending')
)

const shareableLink = computed(() => {
  return creditNote.unique_hash ? `/credit-notes/pdf/${creditNote.unique_hash}` : false
})

const creditNoteDate = computed(() => {
  return moment(creditNoteStore?.selectedCreditNote?.credit_note_date).format(
    'YYYY/MM/DD'
  )
})

watch(route, () => {
  loadCreditNote()
})

loadCreditNotes()
loadCreditNote()

onSearch = debounce(onSearch, 500)

function hasActiveUrl(id) {
  return route.params.id == id
}

function hasAbilities() {
  return userStore.hasAbilities([
    abilities.DELETE_CREDIT_NOTE,
    abilities.EDIT_CREDIT_NOTE,
    abilities.VIEW_CREDIT_NOTE,
  ])
}

const dialogStore = useDialogStore()

async function loadCreditNotes(pageNumber, fromScrollListener = false) {
  if (isLoading.value) {
    return
  }

  let params = {}
  if (
    searchData.searchText !== '' &&
    searchData.searchText !== null &&
    searchData.searchText !== undefined
  ) {
    params.search = searchData.searchText
  }

  if (searchData.orderBy !== null && searchData.orderBy !== undefined) {
    params.orderBy = searchData.orderBy
  }

  if (
    searchData.orderByField !== null &&
    searchData.orderByField !== undefined
  ) {
    params.orderByField = searchData.orderByField
  }

  isLoading.value = true
  let response = await creditNoteStore.fetchCreditNotes({
    page: pageNumber,
    ...params,
  })
  isLoading.value = false

  creditNoteList.value = creditNoteList.value ? creditNoteList.value : []
  creditNoteList.value = [...creditNoteList.value, ...response.data.data]

  currentPageNumber.value = pageNumber ? pageNumber : 1
  lastPageNumber.value = response.data.meta.last_page
  let creditNoteFound = creditNoteList.value.find(
    (cred) => cred.id == route.params.id
  )

  if (
    fromScrollListener == false &&
    !creditNoteFound &&
    currentPageNumber.value < lastPageNumber.value &&
    Object.keys(params).length === 0
  ) {
    loadCreditNotes(++currentPageNumber.value)
  }

  if (creditNoteFound) {
    setTimeout(() => {
      if (fromScrollListener == false) {
        scrollToCreditNote()
      }
    }, 500)
  }
}

async function loadCreditNote() {
  if (!route.params.id) return

  isFetching.value = true

  let response = await creditNoteStore.fetchCreditNote(route.params.id)

  if (response.data) {
    isFetching.value = false
    Object.assign(creditNote, response.data.data)
  }
}

function scrollToCreditNote() {
  const el = document.getElementById(`credit-note-${route.params.id}`)

  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener() {
  creditNoteListSection.value.addEventListener('scroll', (ev) => {
    if (
      ev.target.scrollTop > 0 &&
      ev.target.scrollTop + ev.target.clientHeight >
        ev.target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadCreditNotes(++currentPageNumber.value, true)
      }
    }
  })
}

async function onSearch() {
  creditNoteList.value = []
  loadCreditNotes()
}

function sortData() {
  if (searchData.orderBy === 'asc') {
    searchData.orderBy = 'desc'
    onSearch()
    return true
  }
  searchData.orderBy = 'asc'
  onSearch()
  return true
}

async function onCreditNoteSend() {
  modalStore.openModal({
    title: t('credit_notes.send_credit_note'),
    componentName: 'SendCreditNoteModal',
    id: creditNote.id,
    data: creditNote,
    variant: 'lg',
  })
}
</script>
