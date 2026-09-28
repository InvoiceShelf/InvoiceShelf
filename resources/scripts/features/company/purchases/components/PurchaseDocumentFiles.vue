<template>
  <BaseCard container-class="p-4 md:p-5">
    <h2 class="mb-3 font-semibold text-section text-heading">
      {{ $t('purchases.attachments') }}
    </h2>
    <div
      v-for="file in record.attachments"
      :key="file.id"
      class="py-2"
    >
      <button
        class="text-sm text-primary-600"
        @click="download(file.url, file.name)"
      >
        {{ file.name }}
      </button>
    </div>
    <BaseInputGroup
      v-if="canEdit && record.status !== 'VOID'"
      :label="$t('purchases.add_attachment')"
      class="mt-3"
      ><BaseFileUploader
        :key="uploadKey"
        accept=".pdf,.jpg,.jpeg,.png,.webp"
        @change="upload"
    /></BaseInputGroup>
    <p v-if="busy" role="status" class="mt-2 text-sm text-muted">
      {{ $t('purchases.loading') }}
    </p>
  </BaseCard>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type {
  PurchaseKind,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'
import { purchaseError } from '../helpers'

const props = defineProps<{
  kind: PurchaseKind
  record: PurchaseRecord
  canEdit: boolean
}>()
const emit = defineEmits<{ changed: []; error: [message: string] }>()

const busy = ref(false),
  uploadKey = ref(0)

async function upload(_field: string, file: FileList | File | string) {
  if (!(file instanceof File) || busy.value) return
  busy.value = true
  try {
    await purchaseService.upload(props.kind, props.record.id, file)
    emit('changed')
  } catch (e) {
    emit('error', purchaseError(e))
  } finally {
    busy.value = false
    uploadKey.value++
  }
}
async function download(url: string, name: string) {
  try {
    await purchaseService.download(url, name)
  } catch (e) {
    emit('error', purchaseError(e))
  }
}
</script>
