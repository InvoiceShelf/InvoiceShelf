<template>
  <BaseModal :show="modalActive" @close="closeEInvoiceModal">
    <template #header>
      <div class="flex justify-between w-full">
        <div class="flex-1">
          <h2 class="text-xl font-semibold text-gray-900">
            {{ $t('e_invoice.manager_title') }}
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            {{ $t('e_invoice.manager_description') }}
          </p>
        </div>
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeEInvoiceModal"
        />
      </div>
    </template>
    
    <div class="e-invoice-modal p-6">
      <EInvoiceManager v-if="invoiceData" :invoice="invoiceData" />
      <div v-else class="flex items-center justify-center py-8">
        <LoadingIcon class="h-8 w-8 text-gray-400" />
      </div>
    </div>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useModalStore } from '@/scripts/stores/modal'
import EInvoiceManager from '@/scripts/components/EInvoice/EInvoiceManager.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'

const modalStore = useModalStore()
const invoiceData = ref(null)

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'EInvoiceModal'
})

// Watch for modal activation and set invoice data
watch(() => modalStore.active, (isActive) => {
  if (isActive && modalStore.componentName === 'EInvoiceModal') {
    // Use the invoice data passed from the dropdown (like SendInvoiceModal does)
    if (modalStore.data) {
      invoiceData.value = modalStore.data
    } else {
      invoiceData.value = null
    }
  }
}, { immediate: true })

function closeEInvoiceModal() {
  modalStore.closeModal()
}
</script>
