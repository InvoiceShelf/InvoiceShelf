<template>
  <BaseModal
    :show="modalActive"
    @close="closeModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalTitle }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <form v-if="!isPreview" @submit.prevent>
      <div class="px-8 py-8 sm:p-6">
        <BaseInputGrid layout="one-column" class="col-span-7">
          <BaseInputGroup
            :label="$t('general.from')"
            required
            :error="
              v$.from.$error ? (v$.from.$errors[0]?.$message as string) : undefined
            "
          >
            <BaseInput
              v-model="form.from"
              type="text"
              :invalid="v$.from.$error"
              @input="v$.from.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('general.to')"
            required
            :error="
              v$.to.$error ? (v$.to.$errors[0]?.$message as string) : undefined
            "
          >
            <BaseInput
              v-model="form.to"
              type="text"
              :invalid="v$.to.$error"
              @input="v$.to.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('general.cc')"
            :error="
              v$.cc.$error ? (v$.cc.$errors[0]?.$message as string) : undefined
            "
          >
            <BaseInput
              v-model="form.cc"
              type="text"
              :invalid="v$.cc.$error"
              @input="v$.cc.$touch()"
              placeholder="Optional: CC recipient"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('general.bcc')"
            :error="
              v$.bcc.$error ? (v$.bcc.$errors[0]?.$message as string) : undefined
            "
          >
            <BaseInput
              v-model="form.bcc"
              type="text"
              :invalid="v$.bcc.$error"
              @input="v$.bcc.$touch()"
              placeholder="Optional: BCC recipient"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('general.subject')"
            required
            :error="
              v$.subject.$error
                ? (v$.subject.$errors[0]?.$message as string)
                : undefined
            "
          >
            <BaseInput
              v-model="form.subject"
              type="text"
              :invalid="v$.subject.$error"
              @input="v$.subject.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('general.body')"
            required
            :error="
              v$.body.$error ? (v$.body.$errors[0]?.$message as string) : undefined
            "
          >
            <BaseCustomInput
              v-model="form.body"
              :fields="invoiceMailFields"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeModal"
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
      <div class="my-6 mx-4 border border-line-default relative">
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
        />
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          type="button"
          @click="submitForm"
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

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import { required, email, helpers } from '@vuelidate/validators'
import { useModalStore } from '../../../../stores/modal.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import { useInvoiceStore } from '../store'

interface InvoiceMailForm {
  id: number | string | null
  from: string | null
  to: string | null
  cc: string | null
  bcc: string | null
  subject: string
  body: string | null
}

type FieldType = 'customer' | 'customerCustom' | 'invoice' | 'invoiceCustom' | 'company'

const modalStore = useModalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const invoiceStore = useInvoiceStore()
const { t } = useI18n()

const isLoading = ref<boolean>(false)
const templateUrl = ref<string>('')
const isPreview = ref<boolean>(false)

const invoiceMailFields = ref<FieldType[]>([
  'customer',
  'customerCustom',
  'invoice',
  'invoiceCustom',
  'company',
])

const form = reactive<InvoiceMailForm>({
  id: null,
  from: null,
  to: null,
  cc: null,
  bcc: null,
  subject: t('invoices.new_invoice'),
  body: null,
})

const modalActive = computed<boolean>(() => {
  return modalStore.active && modalStore.componentName === 'SendInvoiceModal'
})

const modalTitle = computed<string>(() => {
  return modalStore.title
})

const modalData = computed<Record<string, unknown> | null>(() => {
  return modalStore.data as Record<string, unknown> | null
})

const rules = computed(() => ({
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
}))

const v$ = useVuelidate(rules, form)

function cancelPreview(): void {
  isPreview.value = false
}

async function setInitialData(): Promise<void> {
  try {
    const admin = await companyStore.fetchBasicMailConfig()

    form.id = modalStore.id

    if (admin?.data) {
      form.from = (admin.data as Record<string, unknown>).from_mail as string
    }

    if (modalData.value?.customer) {
      const customer = modalData.value.customer as Record<string, unknown>
      form.to = (customer.email as string) ?? null
    }

    form.body = companyStore.selectedCompanySettings.invoice_mail_body ?? null
  } catch {
    // Silently handle initialization errors
  }
}

async function submitForm(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  try {
    isLoading.value = true

    if (!isPreview.value) {
      const previewResponse = await invoiceStore.previewInvoice({
        id: form.id as number,
        from: form.from,
        to: form.to,
        cc: form.cc,
        bcc: form.bcc,
        subject: form.subject,
        body: form.body,
      })
      isLoading.value = false

      isPreview.value = true
      const blob = new Blob(
        [(previewResponse as { data: string }).data ?? previewResponse],
        { type: 'text/html' },
      )
      templateUrl.value = URL.createObjectURL(blob)
      return
    }

    await invoiceStore.sendInvoice({
      id: form.id as number,
      from: form.from,
      to: form.to,
      cc: form.cc,
      bcc: form.bcc,
      subject: form.subject,
      body: form.body,
    })

    isLoading.value = false

    notificationStore.showNotification({
      type: 'success',
      message: 'invoices.invoice_sent_successfully',
    })

    if (modalStore.refreshData) {
      modalStore.refreshData()
    }

    closeModal()
  } catch {
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: t('invoices.something_went_wrong'),
    })
  }
}

function closeModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    v$.value.$reset()
    isPreview.value = false
    templateUrl.value = ''
  }, 300)
}
</script>
