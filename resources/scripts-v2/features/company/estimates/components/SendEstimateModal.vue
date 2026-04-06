<template>
  <BaseModal
    :show="modalActive"
    @close="closeSendEstimateModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeSendEstimateModal"
        />
      </div>
    </template>

    <form v-if="!isPreview" action="">
      <div class="px-8 py-8 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('general.from')"
            required
            :error="v$.from.$error && v$.from.$errors[0].$message"
          >
            <BaseInput
              v-model="estimateMailForm.from"
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
              v-model="estimateMailForm.to"
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
              v-model="estimateMailForm.cc"
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
              v-model="estimateMailForm.bcc"
              type="email"
              :invalid="v$.bcc && v$.bcc.$error"
              @input="v$.bcc && v$.bcc.$touch()"
              placeholder="Optional: BCC recipient"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.subject')"
            required
            :error="v$.subject.$error && v$.subject.$errors[0].$message"
          >
            <BaseInput
              v-model="estimateMailForm.subject"
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
              v-model="estimateMailForm.body"
              :fields="['customer', 'company', 'estimate']"
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
          @click="closeSendEstimateModal"
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
          <BaseIcon v-if="!isLoading" name="PhotoIcon" class="h-5 mr-2" />
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
          @click="closeSendEstimateModal"
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
import { required, email, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useModalStore } from '../../../../stores/modal.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import { useEstimateStore } from '../store'

const modalStore = useModalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const estimateStore = useEstimateStore()

const { t } = useI18n()
const isLoading = ref(false)
const templateUrl = ref<string>('')
const isPreview = ref(false)

const emit = defineEmits(['update'])

const estimateMailForm = reactive({
  id: null as number | null,
  from: null as string | null,
  to: null as string | null,
  cc: null as string | null,
  bcc: null as string | null,
  subject: t('estimates.new_estimate'),
  body: null as string | null,
})

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'SendEstimateModal'
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

const v$ = useVuelidate(
  rules,
  computed(() => estimateMailForm),
)

function cancelPreview() {
  isPreview.value = false
}

async function setInitialData() {
  const admin = await companyStore.fetchBasicMailConfig()

  estimateMailForm.id = modalStore.id as number

  if (admin.data) {
    estimateMailForm.from = (admin.data as Record<string, string>).from_mail
  }

  if (modalData.value) {
    const customer = modalData.value.customer as Record<string, string> | undefined
    if (customer) {
      estimateMailForm.to = customer.email
    }
  }

  estimateMailForm.body =
    companyStore.selectedCompanySettings.estimate_mail_body
  estimateMailForm.subject = t('estimates.new_estimate')
}

async function submitForm() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return true
  }

  try {
    isLoading.value = true

    if (!isPreview.value) {
      const previewResponse = await estimateStore.previewEstimate(
        estimateMailForm as unknown as { id: number },
      )
      isLoading.value = false

      isPreview.value = true
      const blob = new Blob([(previewResponse as { data: string }).data], {
        type: 'text/html',
      })
      templateUrl.value = URL.createObjectURL(blob)

      return
    }

    await estimateStore.sendEstimate(
      estimateMailForm as unknown as Parameters<typeof estimateStore.sendEstimate>[0],
    )

    isLoading.value = false

    notificationStore.showNotification({
      type: 'success',
      message: 'estimates.estimate_sent_successfully',
    })

    if (modalStore.refreshData) {
      modalStore.refreshData()
    }

    emit('update')
    closeSendEstimateModal()
  } catch (error) {
    console.error(error)
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: 'estimates.something_went_wrong',
    })
  }
}

function closeSendEstimateModal() {
  modalStore.closeModal()

  setTimeout(() => {
    v$.value.$reset()
    isPreview.value = false
    templateUrl.value = ''
  }, 300)
}
</script>
