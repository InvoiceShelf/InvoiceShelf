<template>
  <BasePage>
    <form
      id="purchase-supplierformview"
      class="space-y-5"
      @submit.prevent="save"
    >
      <PurchaseFormHeader
        kind="suppliers"
        :title="$t(id ? 'purchases.edit_supplier' : 'purchases.new_supplier')"
      >
        <template #actions
          ><BaseButton variant="white" type="button" @click="router.back()">{{
            $t('purchases.cancel')
          }}</BaseButton
          ><BaseButton
            type="submit"
            form="purchase-supplierformview"
            :disabled="editor.saving.value || !editor.ready.value"
            :loading="editor.saving.value"
            >{{ $t('purchases.save_supplier') }}</BaseButton
          ></template
        >
      </PurchaseFormHeader>
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
  </BasePage>
</template>
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useSupplierEditor } from '../composables/use-supplier-editor'
import { providePurchaseLookups } from '../composables/use-purchase-lookups'
import PurchaseFormHeader from '../components/PurchaseFormHeader.vue'
import PurchaseLookupModals from '../components/PurchaseLookupModals.vue'
import SupplierFormFields from '../components/SupplierFormFields.vue'
const route = useRoute(),
  router = useRouter(),
  global = useGlobalStore()
const id = Number(route.params.id) || undefined
const lookups = providePurchaseLookups(),
  editor = useSupplierEditor()
const draft = computed({
  get: () => editor.form,
  set: (value) => Object.assign(editor.form, value),
})
onMounted(() => editor.load(id))
async function save() {
  const record = await editor.save(id)
  if (record) await router.push(`/admin/suppliers/${record.id}/view`)
}
</script>
