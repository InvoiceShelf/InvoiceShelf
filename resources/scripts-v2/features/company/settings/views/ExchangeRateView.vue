<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { exchangeRateService } from '../../../../api/services/exchange-rate.service'
import ExchangeRateProviderModal from '@/scripts/admin/components/modal-components/ExchangeRateProviderModal.vue'

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

const { t } = useI18n()
const modalStore = useModalStore()
const dialogStore = useDialogStore()

const table = ref<{ refresh: () => void } | null>(null)

const drivers = computed<TableColumn[]>(() => [
  {
    key: 'driver',
    label: t('settings.exchange_rate.driver'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'key',
    label: t('settings.exchange_rate.key'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'active',
    label: t('settings.exchange_rate.active'),
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

  const response = await exchangeRateService.listProviders(data)

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

function addExchangeRate(): void {
  modalStore.openModal({
    title: t('settings.exchange_rate.new_driver'),
    componentName: 'ExchangeRateProviderModal',
    size: 'md',
    refreshData: table.value?.refresh,
  })
}

function editExchangeRate(id: number): void {
  exchangeRateService.getProvider(id)
  modalStore.openModal({
    title: t('settings.exchange_rate.edit_driver'),
    componentName: 'ExchangeRateProviderModal',
    size: 'md',
    data: id,
    refreshData: table.value?.refresh,
  })
}

function removeExchangeRate(id: number): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.exchange_rate.exchange_rate_confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        await exchangeRateService.deleteProvider(id)
        table.value?.refresh()
      }
    })
}
</script>

<template>
  <ExchangeRateProviderModal />

  <BaseCard>
    <template #header>
      <div class="flex flex-wrap justify-between lg:flex-nowrap">
        <div>
          <h6 class="text-lg font-medium text-left">
            {{ $t('settings.menu_title.exchange_rate') }}
          </h6>
          <p
            class="mt-2 text-sm leading-snug text-left text-muted"
            style="max-width: 680px"
          >
            {{ $t('settings.exchange_rate.providers_description') }}
          </p>
        </div>
        <div class="mt-4 lg:mt-0 lg:ml-2">
          <BaseButton
            variant="primary-outline"
            size="lg"
            @click="addExchangeRate"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('settings.exchange_rate.new_driver') }}
          </BaseButton>
        </div>
      </div>
    </template>

    <BaseTable ref="table" class="mt-16" :data="fetchData" :columns="drivers">
      <template #cell-driver="{ row }">
        <span class="capitalize">{{ row.data.driver.replace('_', ' ') }}</span>
      </template>

      <template #cell-active="{ row }">
        <BaseBadge
          :bg-color="row.data.active ? 'bg-green-100' : 'bg-gray-100'"
          :color="row.data.active ? 'text-green-800' : 'text-gray-800'"
        >
          {{ row.data.active ? 'YES' : 'NO' }}
        </BaseBadge>
      </template>

      <template #cell-actions="{ row }">
        <BaseDropdown>
          <template #activator>
            <div class="inline-block">
              <BaseIcon name="EllipsisHorizontalIcon" class="w-5 text-muted" />
            </div>
          </template>

          <BaseDropdownItem @click="editExchangeRate(row.data.id)">
            <BaseIcon name="PencilIcon" class="h-5 mr-3 text-body" />
            {{ $t('general.edit') }}
          </BaseDropdownItem>

          <BaseDropdownItem @click="removeExchangeRate(row.data.id)">
            <BaseIcon name="TrashIcon" class="h-5 mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </template>
    </BaseTable>
  </BaseCard>
</template>
