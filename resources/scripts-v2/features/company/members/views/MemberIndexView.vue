<script setup lang="ts">
import { debouncedWatch } from '@vueuse/core'
import { reactive, ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMemberStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useUserStore } from '../../../../stores/user.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import MemberDropdown from '../components/MemberDropdown.vue'
import InviteMemberModal from '../components/InviteMemberModal.vue'
import AstronautIcon from '@v2/components/icons/AstronautIcon.vue'

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

interface MemberFilters {
  name: string
  email: string
  role: string
}

const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const memberStore = useMemberStore()
const userStore = useUserStore()

const tableComponent = ref<{ refresh: () => void } | null>(null)
const showFilters = ref<boolean>(false)
const showInviteModal = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(true)
const { t } = useI18n()

const filters = reactive<MemberFilters>({
  name: '',
  email: '',
  role: '',
})

const userTableColumns = computed<TableColumn[]>(() => [
  {
    key: 'status',
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
    sortable: false,
  },
  {
    key: 'name',
    label: t('members.name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  { key: 'email', label: 'Email' },
  {
    key: 'role',
    label: t('members.role'),
    sortable: false,
  },
  {
    key: 'created_at',
    label: t('members.added_on'),
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

const showEmptyScreen = computed<boolean>(
  () => !memberStore.totalUsers && !isFetchingInitialData.value
)

const selectField = computed<number[]>({
  get: () => memberStore.selectedUsers,
  set: (value: number[]) => {
    memberStore.selectUser(value)
  },
})

const selectAllFieldStatus = computed<boolean>({
  get: () => memberStore.selectAllField,
  set: (value: boolean) => {
    memberStore.setSelectAllState(value)
  },
})

debouncedWatch(
  filters,
  () => {
    refreshTable()
  },
  { debounce: 500 }
)

onMounted(() => {
  memberStore.fetchUsers()
  memberStore.fetchRoles()
  memberStore.fetchPendingInvitations()
})

onUnmounted(() => {
  if (memberStore.selectAllField) {
    memberStore.selectAllUsers()
  }
})

function refreshTable(): void {
  tableComponent.value?.refresh()
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    display_name: filters.name,
    email: filters.email,
    role: filters.role || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true
  const response = await memberStore.fetchUsers(data)
  isFetchingInitialData.value = false

  return {
    data: response.data,
    pagination: {
      totalPages: response.meta.last_page,
      currentPage: page,
      totalCount: response.meta.total,
      limit: 10,
    },
  }
}

function clearFilter(): void {
  filters.name = ''
  filters.email = ''
  filters.role = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

function cancelInvitation(id: number): void {
  memberStore.cancelInvitation(id)
}

function removeMultipleUsers(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('members.confirm_delete', 2),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res: boolean) => {
      if (res) {
        memberStore.deleteMultipleUsers().then((success) => {
          if (success) {
            refreshTable()
          }
        })
      }
    })
}
</script>

<template>
  <BasePage>
    <!-- Page Header Section -->
    <BasePageHeader :title="$t('members.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('members.title', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton
            v-show="memberStore.totalUsers"
            variant="primary-outline"
            @click="toggleFilter"
          >
            {{ $t('general.filter') }}
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                name="FunnelIcon"
                :class="slotProps.class"
              />
              <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
            </template>
          </BaseButton>

          <BaseButton
            v-if="userStore.currentUser?.is_owner"
            @click="showInviteModal = true"
          >
            <template #left="slotProps">
              <BaseIcon
                name="EnvelopeIcon"
                :class="slotProps.class"
                aria-hidden="true"
              />
            </template>
            {{ $t('members.invite_member') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" class="mt-3" @clear="clearFilter">
      <BaseInputGroup :label="$t('members.name')" class="flex-1 mt-2 mr-4">
        <BaseInput
          v-model="filters.name"
          type="text"
          name="name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('members.email')" class="flex-1 mt-2 mr-4">
        <BaseInput
          v-model="filters.email"
          type="text"
          name="email"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup class="flex-1 mt-2" :label="$t('members.role')">
        <BaseMultiselect
          v-model="filters.role"
          :options="memberStore.roles"
          label="title"
          value-prop="id"
          :placeholder="$t('members.select_role')"
          :can-clear="true"
          :can-deselect="true"
          searchable
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Empty Placeholder -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('members.no_users')"
      :description="$t('members.list_of_users')"
    >
      <AstronautIcon class="mt-5 mb-4" />
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <div
        class="relative flex items-center justify-end h-5 border-line-default border-solid"
      >
        <BaseDropdown v-if="memberStore.selectedUsers.length">
          <template #activator>
            <span
              class="flex text-sm font-medium cursor-pointer select-none text-primary-400"
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" class="h-5" />
            </span>
          </template>
          <BaseDropdownItem @click="removeMultipleUsers">
            <BaseIcon name="TrashIcon" class="h-5 mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableComponent"
        :data="fetchData"
        :columns="userTableColumns"
        class="mt-3"
      >
        <!-- Select All Checkbox -->
        <template #header>
          <div class="absolute z-10 items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="selectAllFieldStatus"
              variant="primary"
              @change="memberStore.selectAllUsers"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="custom-control custom-checkbox">
            <BaseCheckbox
              :id="row.data.id"
              v-model="selectField"
              :value="row.data.id"
              variant="primary"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <router-link
            :to="{ path: `users/${row.data.id}/edit` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.name }}
          </router-link>
        </template>

        <template #cell-role="{ row }">
          <span>{{ row.data.roles?.length ? row.data.roles[0].title : '-' }}</span>
        </template>

        <template #cell-created_at="{ row }">
          <span>{{ row.data.formatted_created_at }}</span>
        </template>

        <template v-if="userStore.currentUser?.is_owner" #cell-actions="{ row }">
          <MemberDropdown
            :row="row.data"
            :table="tableComponent"
            :load-data="refreshTable"
          />
        </template>
      </BaseTable>
    </div>

    <!-- Pending Invitations Section -->
    <div
      v-if="userStore.currentUser?.is_owner && memberStore.pendingInvitations.length > 0"
      class="mt-8"
    >
      <h3 class="text-lg font-medium text-heading mb-4">
        {{ $t('members.pending_invitations') }}
      </h3>
      <BaseCard>
        <div class="divide-y divide-line-default">
          <div
            v-for="invitation in memberStore.pendingInvitations"
            :key="invitation.id"
            class="flex items-center justify-between px-6 py-4"
          >
            <div>
              <p class="text-sm font-medium text-heading">
                {{ invitation.email }}
              </p>
              <p class="text-sm text-muted">
                {{ invitation.role?.title }} &middot;
                {{ $t('members.invited_by') }}: {{ invitation.invited_by?.name }}
              </p>
            </div>
            <BaseButton
              variant="danger"
              size="sm"
              @click="cancelInvitation(invitation.id)"
            >
              {{ $t('members.cancel_invitation') }}
            </BaseButton>
          </div>
        </div>
      </BaseCard>
    </div>

    <InviteMemberModal
      :show="showInviteModal"
      @close="showInviteModal = false"
    />
  </BasePage>
</template>
