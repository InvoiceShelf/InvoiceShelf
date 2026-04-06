<script setup lang="ts">
import { computed, reactive, ref, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@v2/stores/user.store'
import { useModalStore } from '@v2/stores/modal.store'
import { useItemStore } from '@v2/features/company/items/store'
import { ABILITIES } from '@v2/config/abilities'
import ItemModal from '@v2/features/company/items/components/ItemModal.vue'
import type { Item } from '@v2/types/domain'
import type { Tax } from '@v2/types/domain'

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
const itemData = reactive<LineItem>({ ...props.item })

async function searchItems(search: string): Promise<Item[]> {
  const res = await itemStore.fetchItems({ search })
  return res.data as unknown as Item[]
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
      data: {
        taxPerItem: props.taxPerItem,
        taxes: props.taxes,
        itemIndex: props.index,
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
    <ItemModal />

    <!-- Selected Item Field  -->
    <div
      v-if="item.item_id"
      class="
        relative
        flex
        items-center
        h-10
        pl-2
        bg-surface-muted
        border border-line-default border-solid
        rounded
      "
    >
      {{ item.name }}

      <span
        class="absolute text-subtle cursor-pointer top-[8px] right-[10px]"
        @click="deselectItem(index)"
      >
        <BaseIcon name="XCircleIcon" />
      </span>
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
      @update:modelValue="(val: Item) => $emit('select', val)"
      @searchChange="(val: string) => $emit('search', val)"
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
