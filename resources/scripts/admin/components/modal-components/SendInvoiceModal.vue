<template>
  <BaseModal
    :show="modalActive"
    @close="closeSendInvoiceModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalTitle }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-gray-500 cursor-pointer"
          @click="closeSendInvoiceModal"
        />
      </div>
    </template>
    <form v-if="!isPreview" action="">
      <div class="px-8 py-8 sm:p-6">
        <BaseInputGrid layout="one-column" class="col-span-7">
          <BaseInputGroup
            :label="$t('general.from')"
            required
            :error="v$.from.$error && v$.from.$errors[0].$message"
          >
            <BaseInput
              v-model="invoiceMailForm.from"
              type="text"
              :invalid="v$.from.$error"
              @input="v$.from.$touch()"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.to')"
            required
            :error="v$.to.$error && v$.to.$errors[0].$message"
          >
            <BaseInput
              v-model="invoiceMailForm.to"
              type="text"
              :invalid="v$.to.$error"
              @input="v$.to.$touch()"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.cc')"
            :error="v$.cc && v$.cc.$error && v$.cc.$errors[0].$message"
          >
            <BaseInput
              v-model="invoiceMailForm.cc"
              type="text"
              :invalid="v$.cc && v$.cc.$error"
              @input="v$.cc && v$.cc.$touch()"
              placeholder="Optional: CC recipient"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.bcc')"
            :error="v$.bcc && v$.bcc.$error && v$.bcc.$errors[0].$message"
          >
            <BaseInput
              v-model="invoiceMailForm.bcc"
              type="text"
              :invalid="v$.bcc && v$.bcc.$error"
              @input="v$.bcc && v$.bcc.$touch()"
              placeholder="Optional: BCC recipient"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :error="v$.subject.$error && v$.subject.$errors[0].$message"
            :label="$t('general.subject')"
            required
          >
            <BaseInput
              v-model="invoiceMailForm.subject"
              type="text"
              :invalid="v$.subject.$error"
              @input="v$.subject.$touch()"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.body')"
            :error="v$.body.$error && v$.body.$errors[0].$message"
            required
          >
            <BaseCustomInput
              v-model="invoiceMailForm.body"
              :fields="invoiceMailFields"
            />
          </BaseInputGroup>
          <BaseInputGroup :label="$t('general.attachments')">
            <BaseFileUploader
              v-model="attachmentFiles"
              multiple
              preserve-local-files
              input-field-name="attachments"
              accept="image/*,.doc,.docx,.pdf,.csv,.xlsx,.xls,.ppt,.pptx"
              @change="onAttachmentChange"
              @remove="onAttachmentRemove"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>
      <div
        class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeSendInvoiceModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          type="button"
          class="mr-3"
          @click="submitForm"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isLoading"
              :class="slotProps.class"
              name="PhotoIcon"
            />
          </template>
          {{ $t('general.preview') }}
        </BaseButton>
      </div>
    </form>
    <div v-else>
      <div class="my-6 mx-4 border border-gray-200 relative">
        <BaseButton
          class="absolute top-4 right-4"
          :disabled="isLoading"
          variant="primary-outline"
          @click="cancelPreview"
        >
          <BaseIcon name="PencilIcon" class="h-5 mr-2" />
          {{ $t('general.edit') }}
        </BaseButton>

        <iframe
          :src="templateUrl"
          frameborder="0"
          class="w-full"
          style="min-height: 500px"
        ></iframe>
      </div>
      <div
        class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeSendInvoiceModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          type="button"
          @click="submitForm()"
        >
          <BaseIcon
            v-if="!isLoading"
            name="PaperAirplaneIcon"
            class="h-5 mr-2"
          />
          {{ $t('general.send') }}
        </BaseButton>
      </div>
    </div>
  </BaseModal>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { useModalStore } from '@/scripts/stores/modal'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useI18n } from 'vue-i18n'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useVuelidate } from '@vuelidate/core'
import { required, email, helpers } from '@vuelidate/validators'
import { useMailDriverStore } from '@/scripts/admin/stores/mail-driver'

const modalStore = useModalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const invoiceStore = useInvoiceStore()
const mailDriverStore = useMailDriverStore()

const { t } = useI18n()
let isLoading = ref(false)
const templateUrl = ref('')
const isPreview = ref(false)

const emit = defineEmits(['update'])

const invoiceMailFields = ref([
  'customer',
  'customerCustom',
  'invoice',
  'invoiceCustom',
  'company',
])

const invoiceMailForm = reactive({
  id: null,
  from: null,
  to: null,
  cc: null,
  bcc: null,
  subject: t('invoices.new_invoice'),
  body: null,
  attachments: [],
})

const attachmentFiles = ref([])

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'SendInvoiceModal'
})

const modalTitle = computed(() => {
  return modalStore.title
})

const modalData = computed(() => {
  return modalStore.data
})

const rules = {
  from: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  to: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  cc: {
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  bcc: {
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  subject: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  body: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => invoiceMailForm)
)

function cancelPreview() {
  isPreview.value = false
}

async function setInitialData() {
  let admin = await companyStore.fetchBasicMailConfig()

  invoiceMailForm.id = modalStore.id

  if (admin.data) {
    invoiceMailForm.from = admin.data.from_mail
  }

  if (modalData.value) {
    invoiceMailForm.to = modalData.value.customer.email
  }

  invoiceMailForm.body = companyStore.selectedCompanySettings.invoice_mail_body
  invoiceMailForm.attachments = []
  attachmentFiles.value = []
}

async function submitForm() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return true
  }

  try {
    isLoading.value = true

    if (!isPreview.value) {
      const previewData = { ...invoiceMailForm }
      delete previewData.attachments

      const previewResponse = await invoiceStore.previewInvoice(previewData)
      isLoading.value = false

      isPreview.value = true
      var blob = new Blob([previewResponse.data], { type: 'text/html' })
      templateUrl.value = URL.createObjectURL(blob)

      return
    }

    const response = await invoiceStore.sendInvoice(invoiceMailForm)

    isLoading.value = false

    if (response.data.success) {
      emit('update', modalStore.id)
      closeSendInvoiceModal()
      return true
    }
  } catch (error) {
    console.error(error)
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: t('invoices.something_went_wrong'),
    })
  }
}

function closeSendInvoiceModal() {
  modalStore.closeModal()
  setTimeout(() => {
    v$.value.$reset()
    isPreview.value = false
    templateUrl.value = null
    invoiceMailForm.attachments = []
    attachmentFiles.value = []
  }, 300)
}

function onAttachmentChange(_fieldName, fileList) {
  const newFiles = Array.from(fileList || [])

  if (!newFiles.length) {
    return
  }

  if (invoiceMailForm.attachments.length) {
    invoiceMailForm.attachments = [
      ...invoiceMailForm.attachments,
      ...newFiles,
    ]
  } else {
    invoiceMailForm.attachments = newFiles
  }
}

function onAttachmentRemove(index) {
  if (Array.isArray(invoiceMailForm.attachments)) {
    invoiceMailForm.attachments.splice(index, 1)
  }

  if (Array.isArray(attachmentFiles.value)) {
    attachmentFiles.value.splice(index, 1)
  }
}
</script>
