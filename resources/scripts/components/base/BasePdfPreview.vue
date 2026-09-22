<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { isNative } from '@/scripts/config/runtime'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
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
  /** Shown on the document card where the PDF is not framed */
  title?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
})

const { isPhone } = useBreakpoints()

const status = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
const previewUrl = ref<string | null>(null)

// A WebView has no PDF viewer of its own, and phone browsers render a framed
// PDF as a blank box or its first page, so there the document is offered as
// a file rather than framed.
const canEmbed = computed<boolean>(() => !isNative() && !isPhone.value)

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

    previewUrl.value = previewUrlFor(fetched.blob)

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

defineExpose({ openPdf, status })
</script>

<template>
  <!-- Wider screens: the document as a sheet of paper on the canvas -->
  <div
    v-if="canEmbed"
    class="flex flex-col min-h-[560px] h-[78vh] overflow-hidden rounded-lg bg-surface shadow-paper"
  >
    <div
      v-if="status === 'loading' || status === 'idle'"
      class="flex items-center justify-center flex-1"
    >
      <BaseSpinner class="w-7 h-7 text-subtle" />
    </div>

    <div
      v-else-if="status === 'error'"
      class="flex flex-col items-center justify-center flex-1 gap-4"
    >
      <BaseIcon name="ExclamationCircleIcon" class="w-10 h-10 text-subtle" />
      <p class="text-sm text-muted">
        {{ $t('general.unable_to_load_pdf') }}
      </p>
      <BaseButton variant="white" size="sm" @click="retry">
        {{ $t('general.retry') }}
      </BaseButton>
    </div>

    <iframe
      v-else
      :src="previewUrl ?? undefined"
      :title="title || undefined"
      class="flex-1 w-full border-0"
    />
  </div>

  <!-- Phones and the app: a document card that opens the PDF -->
  <div
    v-else
    class="flex items-center gap-4 p-4 border glass rounded-xl"
  >
    <div
      class="flex items-center justify-center w-12 shrink-0 rounded-md aspect-[3/4] bg-surface-secondary border border-line-light shadow-paper"
      aria-hidden="true"
    >
      <BaseIcon name="DocumentTextIcon" class="w-5 h-5 text-subtle" />
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-medium truncate text-heading">{{ title || 'PDF' }}</p>
      <p v-if="status === 'error'" class="text-xs text-muted">
        {{ $t('general.unable_to_load_pdf') }}
      </p>
      <p v-else class="text-xs text-muted">PDF</p>
    </div>
    <BaseButton
      v-if="status === 'error'"
      variant="white"
      size="sm"
      @click="retry"
    >
      {{ $t('general.retry') }}
    </BaseButton>
    <BaseButton
      v-else
      variant="white"
      size="sm"
      :loading="status !== 'ready'"
      :disabled="status !== 'ready'"
      @click="openPdf"
    >
      {{ $t('pdf.open_pdf') }}
    </BaseButton>
  </div>
</template>
