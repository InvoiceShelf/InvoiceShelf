<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useMemberStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'

interface RowData {
  id: number
  [key: string]: unknown
}

interface Props {
  row: RowData | null
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}

const props = withDefaults(defineProps<Props>(), {
  row: null,
  table: null,
  loadData: null,
})

const memberStore = useMemberStore()
const dialogStore = useDialogStore()

const { t } = useI18n()
const route = useRoute()

function removeMember(id: number): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('members.confirm_delete', 1),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res: boolean) => {
      if (res) {
        memberStore.deleteUser({ users: [id] }).then((success) => {
          if (success) {
            props.loadData?.()
          }
        })
      }
    })
}
</script>

<template>
  <BaseDropdown>
    <template #activator>
      <span class="inline-flex items-center justify-center rounded-lg w-9 h-9 text-muted hover:bg-hover-strong hover:text-heading">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
    </template>

    <!-- Edit Member -->
    <router-link v-if="row" :to="`/admin/members/${row.id}/edit`">
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Delete Member -->
    <BaseDropdownItem v-if="row" @click="removeMember(row.id)">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
