<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useCustomerStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useUserStore } from '../../../../stores/user.store'

interface RowData {
  id: number
  [key: string]: unknown
}

interface Props {
  row: RowData | null
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}

interface Emits {
  (e: 'deleted'): void
}

const ABILITIES = {
  EDIT_CUSTOMER: 'edit-customer',
  VIEW_CUSTOMER: 'view-customer',
  DELETE_CUSTOMER: 'delete-customer',
} as const

const props = withDefaults(defineProps<Props>(), {
  row: null,
  table: null,
  loadData: null,
})

const emit = defineEmits<Emits>()

const customerStore = useCustomerStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

function removeCustomer(id: number): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('customers.confirm_delete', 1),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res: boolean) => {
      if (res) {
        customerStore.deleteCustomer({ ids: [id] }).then((response) => {
          if (response.success) {
            props.loadData?.()
          }
        })
      }
    })
}
</script>

<template>
  <BaseDropdown :content-loading="customerStore.isFetchingViewData">
    <template #activator>
      <BaseButton v-if="route.name === 'customers.view'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Edit Customer -->
    <router-link
      v-if="userStore.hasAbilities(ABILITIES.EDIT_CUSTOMER) && row"
      :to="`/admin/customers/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- View Customer -->
    <router-link
      v-if="
        route.name !== 'customers.view' &&
        userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER) &&
        row
      "
      :to="`customers/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Delete Customer -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.DELETE_CUSTOMER) && row"
      @click="removeCustomer(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
