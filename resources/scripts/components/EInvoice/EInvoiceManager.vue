<template>
  <div class="e-invoice-manager">
    <div class="space-y-6">

      <!-- E-Invoice Generator -->
      <EInvoiceGenerator
        :invoice="invoice"
        @generated="handleEInvoiceGenerated"
      />

      <!-- Generated E-Invoices List -->
      <EInvoiceList
        ref="eInvoiceList"
        :invoice="invoice"
        @deleted="handleEInvoiceDeleted"
      />

      <!-- Information Panel -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg
              class="h-5 w-5 text-blue-400"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800">
              {{ $t('e_invoice.compliance_info_title') }}
            </h3>
            <div class="mt-2 text-sm text-blue-700">
              <p class="mb-2">
                {{ $t('e_invoice.compliance_info_description') }}
              </p>
              <ul class="list-disc list-inside space-y-1">
                <li>{{ $t('e_invoice.compliance_info_ubl') }}</li>
                <li>{{ $t('e_invoice.compliance_info_cii') }}</li>
                <li>{{ $t('e_invoice.compliance_info_facturx') }}</li>
                <li>{{ $t('e_invoice.compliance_info_zugferd') }}</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref } from 'vue'
import EInvoiceGenerator from './EInvoiceGenerator.vue'
import EInvoiceList from './EInvoiceList.vue'

export default {
  name: 'EInvoiceManager',
  emits: ['refresh-list'],
  components: {
    EInvoiceGenerator,
    EInvoiceList
  },
  props: {
    invoice: {
      type: Object,
      required: true
    }
  },
  setup(props, { emit }) {
    const eInvoiceList = ref(null)

    const handleEInvoiceGenerated = () => {
      // Refresh the list when a new e-invoice is generated
      if (eInvoiceList.value && eInvoiceList.value.loadEInvoices) {
        eInvoiceList.value.loadEInvoices()
      }
    }

    const handleEInvoiceDeleted = () => {
      // Refresh the list when an e-invoice is deleted
      if (eInvoiceList.value && eInvoiceList.value.loadEInvoices) {
        eInvoiceList.value.loadEInvoices()
      }
    }

    return {
      eInvoiceList,
      handleEInvoiceGenerated,
      handleEInvoiceDeleted
    }
  }
}
</script>
