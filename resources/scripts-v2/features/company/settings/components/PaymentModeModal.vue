<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { paymentService } from '@v2/api/services/payment.service'

interface PaymentModeForm {
  id: number | null
  name: string
}

const modalStore = useModalStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const currentPaymentMode = ref<PaymentModeForm>({
  id: null,
  name: '',
})

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'PaymentModeModal'
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3)
    ),
  },
}))

const v$ = useVuelidate(rules, currentPaymentMode)

async function setInitialData(): Promise<void> {
  if (modalStore.data && typeof modalStore.data === 'number') {
    const response = await paymentService.getMethod(modalStore.data)
    if (response.data) {
      currentPaymentMode.value = {
        id: response.data.id,
        name: response.data.name,
      }
    }
  } else {
    resetForm()
  }
}

async function submitPaymentMode(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true
  try {
    if (currentPaymentMode.value.id) {
      await paymentService.updateMethod(currentPaymentMode.value.id, {
        name: currentPaymentMode.value.name,
      })
    } else {
      await paymentService.createMethod({
        name: currentPaymentMode.value.name,
      })
    }

    isSaving.value = false
    if (modalStore.refreshData) {
      modalStore.refreshData()
    }
    closePaymentModeModal()
  } catch {
    isSaving.value = false
  }
}

function resetForm(): void {
  currentPaymentMode.value = {
    id: null,
    name: '',
  }
}

function closePaymentModeModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetForm()
    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal
    :show="modalActive"
    @close="closePaymentModeModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closePaymentModeModal"
        />
      </div>
    </template>

    <form action="" @submit.prevent="submitPaymentMode">
      <div class="p-4 sm:p-6">
        <BaseInputGroup
          :label="$t('settings.payment_modes.mode_name')"
          :error="v$.name.$error && v$.name.$errors[0].$message"
          required
        >
          <BaseInput
            v-model="currentPaymentMode.name"
            :invalid="v$.name.$error"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          variant="primary-outline"
          class="mr-3"
          type="button"
          @click="closePaymentModeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{
            currentPaymentMode.id
              ? $t('general.update')
              : $t('general.save')
          }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
