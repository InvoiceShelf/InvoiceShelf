<script setup lang="ts">
import { XCircleIcon } from '@heroicons/vue/24/solid'

interface Props {
  errorTitle?: string
  errors?: string[] | null
}

withDefaults(defineProps<Props>(), {
  errorTitle: 'There were some errors with your submission',
  errors: null,
})
</script>

<template>
  <div class="rounded-md bg-alert-error-bg p-4">
    <div class="flex">
      <div class="shrink-0">
        <XCircleIcon class="h-5 w-5 text-alert-error-text" aria-hidden="true" />
      </div>
      <div class="ml-3">
        <h3 class="text-sm font-medium text-alert-error-text">
          {{ errorTitle }}
        </h3>
        <div class="mt-2 text-sm text-alert-error-text">
          <ul role="list" class="list-disc pl-5 space-y-1">
            <li v-for="(error, key) in errors" :key="key">
              {{ error }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
