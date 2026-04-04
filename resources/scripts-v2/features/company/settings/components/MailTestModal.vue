<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, email, maxLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { mailService } from '@v2/api/services/mail.service'
import { companyService } from '@v2/api/services/company.service'

interface MailTestForm {
  to: string
  subject: string
  message: string
}

const props = withDefaults(
  defineProps<{
    storeType?: string
  }>(),
  {
    storeType: 'global',
  }
)

const modalStore = useModalStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const formData = reactive<MailTestForm>({
  to: '',
  subject: '',
  message: '',
})

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'MailTestModal'
)

const rules = {
  to: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  subject: {
    required: helpers.withMessage(t('validation.required'), required),
    maxLength: helpers.withMessage(
      t('validation.subject_maxlength'),
      maxLength(100)
    ),
  },
  message: {
    required: helpers.withMessage(t('validation.required'), required),
    maxLength: helpers.withMessage(
      t('validation.message_maxlength'),
      maxLength(255)
    ),
  },
}

const v$ = useVuelidate({ formData: rules }, { formData })

function resetFormData(): void {
  formData.to = ''
  formData.subject = ''
  formData.message = ''
  v$.value.$reset()
}

async function onTestMailSend(): Promise<void> {
  v$.value.formData.$touch()
  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true
  try {
    await mailService.testEmail({ to: formData.to })
    closeTestModal()
  } finally {
    isSaving.value = false
  }
}

function closeTestModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetFormData()
  }, 300)
}
</script>

<template>
  <BaseModal :show="modalActive" @close="closeTestModal">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeTestModal"
        />
      </div>
    </template>
    <form action="" @submit.prevent="onTestMailSend">
      <div class="p-4 md:p-8">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('general.to')"
            :error="
              v$.formData.to.$error && v$.formData.to.$errors[0].$message
            "
            variant="horizontal"
            required
          >
            <BaseInput
              v-model="formData.to"
              type="text"
              :invalid="v$.formData.to.$error"
              @input="v$.formData.to.$touch()"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.subject')"
            :error="
              v$.formData.subject.$error &&
              v$.formData.subject.$errors[0].$message
            "
            variant="horizontal"
            required
          >
            <BaseInput
              v-model="formData.subject"
              type="text"
              :invalid="v$.formData.subject.$error"
              @input="v$.formData.subject.$touch()"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.message')"
            :error="
              v$.formData.message.$error &&
              v$.formData.message.$errors[0].$message
            "
            variant="horizontal"
            required
          >
            <BaseTextarea
              v-model="formData.message"
              rows="4"
              cols="50"
              :invalid="v$.formData.message.$error"
              @input="v$.formData.message.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>
      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          variant="primary-outline"
          type="button"
          class="mr-3"
          @click="closeTestModal()"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton :loading="isSaving" variant="primary" type="submit">
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="PaperAirplaneIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('general.send') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
