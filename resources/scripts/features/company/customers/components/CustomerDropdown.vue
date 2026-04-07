<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useCustomerStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useUserStore } from '../../../../stores/user.store'

interface RowData {
  id?: number | string | null
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

const isDetailView = computed<boolean>(() => route.name === 'customers.view')
const customerId = computed<number | null>(() => {
  const rowId = normalizeCustomerId(props.row?.id)
  if (rowId !== null) {
    return rowId
  }

  if (isDetailView.value) {
    return normalizeCustomerId(route.params.id)
  }

  return null
})

function normalizeCustomerId(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsedValue = Number(value)
    return Number.isFinite(parsedValue) ? parsedValue : null
  }

  return null
}

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

function onRemoveCustomer(): void {
  if (customerId.value === null) {
    return
  }

  removeCustomer(customerId.value)
}
</script>

<template>
  <BaseDropdown :content-loading="customerStore.isFetchingViewData">
    <template #activator>
      <BaseButton v-if="isDetailView" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Edit Customer -->
    <router-link
      v-if="
        userStore.hasAbilities(ABILITIES.EDIT_CUSTOMER) &&
        customerId !== null
      "
      :to="`/admin/customers/${customerId}/edit`"
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
        !isDetailView &&
        userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER) &&
        customerId !== null
      "
      :to="`/admin/customers/${customerId}/view`"
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
      v-if="
        userStore.hasAbilities(ABILITIES.DELETE_CUSTOMER) &&
        customerId !== null
      "
      @click="onRemoveCustomer"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
