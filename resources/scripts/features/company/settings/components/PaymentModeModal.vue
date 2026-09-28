<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useLookupDialog } from '@/scripts/composables/use-lookup-dialog'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { paymentService } from '@/scripts/api/services/payment.service'

interface PaymentModeForm {
  id: number | null
  name: string
}

const props = withDefaults(
  defineProps<{
    show?: boolean
    title?: string
    data?: unknown
    lockTransactionType?: boolean
  }>(),
  { show: undefined, title: '', data: undefined, lockTransactionType: false },
)
const emit = defineEmits<{ close: []; saved: [record: unknown] }>()
const dialog = useLookupDialog(props, 'PaymentModeModal', {
  close: () => emit('close'),
  saved: (record) => emit('saved', record),
})
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const currentPaymentMode = ref<PaymentModeForm>({
  id: null,
  name: '',
})

const modalActive = dialog.active

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3),
    ),
  },
}))

const v$ = useVuelidate(rules, currentPaymentMode)

async function setInitialData(): Promise<void> {
  dialog.clearErrors()
  v$.value.$reset()
  if (dialog.data.value && typeof dialog.data.value === 'number') {
    const response = await paymentService.getMethod(dialog.data.value)
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
  dialog.clearErrors()
  try {
    let saved
    if (currentPaymentMode.value.id) {
      saved = await paymentService.updateMethod(currentPaymentMode.value.id, {
        name: currentPaymentMode.value.name,
      })
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.payment_modes.updated_message',
      })
    } else {
      saved = await paymentService.createMethod({
        name: currentPaymentMode.value.name,
      })
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.payment_modes.created_message',
      })
    }

    isSaving.value = false
    dialog.saved(saved.data)
    closePaymentModeModal()
  } catch (error) {
    dialog.fail(error)
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
  if (!isSaving.value) dialog.close()
}
</script>

<template>
  <BaseModal
    :show="modalActive"
    :closable="!isSaving"
    @close="closePaymentModeModal"
    @open="setInitialData"
  >
    <template #header>
      {{ dialog.title.value }}
    </template>

    <form action="" @submit.stop.prevent="submitPaymentMode">
      <p
        v-if="dialog.error.value"
        role="alert"
        class="px-6 pt-4 text-sm text-danger"
      >
        {{ dialog.error.value }}
      </p>
      <div class="p-4 sm:p-6">
        <BaseInputGroup
          :label="$t('settings.payment_modes.mode_name')"
          :error="
            dialog.errors.value.name?.[0] ||
            (v$.name.$error && v$.name.$errors[0].$message)
          "
          required
        >
          <BaseInput
            v-model="currentPaymentMode.name"
            :invalid="v$.name.$error || !!dialog.errors.value.name"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          variant="primary-outline"
          class="me-3"
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
            currentPaymentMode.id ? $t('general.update') : $t('general.save')
          }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
