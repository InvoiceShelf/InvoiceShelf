<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { useUserStore } from '../../../../stores/user.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { roleService } from '../../../../api/services/role.service'
import RoleDropdown from '@/scripts/features/company/settings/components/RoleDropdown.vue'
import RolesModal from '@/scripts/features/company/settings/components/RolesModal.vue'

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
}

const modalStore = useModalStore()
const userStore = useUserStore()
const companyStore = useCompanyStore()

const { t } = useI18n()
const table = ref<{ refresh: () => void } | null>(null)

const roleColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('settings.roles.role_name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'created_at',
    label: t('settings.roles.added_on'),
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'actions',
    label: '',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

async function fetchData({ sort }: FetchParams): Promise<FetchResult> {
  const data = {
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    company_id: companyStore.selectedCompany?.id,
  }

  const response = await roleService.list(data)

  return {
    data: (response as Record<string, unknown>).data as unknown[],
  }
}

function refreshTable(): void {
  table.value?.refresh()
}

async function openRoleModal(): Promise<void> {
  await roleService.getAbilities()

  modalStore.openModal({
    title: t('settings.roles.add_role'),
    componentName: 'RolesModal',
    size: 'lg',
    refreshData: table.value?.refresh,
  })
}
</script>

<template>
  <RolesModal />

  <BaseSettingCard
    :title="$t('settings.roles.title')"
    :description="$t('settings.roles.description')"
  >
    <template v-if="userStore.currentUser?.is_owner" #action>
      <BaseButton variant="primary-outline" @click="openRoleModal">
        <template #left="slotProps">
          <BaseIcon name="PlusIcon" :class="slotProps.class" />
        </template>
        {{ $t('settings.roles.add_new_role') }}
      </BaseButton>
    </template>

    <BaseTable
      ref="table"
      :data="fetchData"
      :columns="roleColumns"
      class="mt-14"
    >
      <template #cell-created_at="{ row }">
        {{ row.data.formatted_created_at }}
      </template>

      <template #cell-actions="{ row }">
        <RoleDropdown
          v-if="
            userStore.currentUser?.is_owner &&
            row.data.name !== 'super admin' &&
            row.data.name !== 'owner'
          "
          :row="row.data"
          :table="table"
          :load-data="refreshTable"
        />
        <span
          v-else-if="row.data.name === 'owner' || row.data.name === 'super admin'"
          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300/50"
        >
          {{ $t('settings.roles.system_role') }}
        </span>
      </template>
    </BaseTable>
  </BaseSettingCard>
</template>
