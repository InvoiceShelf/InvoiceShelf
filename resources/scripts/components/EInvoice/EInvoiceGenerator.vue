<template>
  <div class="e-invoice-generator">
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-gray-900">
          {{ $t('e_invoice.generation') }}
        </h3>
        <div class="flex items-center space-x-2">
          <span class="text-sm text-gray-500">
            {{ $t('e_invoice.compliance') }}
          </span>
          <div class="flex items-center">
            <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
            <span class="text-xs text-green-600 font-medium">
              {{ $t('e_invoice.eu_compliant') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Format Selection -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-3">
          {{ $t('e_invoice.select_format') }}
        </label>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div
            v-for="format in supportedFormats"
            :key="format.value"
            class="relative"
          >
            <input
              :id="format.value"
              v-model="selectedFormat"
              :value="format.value"
              type="radio"
              class="sr-only"
            />
            <label
              :for="format.value"
              class="flex flex-col items-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
              :class="{
                'border-blue-500 bg-blue-50': selectedFormat === format.value,
                'border-gray-200 hover:border-gray-300': selectedFormat !== format.value
              }"
            >
              <div class="text-2xl mb-2">{{ format.icon }}</div>
              <div class="text-sm font-medium text-gray-900">{{ format.name }}</div>
              <div class="text-xs text-gray-500 text-center mt-1">
                {{ format.description }}
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- Validation Status -->
      <div v-if="validationResult" class="mb-6">
        <div
          class="p-4 rounded-lg"
          :class="{
            'bg-green-50 border border-green-200': validationResult.valid,
            'bg-red-50 border border-red-200': !validationResult.valid
          }"
        >
          <div class="flex items-center">
            <div
              class="flex-shrink-0"
              :class="{
                'text-green-400': validationResult.valid,
                'text-red-400': !validationResult.valid
              }"
            >
              <svg
                v-if="validationResult.valid"
                class="h-5 w-5"
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
                v-else
                class="h-5 w-5"
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
              <h3
                class="text-sm font-medium"
                :class="{
                  'text-green-800': validationResult.valid,
                  'text-red-800': !validationResult.valid
                }"
              >
                {{ validationResult.valid ? $t('e_invoice.valid') : $t('e_invoice.invalid') }}
              </h3>
              <div
                v-if="!validationResult.valid"
                class="mt-2 text-sm"
                :class="{
                  'text-green-700': validationResult.valid,
                  'text-red-700': !validationResult.valid
                }"
              >
                <ul class="list-disc list-inside space-y-1">
                  <li v-for="error in validationResult.errors" :key="error">
                    {{ error }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Generation Options -->
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input
              id="async-generation"
              v-model="asyncGeneration"
              type="checkbox"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
            <label for="async-generation" class="ml-2 block text-sm text-gray-900">
              {{ $t('e_invoice.async_generation') }}
            </label>
          </div>
          <div class="text-sm text-gray-500">
            {{ $t('e_invoice.async_description') }}
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <button
            @click="validateInvoice"
            :disabled="isValidating"
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
          >
            <svg
              v-if="isValidating"
              class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
            <svg
              v-else
              class="-ml-1 mr-2 h-4 w-4 text-gray-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            {{ $t('e_invoice.validate') }}
          </button>

          <button
            @click="generateEInvoice"
            :disabled="!selectedFormat || isGenerating || (validationResult && !validationResult.valid)"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
          >
            <svg
              v-if="isGenerating"
              class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
            <svg
              v-else
              class="-ml-1 mr-2 h-4 w-4 text-white"
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
            {{ $t('e_invoice.generate') }}
          </button>
        </div>

        <!-- Download Links -->
        <div v-if="generatedFiles.length > 0" class="flex items-center space-x-2">
          <span class="text-sm text-gray-500">{{ $t('e_invoice.download') }}:</span>
          <div class="flex space-x-2">
            <a
              v-for="file in generatedFiles"
              :key="file.type"
              :href="file.url"
              :download="file.filename"
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
              {{ file.type.toUpperCase() }}
            </a>
          </div>
        </div>
      </div>

      <!-- Status Messages -->
      <div v-if="statusMessage" class="mt-4">
        <div
          class="p-4 rounded-lg"
          :class="{
            'bg-blue-50 border border-blue-200': statusMessage.type === 'info',
            'bg-green-50 border border-green-200': statusMessage.type === 'success',
            'bg-red-50 border border-red-200': statusMessage.type === 'error'
          }"
        >
          <div class="flex">
            <div class="flex-shrink-0">
              <svg
                v-if="statusMessage.type === 'success'"
                class="h-5 w-5 text-green-400"
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
                v-else-if="statusMessage.type === 'error'"
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
              <svg
                v-else
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
              <p
                class="text-sm font-medium"
                :class="{
                  'text-blue-800': statusMessage.type === 'info',
                  'text-green-800': statusMessage.type === 'success',
                  'text-red-800': statusMessage.type === 'error'
                }"
              >
                {{ statusMessage.message }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

export default {
  name: 'EInvoiceGenerator',
  props: {
    invoice: {
      type: Object,
      required: true
    }
  },
  setup(props) {
    const { t } = useI18n()
    
    const selectedFormat = ref('UBL')
    const asyncGeneration = ref(true)
    const isValidating = ref(false)
    const isGenerating = ref(false)
    const validationResult = ref(null)
    const statusMessage = ref(null)
    const generatedFiles = ref([])
    const supportedFormats = ref([
      {
        value: 'UBL',
        name: t('e_invoice.formats.ubl.name'),
        description: t('e_invoice.formats.ubl.description'),
        icon: '📄'
      },
      {
        value: 'CII',
        name: t('e_invoice.formats.cii.name'),
        description: t('e_invoice.formats.cii.description'),
        icon: '📋'
      },
      {
        value: 'Factur-X',
        name: t('e_invoice.formats.facturx.name'),
        description: t('e_invoice.formats.facturx.description'),
        icon: '📊'
      },
      {
        value: 'ZUGFeRD',
        name: t('e_invoice.formats.zugferd.name'),
        description: t('e_invoice.formats.zugferd.description'),
        icon: '📈'
      }
    ])

    const validateInvoice = async () => {
      if (!selectedFormat.value) return

      isValidating.value = true
      statusMessage.value = null

      try {
        const response = await axios.get(`/api/v1/invoices/${props.invoice.id}/e-invoice/validate`, {
          params: { format: selectedFormat.value }
        })

        validationResult.value = response.data
        statusMessage.value = {
          type: response.data.valid ? 'success' : 'error',
          message: response.data.valid 
            ? t('e_invoice.messages.validation_success')
            : t('e_invoice.messages.validation_failed')
        }
      } catch (error) {
        statusMessage.value = {
          type: 'error',
          message: t('e_invoice.messages.validation_error')
        }
      } finally {
        isValidating.value = false
      }
    }

    const generateEInvoice = async () => {
      if (!selectedFormat.value) return

      isGenerating.value = true
      statusMessage.value = null

      try {
        const response = await axios.post(`/api/v1/invoices/${props.invoice.id}/e-invoice/generate`, {
          format: selectedFormat.value,
          async: asyncGeneration.value
        })

        if (asyncGeneration.value) {
          statusMessage.value = {
            type: 'info',
            message: 'E-invoice generation started. You will be notified when it\'s ready.'
          }
        } else {
          statusMessage.value = {
            type: 'success',
            message: 'E-invoice generated successfully'
          }
          
          // Update generated files
          if (response.data.files) {
            generatedFiles.value = Object.entries(response.data.files).map(([type, path]) => ({
              type,
              url: `/api/v1/invoices/${props.invoice.id}/e-invoice/download/${selectedFormat.value}/${type}`,
              filename: `${props.invoice.invoice_number}_${selectedFormat.value}.${type}`
            }))
          }
        }
      } catch (error) {
        statusMessage.value = {
          type: 'error',
          message: error.response?.data?.error || t('e_invoice.messages.generation_error')
        }
      } finally {
        isGenerating.value = false
      }
    }

    const checkExistingFiles = async () => {
      try {
        const response = await axios.get(`/api/v1/invoices/${props.invoice.id}/e-invoice/exists/${selectedFormat.value}`)
        
        if (response.data.exists) {
          generatedFiles.value = [
            {
              type: 'xml',
              url: `/api/v1/invoices/${props.invoice.id}/e-invoice/download/${selectedFormat.value}/xml`,
              filename: `${props.invoice.invoice_number}_${selectedFormat.value}.xml`
            }
          ]
        }
      } catch (error) {
        console.error(t('e_invoice.messages.check_files_error'), error)
      }
    }

    onMounted(() => {
      checkExistingFiles()
    })

    return {
      t,
      selectedFormat,
      asyncGeneration,
      isValidating,
      isGenerating,
      validationResult,
      statusMessage,
      generatedFiles,
      supportedFormats,
      validateInvoice,
      generateEInvoice
    }
  }
}
</script>
