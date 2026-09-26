<script setup lang="ts">
import { computed } from 'vue'
import { isReadOnly, managedState } from '@/scripts/utils/managed'
const link = computed(() => managedState()?.billing_url || managedState()?.support_url)
</script>

<template>
  <aside
    v-if="isReadOnly()"
    role="status"
    class="flex flex-wrap items-center justify-between gap-2 border-b border-line-default bg-alert-warning-bg px-4 py-3 text-sm text-alert-warning-text sm:px-6"
  >
    <p>{{ $t('managed_read_only.message') }}</p>
    <a v-if="link" :href="link" class="shrink-0 font-semibold underline underline-offset-4">
      {{ $t(managedState()?.billing_url ? 'managed_read_only.billing' : 'managed_read_only.support') }}
    </a>
  </aside>
</template>
