<template>
  <BasePage>
    <BasePageHeader :title="$t('inbox.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('inbox.title')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          variant="primary-outline"
          :disabled="inboxStore.unreadCount === 0"
          @click="markAllRead"
        >
          <template #left="slotProps">
            <BaseIcon name="CheckIcon" :class="slotProps.class" aria-hidden="true" />
          </template>
          {{ $t('inbox.mark_all_read') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <div class="flex flex-col gap-4 mt-6">
      <BaseTabGroup @change="setFilter">
        <BaseTab :title="$t('inbox.all')" filter="" />
        <BaseTab :title="$t('inbox.unread')" filter="unread" />
      </BaseTabGroup>

      <BaseCard container-class="p-0">
        <div v-if="loading && !notices.length" class="px-4 py-4 space-y-4">
          <BaseContentPlaceholders v-for="n in 4" :key="n">
            <BaseContentPlaceholdersText :lines="2" />
          </BaseContentPlaceholders>
        </div>

        <BaseEmptyPlaceholder
          v-else-if="!notices.length"
          icon="BellIcon"
          :title="$t('inbox.empty_title')"
          :description="unreadOnly ? $t('inbox.empty_unread_description') : $t('inbox.empty_description')"
        />

        <ul v-else class="p-1.5 divide-y divide-line-light">
          <li v-for="notice in notices" :key="notice.id">
            <InboxNoticeItem :notice="notice" @open="openNotice">
              <template #actions>
                <BaseIconButton
                  v-if="notice.read_at === null"
                  icon="CheckIcon"
                  size="sm"
                  :label="$t('inbox.mark_read')"
                  @click="toggleRead(notice)"
                />
                <BaseIconButton
                  v-else
                  icon="EnvelopeIcon"
                  size="sm"
                  :label="$t('inbox.mark_unread')"
                  @click="toggleRead(notice)"
                />
                <BaseIconButton
                  icon="TrashIcon"
                  size="sm"
                  tone="danger"
                  :label="$t('inbox.delete')"
                  @click="remove(notice)"
                />
              </template>
            </InboxNoticeItem>
          </li>
        </ul>

        <BaseTablePagination
          v-if="pagination.totalPages > 1"
          :pagination="pagination"
          @page-change="load"
        />
      </BaseCard>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { inboxService } from '@/scripts/api/services/inbox.service'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useInboxStore } from '@/scripts/stores/inbox.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { InboxNotice, InboxPage } from '@/scripts/types/domain/inbox'
import type { PaginationInfo } from '@/scripts/components/table/TablePagination.vue'
import InboxNoticeItem from '../components/InboxNoticeItem.vue'

const PER_PAGE = 20

const router = useRouter()
const companyStore = useCompanyStore()
const inboxStore = useInboxStore()
const notificationStore = useNotificationStore()

const notices = ref<InboxNotice[]>([])
const meta = ref<InboxPage['meta'] | null>(null)
const loading = ref<boolean>(false)
const unreadOnly = ref<boolean>(false)

const pagination = computed<PaginationInfo>(() => ({
  currentPage: meta.value?.current_page ?? 1,
  totalPages: meta.value?.last_page ?? 1,
  totalCount: meta.value?.total ?? 0,
  count: notices.value.length,
  limit: meta.value?.per_page ?? PER_PAGE,
}))

function showError(err: unknown): void {
  notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
}

async function load(page = 1): Promise<void> {
  loading.value = true
  try {
    const result = await inboxService.list({ page, limit: PER_PAGE, unread: unreadOnly.value })
    notices.value = result.data
    meta.value = result.meta
    inboxStore.unreadCount = result.meta.unread_count
  } catch (err: unknown) {
    showError(err)
  } finally {
    loading.value = false
  }
}

function setFilter(tab: { filter?: string }): void {
  const unread = tab.filter === 'unread'
  if (unread === unreadOnly.value && meta.value) {
    return
  }
  unreadOnly.value = unread
  void load()
}

function replace(updated: InboxNotice): void {
  notices.value = notices.value.map((n) => (n.id === updated.id ? updated : n))
}

async function toggleRead(notice: InboxNotice): Promise<void> {
  try {
    replace(
      notice.read_at === null
        ? await inboxStore.markRead(notice)
        : await inboxStore.markUnread(notice),
    )
    if (unreadOnly.value) {
      await load(pagination.value.currentPage)
    }
  } catch (err: unknown) {
    showError(err)
  }
}

async function remove(notice: InboxNotice): Promise<void> {
  try {
    await inboxStore.remove(notice)
    notificationStore.showNotification({ type: 'success', message: 'inbox.deleted' })
    const page = notices.value.length === 1 && pagination.value.currentPage > 1
      ? pagination.value.currentPage - 1
      : pagination.value.currentPage
    await load(page)
  } catch (err: unknown) {
    showError(err)
  }
}

async function markAllRead(): Promise<void> {
  try {
    await inboxStore.markAllRead()
    await load(unreadOnly.value ? 1 : pagination.value.currentPage)
  } catch (err: unknown) {
    showError(err)
  }
}

async function openNotice(notice: InboxNotice): Promise<void> {
  try {
    await inboxStore.markRead(notice)
  } catch {
    // Reading it matters more than marking it.
  }
  if (notice.url) {
    await router.push(notice.url)
  } else {
    replace({ ...notice, read_at: notice.read_at ?? new Date().toISOString() })
  }
}

watch(() => companyStore.selectedCompany?.id, () => void load(), { immediate: true })
</script>
