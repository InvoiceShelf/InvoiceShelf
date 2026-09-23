<template>
  <div
    v-if="isImpersonating"
    class="
      flex flex-wrap items-center justify-center shrink-0 gap-x-3 gap-y-1 px-4 py-2
      text-sm font-medium bg-alert-warning-bg text-alert-warning-text
      border-b border-line-light
    "
    role="status"
  >
    <span class="flex items-center gap-2">
      <BaseIcon name="ExclamationTriangleIcon" class="w-4 h-4 shrink-0" />
      {{ $t('administration.users.impersonating_banner') }}
    </span>
    <button
      type="button"
      class="px-2.5 py-1 text-xs font-semibold rounded-md bg-surface text-heading border border-line-default hover:bg-hover disabled:opacity-60"
      :disabled="isStopping"
      @click="stopImpersonating"
    >
      {{ $t('administration.users.stop_impersonating') }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import * as ls from '@/scripts/utils/local-storage'
import { client } from '@/scripts/api/client'
import { API } from '@/scripts/api/endpoints'
import { hardNavigate } from '@/scripts/utils/hard-navigate'

const isStopping = ref<boolean>(false)

const isImpersonating = computed<boolean>(() => {
  return ls.get<string>('admin.impersonating') === 'true'
})

async function stopImpersonating(): Promise<void> {
  isStopping.value = true

  try {
    await client.post(API.SUPER_ADMIN_STOP_IMPERSONATING)
  } catch {
    // Token already cleaned up in store action
  }

  ls.remove('admin.impersonating')
  ls.remove('auth.token')

  hardNavigate('/admin/administration/users')
}
</script>
