<script setup lang="ts">
import { computed, reactive, ref, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/scripts/stores/user.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useItemStore } from '@/scripts/features/company/items/store'
import { ABILITIES } from '@/scripts/config/abilities'
import type { Item } from '@/scripts/types/domain'
import type { Tax } from '@/scripts/types/domain'

interface LineItem {
  item_id: number | null
  name: string
  description: string | null
  [key: string]: unknown
}

interface Props {
  contentLoading?: boolean
  type?: string | null
  item: LineItem
  index?: number
  invalid?: boolean
  invalidDescription?: boolean
  taxPerItem?: string
  taxes?: Tax[] | null
  store?: { deselectItem: (index: number) => void } | null
  storeProp?: string
}

interface Emits {
  (e: 'search', val: string): void
  (e: 'select', val: Item): void
  (e: 'deselect', index: number): void
  (e: 'update:description', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  contentLoading: false,
  type: null,
  index: 0,
  invalid: false,
  invalidDescription: false,
  taxPerItem: '',
  taxes: null,
  store: null,
  storeProp: '',
})

const emit = defineEmits<Emits>()

const userStore = useUserStore()
const modalStore = useModalStore()
const itemStore = useItemStore()
const { t } = useI18n()

const itemSelect = ref<Item | null>(null)
const multiselectRef = ref<{ close?: () => void } | null>(null)
const loading = ref<boolean>(false)
const searchQuery = ref<string>('')
const itemData = reactive<LineItem>({ ...props.item })

async function searchItems(search: string): Promise<Item[]> {
  const res = await itemStore.fetchItems({ search })
  return res.data as unknown as Item[]
}

function onSearchChange(val: string): void {
  searchQuery.value = val
  emit('search', val)
}

const description = computed<string | null>({
  get: () => props.item.description,
  set: (value: string | null) => {
    emit('update:description', value ?? '')
  },
})

function openItemModal(): void {
  // Close the multiselect dropdown before opening the modal
  ;(document.activeElement as HTMLElement)?.blur()

  nextTick(() => {
    modalStore.openModal({
      title: t('items.add_item'),
      componentName: 'ItemModal',
      refreshData: (val: Item) => emit('select', val),
      // ItemModal owns its own form; hand it the typed search text to pre-fill the name.
      data: {
        name: searchQuery.value,
      },
    })
  })
}

function deselectItem(index: number): void {
  if (props.store) {
    props.store.deselectItem(index)
  }
  emit('deselect', index)
}
</script>

<template>
  <div class="flex-1 text-sm">
    <!-- Selected Item Field  -->
    <div
      v-if="item.item_id"
      class="relative flex items-center h-11 pl-3 pr-11 font-medium border rounded-lg md:h-10 bg-surface-muted border-line-default text-heading"
    >
      <span class="truncate">{{ item.name }}</span>

      <button
        type="button"
        class="absolute inset-y-0 right-0 flex items-center justify-center w-10 rounded-r-lg text-subtle hover:text-heading"
        :aria-label="$t('general.deselect')"
        @click="deselectItem(index)"
      >
        <BaseIcon name="XCircleIcon" class="w-5 h-5" />
      </button>
    </div>

    <!-- Select Item Field -->
    <BaseMultiselect
      v-else
      ref="multiselectRef"
      v-model="itemSelect"
      :content-loading="contentLoading"
      value-prop="id"
      track-by="name"
      :invalid="invalid"
      preserve-search
      :initial-search="itemData.name"
      label="name"
      :filter-results="false"
      resolve-on-load
      :delay="500"
      searchable
      :options="searchItems"
      object
      @update:model-value="(val: Item) => $emit('select', val)"
      @search-change="onSearchChange"
    >
      <!-- Add Item Action  -->
      <template #action>
        <BaseSelectAction
          v-if="userStore.hasAbilities(ABILITIES.CREATE_ITEM)"
          @click="openItemModal"
        >
          <BaseIcon
            name="PlusCircleIcon"
            class="h-4 mr-2 -ml-2 text-center text-primary-400"
          />
          {{ $t('general.add_new_item') }}
        </BaseSelectAction>
      </template>
    </BaseMultiselect>

    <!-- Item Description  -->
    <div class="w-full pt-1 text-xs text-light">
      <BaseTextarea
        v-model="description"
        :content-loading="contentLoading"
        :autosize="true"
        class="text-xs"
        :borderless="true"
        :placeholder="$t('estimates.item.type_item_description')"
        :invalid="invalidDescription"
      />
      <div v-if="invalidDescription">
        <span class="text-red-600">
          {{ $t('validation.description_maxlength') }}
        </span>
      </div>
    </div>
  </div>
</template>
