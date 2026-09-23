<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { isNative } from '@/scripts/config/runtime'
import {
  deliverDocument,
  fetchDocumentBlob,
  previewUrlFor,
  revokePreviewUrl,
  type FetchedDocument,
} from '@/scripts/utils/documents'

interface Props {
  /**
   * A path on the server, such as `/invoices/pdf/<hash>`. An absolute
   * same-origin URL is still accepted and reduced to its path.
   */
  src: string | false
}

const props = defineProps<Props>()

const status = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
const previewUrl = ref<string | null>(null)

// A WebView has no PDF viewer of its own, so on a device the document is
// offered as a file rather than framed.
const canEmbed = !isNative()

let loaded: FetchedDocument | null = null
// Only the newest request may write to the state; a fast click through two
// invoices must not end on whichever PDF rendered slower.
let request = 0

function release(): void {
  revokePreviewUrl(previewUrl.value)
  previewUrl.value = null
  loaded = null
}

async function load(path: string): Promise<void> {
  const ticket = ++request

  release()
  status.value = 'loading'

  try {
    const fetched = await fetchDocumentBlob(path)

    if (ticket !== request) {
      return
    }

    loaded = fetched

    if (canEmbed) {
      previewUrl.value = previewUrlFor(fetched.blob)
    }

    status.value = 'ready'
  } catch {
    if (ticket === request) {
      status.value = 'error'
    }
  }
}

function retry(): void {
  if (props.src) {
    load(props.src)
  }
}

function openPdf(): void {
  if (loaded) {
    deliverDocument(loaded.blob, loaded.filename)
  }
}

watch(
  () => props.src,
  (path) => {
    if (path) {
      load(path)

      return
    }

    request++
    release()
    status.value = 'idle'
  },
  { immediate: true },
)

onBeforeUnmount(release)
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

    <!-- On a device: hand the file to the system viewer -->
    <div
      v-else-if="!canEmbed"
      class="flex-1 flex flex-col items-center justify-center gap-4 border border-line-default rounded-md bg-surface"
    >
      <BaseIcon name="DocumentTextIcon" class="w-12 h-12 text-muted" />
      <BaseButton variant="primary" size="sm" @click="openPdf">
        {{ $t('pdf.open_pdf') }}
      </BaseButton>
    </div>

    <!-- PDF iframe -->
    <iframe
      v-else
      :src="previewUrl ?? undefined"
      class="flex-1 border border-line-default border-solid rounded-md bg-surface"
    />
  </div>
</template>
