<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { isNative } from '@/scripts/config/runtime'
import {
  fetchDocumentBlob,
  openDocument,
  previewUrlFor,
  revokePreviewUrl,
  type FetchedDocument,
} from '@/scripts/utils/documents'

/**
 * The rendered half of a report page.
 *
 * The report used to be an iframe pointed straight at `/reports/...`, which
 * only works where the page and the server share an origin and a cookie. It
 * is fetched through the API client instead, and framed as a blob, so a thin
 * client renders the same report with its bearer token.
 */

interface Props {
  /** Where the report is rendered, query string included. */
  path: string | null
}

const props = defineProps<Props>()

const status = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')
const previewUrl = ref<string | null>(null)

// A WebView has no PDF viewer of its own, so on a device the report is
// offered as a file rather than framed.
const canEmbed = !isNative()

let loaded: FetchedDocument | null = null
let loadedPath: string | null = null
// Only the newest request may write to the state: report parameters change
// faster than a PDF renders.
let request = 0

function release(): void {
  revokePreviewUrl(previewUrl.value)
  previewUrl.value = null
  loaded = null
  loadedPath = null
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
    loadedPath = path

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
  if (props.path) {
    load(props.path)
  }
}

/**
 * Show the report outside the pane: a new tab in a browser, the system viewer
 * on a device. `path` lets the page hand over parameters it has just changed,
 * since the update button is desktop-only.
 */
async function view(path?: string | null): Promise<void> {
  const target = path ?? props.path

  if (!target) {
    return
  }

  // The report already in hand opens without an await in the way, which is
  // what keeps a browser from taking the new tab for an unprompted popup.
  if (loaded && loadedPath === target) {
    await openDocument(loaded.blob, loaded.filename)

    return
  }

  try {
    const fetched = await fetchDocumentBlob(target)

    await openDocument(fetched.blob, fetched.filename)
  } catch {
    status.value = 'error'
  }
}

watch(
  () => props.path,
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

defineExpose({ view })
</script>

<template>
  <div>
    <!-- Loading -->
    <div
      v-if="status === 'loading' || status === 'idle'"
      class="flex items-center justify-center w-full h-screen border border-line-default border-solid rounded bg-surface"
    >
      <BaseSpinner class="w-8 h-8 text-primary-400" />
    </div>

    <!-- Error -->
    <div
      v-else-if="status === 'error'"
      class="flex flex-col items-center justify-center gap-4 w-full h-screen border border-line-default border-solid rounded bg-surface"
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
      class="flex flex-col items-center justify-center gap-4 w-full h-screen border border-line-default border-solid rounded bg-surface"
    >
      <BaseIcon name="DocumentTextIcon" class="w-12 h-12 text-muted" />
      <BaseButton variant="primary" size="sm" @click="view()">
        {{ $t('pdf.open_pdf') }}
      </BaseButton>
    </div>

    <!-- Report iframe -->
    <iframe
      v-else
      :src="previewUrl ?? undefined"
      :title="$t('general.pdf_preview')"
      class="w-full h-screen border border-line-default border-solid rounded bg-surface"
    />
  </div>
</template>
