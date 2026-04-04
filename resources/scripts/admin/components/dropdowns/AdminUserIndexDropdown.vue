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

<script setup>
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useAdministrationStore } from '@/scripts/admin/stores/administration'
import { useDialogStore } from '@/scripts/stores/dialog'

const props = defineProps({
  row: {
    type: Object,
    default: null,
  },
  table: {
    type: Object,
    default: null,
  },
  loadData: {
    type: Function,
    default: null,
  },
})

const userStore = useUserStore()
const administrationStore = useAdministrationStore()
const dialogStore = useDialogStore()
const { t } = useI18n()

function onImpersonate() {
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
    .then((confirmed) => {
      if (confirmed) {
        administrationStore.impersonateUser(props.row.id).then(() => {
          window.location.href = '/admin/dashboard'
        })
      }
    })
}
</script>
