<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
  src: string | false
}

const props = defineProps<Props>()

const status = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')

async function checkPdf(url: string): Promise<void> {
  status.value = 'loading'
  try {
    const response = await fetch(url, { method: 'GET', credentials: 'same-origin' })
    if (response.ok) {
      status.value = 'ready'
    } else {
      status.value = 'error'
    }
  } catch {
    status.value = 'error'
  }
}

function retry(): void {
  if (props.src) {
    checkPdf(props.src)
  }
}

watch(
  () => props.src,
  (url) => {
    if (url) {
      checkPdf(url)
    } else {
      status.value = 'idle'
    }
  },
  { immediate: true },
)
</script>

<template>
  <div
    class="flex flex-col min-h-0 mt-8 overflow-hidden"
    style="height: 75vh"
  >
    <!-- Loading -->
    <div
      v-if="status === 'loading' || status === 'idle'"
      class="flex-1 flex items-center justify-center border border-line-default rounded-md bg-surface"
    >
      <BaseSpinner class="w-8 h-8 text-primary-400" />
    </div>

    <!-- Error -->
    <div
      v-else-if="status === 'error'"
      class="flex-1 flex flex-col items-center justify-center gap-4 border border-line-default rounded-md bg-surface"
    >
      <BaseIcon name="ExclamationCircleIcon" class="w-12 h-12 text-muted" />
      <p class="text-sm text-muted">
        {{ $t('general.unable_to_load_pdf') }}
      </p>
      <BaseButton variant="primary-outline" size="sm" @click="retry">
        {{ $t('general.retry') }}
      </BaseButton>
    </div>

    <!-- PDF iframe -->
    <iframe
      v-else
      :src="src || undefined"
      class="flex-1 border border-line-default border-solid rounded-md bg-surface"
    />
  </div>
</template>
