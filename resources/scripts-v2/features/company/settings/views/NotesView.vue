<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { useUserStore } from '../../../../stores/user.store'
import { noteService } from '../../../../api/services/note.service'
import NoteDropdown from '@v2/features/company/settings/components/NoteDropdown.vue'
import NoteModal from '@v2/features/company/settings/components/NoteModal.vue'

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

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

const ABILITIES = {
  MANAGE_NOTE: 'manage-note',
} as const

const { t } = useI18n()
const modalStore = useModalStore()
const userStore = useUserStore()

const table = ref<{ refresh: () => void } | null>(null)

const notesColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('settings.customization.notes.name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading flex gap-1 items-center',
  },
  {
    key: 'type',
    label: t('settings.customization.notes.type'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'actions',
    label: '',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  const response = await noteService.list(data)

  return {
    data: (response as Record<string, unknown>).data as unknown[],
    pagination: {
      totalPages: ((response as Record<string, unknown>).meta as Record<string, number>).last_page,
      currentPage: page,
      totalCount: ((response as Record<string, unknown>).meta as Record<string, number>).total,
      limit: 5,
    },
  }
}

function openNoteSelectModal(): void {
  modalStore.openModal({
    title: t('settings.customization.notes.add_note'),
    componentName: 'NoteModal',
    size: 'md',
    refreshData: table.value?.refresh,
  })
}

function refreshTable(): void {
  table.value?.refresh()
}

function getLabelNote(type: string): string {
  switch (type) {
    case 'Estimate':
      return t('settings.customization.notes.types.estimate')
    case 'Invoice':
      return t('settings.customization.notes.types.invoice')
    case 'Payment':
      return t('settings.customization.notes.types.payment')
    default:
      return type
  }
}
</script>

<template>
  <NoteModal />

  <BaseSettingCard
    :title="$t('settings.customization.notes.title')"
    :description="$t('settings.customization.notes.description')"
  >
    <template #action>
      <BaseButton
        v-if="userStore.hasAbilities(ABILITIES.MANAGE_NOTE)"
        variant="primary-outline"
        @click="openNoteSelectModal"
      >
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
        </template>
        {{ $t('settings.customization.notes.add_note') }}
      </BaseButton>
    </template>

    <BaseTable
      ref="table"
      :data="fetchData"
      :columns="notesColumns"
      class="mt-14"
    >
      <template #cell-actions="{ row }">
        <NoteDropdown
          :row="row.data"
          :table="table"
          :load-data="refreshTable"
        />
      </template>
      <template #cell-name="{ row }">
        {{ row.data.name }}
        <BaseIcon
          v-if="row.data.is_default"
          name="StarIcon"
          class="w-3 h-3 text-primary-400"
        />
      </template>
      <template #cell-type="{ row }">
        {{ getLabelNote(row.data.type) }}
      </template>
    </BaseTable>
  </BaseSettingCard>
</template>
