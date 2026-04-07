<template>
  <BaseModal
    :show="modalActive"
    @close="closeSendPaymentModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalTitle }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeSendPaymentModal"
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
              v-model="paymentMailForm.from"
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
              v-model="paymentMailForm.to"
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
              v-model="paymentMailForm.cc"
              type="email"
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
              v-model="paymentMailForm.bcc"
              type="email"
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
              v-model="paymentMailForm.subject"
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
              v-model="paymentMailForm.body"
              :fields="['customer', 'company', 'payment']"
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
          @click="closeSendPaymentModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          type="button"
          class="mr-3"
          @click="sendPaymentData"
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
        ></iframe>
      </div>
      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeSendPaymentModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          type="button"
          @click="sendPaymentData()"
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
import { required, email, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useModalStore } from '../../../../stores/modal.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import { usePaymentStore } from '../store'

const modalStore = useModalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const paymentStore = usePaymentStore()

const { t } = useI18n()
const isLoading = ref(false)
const templateUrl = ref<string>('')
const isPreview = ref(false)

const paymentMailForm = reactive({
  id: null as number | null,
  from: null as string | null,
  to: null as string | null,
  cc: null as string | null,
  bcc: null as string | null,
  subject: t('payments.new_payment'),
  body: null as string | null,
})

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'SendPaymentModal'
})

const modalTitle = computed(() => {
  return modalStore.title
})

const modalData = computed(() => {
  return modalStore.data as Record<string, unknown> | null
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

const v$ = useVuelidate(rules, paymentMailForm)

function cancelPreview() {
  isPreview.value = false
}

async function setInitialData() {
  const admin = await companyStore.fetchBasicMailConfig()
  paymentMailForm.id = modalStore.id as number

  if (admin.from_mail) {
    paymentMailForm.from = admin.from_mail as string
  }

  if (modalData.value) {
    const customer = modalData.value.customer as Record<string, string> | undefined
    if (customer) {
      paymentMailForm.to = customer.email
    }
  }

  paymentMailForm.body = companyStore.selectedCompanySettings.payment_mail_body
  paymentMailForm.subject = t('payments.new_payment')
}

async function sendPaymentData() {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return true
  }

  try {
    isLoading.value = true

    if (!isPreview.value) {
      const previewResponse = await paymentStore.previewPayment(
        paymentMailForm as unknown as { id: number } & Record<string, unknown>,
      )
      isLoading.value = false

      isPreview.value = true
      const blob = new Blob(
        [(previewResponse as { data: string }).data ?? previewResponse],
        { type: 'text/html' },
      )
      templateUrl.value = URL.createObjectURL(blob)

      return
    }

    await paymentStore.sendEmail(
      paymentMailForm as unknown as Parameters<typeof paymentStore.sendEmail>[0],
    )

    isLoading.value = false

    notificationStore.showNotification({
      type: 'success',
      message: 'payments.payment_sent_successfully',
    })

    if (modalStore.refreshData) {
      modalStore.refreshData()
    }

    closeSendPaymentModal()
  } catch (error) {
    console.error(error)
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: 'payments.something_went_wrong',
    })
  }
}

function closeSendPaymentModal() {
  modalStore.closeModal()

  setTimeout(() => {
    v$.value.$reset()
    isPreview.value = false
    templateUrl.value = ''
  }, 300)
}
</script>
