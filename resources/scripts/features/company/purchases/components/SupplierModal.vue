<template>
  <BaseModal
    :show="show"
    :closable="!editor.saving.value"
    size="lg"
    @open="editor.load(supplier)"
    @close="close"
  >
    <template #header>{{
      $t(supplier?.id ? 'purchases.edit_supplier' : 'purchases.new_supplier')
    }}</template>
    <form :id="id" class="space-y-5 p-4 md:p-6" @submit.stop.prevent="save">
      <p v-if="editor.error.value" role="alert" class="text-sm text-danger">
        {{ editor.error.value }}
      </p>
      <SupplierFormFields
        v-if="editor.ready.value"
        v-model="draft"
        :options="editor.options.value"
        :countries="global.countries"
        :custom-field-scope="editor.customFieldScope"
      />
    </form>
    <PurchaseLookupModals :context="lookups" />
    <template #footer
      ><div class="flex justify-end gap-3 border-t border-line-light p-4">
        <BaseButton
          type="button"
          variant="white"
          :disabled="editor.saving.value"
          @click="close"
          >{{ $t('general.cancel') }}</BaseButton
        ><BaseButton
          type="submit"
          :form="id"
          :loading="editor.saving.value"
          :disabled="editor.saving.value || !editor.ready.value"
          >{{ $t('purchases.save_supplier') }}</BaseButton
        >
      </div></template
    >
  </BaseModal>
</template>
<script setup lang="ts">
import { computed, useId } from 'vue'
import { useGlobalStore } from '@/scripts/stores/global.store'
import type { PurchaseRecord } from '@/scripts/types/domain/purchase'
import { useSupplierEditor } from '../composables/use-supplier-editor'
import { providePurchaseLookups } from '../composables/use-purchase-lookups'
import SupplierFormFields from './SupplierFormFields.vue'
import PurchaseLookupModals from './PurchaseLookupModals.vue'
const props = defineProps<{ show: boolean; supplier?: PurchaseRecord | null }>()
const emit = defineEmits<{ close: []; saved: [record: PurchaseRecord] }>()
const id = useId(),
  global = useGlobalStore(),
  lookups = providePurchaseLookups(),
  editor = useSupplierEditor()
const draft = computed({
  get: () => editor.form,
  set: (value) => Object.assign(editor.form, value),
})
function close() {
  if (!editor.saving.value && !lookups.active.value) emit('close')
}
async function save() {
  const record = await editor.save(props.supplier?.id)
  if (record) {
    emit('saved', record)
    emit('close')
  }
}
</script>
