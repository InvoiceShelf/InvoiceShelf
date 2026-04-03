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

<script setup>
import { computed, ref } from 'vue'
import Ls from '@/scripts/services/ls.js'
import { useAdministrationStore } from '@/scripts/admin/stores/administration'

const administrationStore = useAdministrationStore()
let isStopping = ref(false)

const isImpersonating = computed(() => {
  return Ls.get('admin.impersonating') === 'true'
})

async function stopImpersonating() {
  isStopping.value = true

  try {
    await administrationStore.stopImpersonating()
  } catch {
    // Token already cleaned up in store action
  }

  window.location.href = '/admin/administration/users'
}
</script>
