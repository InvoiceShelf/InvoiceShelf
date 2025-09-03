<template>
  <div class="e-invoice-list">
    <div class="bg-white shadow rounded-lg">
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">
          {{ $t('e_invoice.generated_invoices') }}
        </h3>
        <p class="mt-1 text-sm text-gray-500">
          {{ $t('e_invoice.generated_invoices_description') }}
        </p>
      </div>

      <div class="p-6">
        <div v-if="eInvoices.length === 0" class="text-center py-8">
          <svg
            class="mx-auto h-12 w-12 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900">
            {{ $t('e_invoice.no_invoices') }}
          </h3>
          <p class="mt-1 text-sm text-gray-500">
            {{ $t('e_invoice.no_invoices_description') }}
          </p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="eInvoice in eInvoices"
            :key="eInvoice.id"
            class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                  <div
                    class="w-10 h-10 rounded-full flex items-center justify-center"
                    :class="{
                      'bg-green-100 text-green-600': eInvoice.status === 'generated',
                      'bg-yellow-100 text-yellow-600': eInvoice.status === 'pending',
                      'bg-red-100 text-red-600': eInvoice.status === 'failed'
                    }"
                  >
                    <svg
                      v-if="eInvoice.status === 'generated'"
                      class="w-5 h-5"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd"
                      />
                    </svg>
                    <svg
                      v-else-if="eInvoice.status === 'pending'"
                      class="w-5 h-5 animate-spin"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                      />
                    </svg>
                    <svg
                      v-else
                      class="w-5 h-5"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </div>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center space-x-2">
                    <h4 class="text-sm font-medium text-gray-900">
                      {{ eInvoice.format }}
                    </h4>
                    <span
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                      :class="{
                        'bg-green-100 text-green-800': eInvoice.status === 'generated',
                        'bg-yellow-100 text-yellow-800': eInvoice.status === 'pending',
                        'bg-red-100 text-red-800': eInvoice.status === 'failed'
                      }"
                    >
                      {{ $t(`e_invoice.status.${eInvoice.status}`) }}
                    </span>
                  </div>
                  <div class="mt-1 text-sm text-gray-500">
                    {{ $t('e_invoice.generated_at') }}: 
                    {{ formatDate(eInvoice.generated_at) }}
                  </div>
                </div>
              </div>

              <div class="flex items-center space-x-2">
                <button
                  v-if="eInvoice.status === 'generated'"
                  @click="downloadFile(eInvoice, 'xml')"
                  class="inline-flex items-center px-3 py-1 border border-gray-300 rounded text-xs font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                  <svg
                    class="w-3 h-3 mr-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                  </svg>
                  XML
                </button>

                <button
                  v-if="eInvoice.status === 'generated' && eInvoice.pdf_path"
                  @click="downloadFile(eInvoice, 'pdf')"
                  class="inline-flex items-center px-3 py-1 border border-gray-300 rounded text-xs font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                  <svg
                    class="w-3 h-3 mr-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                  </svg>
                  PDF
                </button>

                <button
                  @click="deleteEInvoice(eInvoice)"
                  class="inline-flex items-center px-3 py-1 border border-red-300 rounded text-xs font-medium text-red-700 bg-white hover:bg-red-50"
                >
                  <svg
                    class="w-3 h-3 mr-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    />
                  </svg>
                  {{ $t('common.delete') }}
                </button>
              </div>
            </div>

            <!-- Error Message -->
            <div
              v-if="eInvoice.status === 'failed' && eInvoice.error_message"
              class="mt-3 p-3 bg-red-50 border border-red-200 rounded-md"
            >
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg
                    class="h-5 w-5 text-red-400"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-red-800">
                    {{ $t('e_invoice.generation_failed') }}
                  </h3>
                  <div class="mt-1 text-sm text-red-700">
                    {{ eInvoice.error_message }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'

export default {
  name: 'EInvoiceList',
  props: {
    invoice: {
      type: Object,
      required: true
    }
  },
  setup(props) {
    const { t } = useI18n()
    const eInvoices = ref([])
    const isLoading = ref(false)

    const loadEInvoices = async () => {
      isLoading.value = true
      try {
        // This would be an endpoint to get all e-invoices for an invoice
        // For now, we'll simulate with the existing files check
        const formats = ['UBL', 'CII', 'Factur-X', 'ZUGFeRD']
        const existingInvoices = []

        for (const format of formats) {
          try {
            const response = await axios.get(`/api/v1/invoices/${props.invoice.id}/e-invoice/exists/${format}`)
            if (response.data.exists) {
              existingInvoices.push({
                id: `${props.invoice.id}_${format}`,
                format,
                status: 'generated',
                generated_at: new Date().toISOString(),
                xml_path: `e-invoices/${props.invoice.company_id}/${props.invoice.id}/${props.invoice.invoice_number}_${format.toLowerCase()}.xml`,
                pdf_path: format === 'Factur-X' || format === 'ZUGFeRD' ? `e-invoices/${props.invoice.company_id}/${props.invoice.id}/${props.invoice.invoice_number}_${format.toLowerCase()}.pdf` : null
              })
            }
          } catch (error) {
            // Format doesn't exist, skip
          }
        }

        eInvoices.value = existingInvoices
      } catch (error) {
        console.error(t('e_invoice.messages.load_invoices_error'), error)
      } finally {
        isLoading.value = false
      }
    }

    const downloadFile = (eInvoice, type) => {
      const url = `/api/v1/invoices/${props.invoice.id}/e-invoice/download/${eInvoice.format}/${type}`
      const link = document.createElement('a')
      link.href = url
      link.download = `${props.invoice.invoice_number}_${eInvoice.format}.${type}`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    }

    const deleteEInvoice = async (eInvoice) => {
      if (!confirm(t('e_invoice.confirm_delete'))) {
        return
      }

      try {
        await axios.delete(`/api/v1/invoices/${props.invoice.id}/e-invoice/${eInvoice.format}`)
        await loadEInvoices()
      } catch (error) {
        console.error(t('e_invoice.messages.delete_error'), error)
      }
    }

    const formatDate = (dateString) => {
      if (!dateString) return t('common.never')
      return new Date(dateString).toLocaleString()
    }

    onMounted(() => {
      loadEInvoices()
    })

    return {
      eInvoices,
      isLoading,
      downloadFile,
      deleteEInvoice,
      formatDate
    }
  }
}
</script>
