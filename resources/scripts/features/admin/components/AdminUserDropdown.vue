<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <router-link :to="`/admin/administration/users/${row.id}/edit`">
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <BaseDropdownItem
      v-if="row.id !== userStore.currentUser?.id"
      @click="onImpersonate"
    >
      <BaseIcon
        name="ArrowRightEndOnRectangleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('administration.users.impersonate') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useUserStore } from '../../../stores/user.store'
import { useDialogStore } from '../../../stores/dialog.store'
import { useAdminStore } from '../stores/admin.store'
import type { User } from '../../../types/domain/user'

interface Props {
  row: User
  table: { refresh: () => void } | null
  loadData: (() => void) | null
}

const props = defineProps<Props>()

const userStore = useUserStore()
const adminStore = useAdminStore()
const dialogStore = useDialogStore()
const { t } = useI18n()

function onImpersonate(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('administration.users.impersonate_confirm', {
        name: props.row.name,
      }),
      yesLabel: t('administration.users.impersonate'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      size: 'lg',
      hideNoButton: false,
    })
    .then((confirmed: boolean) => {
      if (confirmed) {
        adminStore.impersonateUser(props.row.id).then(() => {
          window.location.href = '/admin/dashboard'
        })
      }
    })
}
</script>
