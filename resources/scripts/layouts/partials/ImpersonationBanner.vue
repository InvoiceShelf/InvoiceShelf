<template>
  <div
    v-if="isImpersonating"
    class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-orange-600"
  >
    <BaseIcon name="ExclamationTriangleIcon" class="w-4 h-4 mr-2" />
    <span>{{ $t('administration.users.impersonating_banner') }}</span>
    <button
      class="ml-4 px-3 py-1 text-xs font-semibold text-orange-600 bg-white rounded hover:bg-orange-50"
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

  window.location.href = '/admin/administration/users'
}
</script>
