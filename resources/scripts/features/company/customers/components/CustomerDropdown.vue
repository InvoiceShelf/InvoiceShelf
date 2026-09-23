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
  <BaseDropdown :content-loading="customerStore.isFetchingViewData" :label="row?.name ? $t('general.actions_for', { name: String(row?.name) }) : ''">
    <template #activator>
      <span v-if="isDetailView" data-overflow class="inline-flex items-center justify-center border rounded-lg w-11 h-11 md:w-9 md:h-9 bg-surface border-line-default text-body hover:bg-hover">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
      <span v-else class="inline-flex items-center justify-center rounded-lg w-9 h-9 text-muted hover:bg-hover-strong hover:text-heading">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
    </template>

    <!-- Edit Customer -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.EDIT_CUSTOMER) && customerId !== null"
      :to="`/admin/customers/${customerId}/edit`"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <!-- View Customer -->
    <BaseDropdownItem
      v-if="!isDetailView && userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER) && customerId !== null"
      :to="`/admin/customers/${customerId}/view`"
    >
      <BaseIcon
        name="EyeIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.view') }}
    </BaseDropdownItem>

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
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
