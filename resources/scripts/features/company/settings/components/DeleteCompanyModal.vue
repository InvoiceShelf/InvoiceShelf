<script setup lang="ts">
import { computed, ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { required, helpers, sameAs } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useGlobalStore } from '@/scripts/stores/global.store'

const companyStore = useCompanyStore()
const modalStore = useModalStore()
const globalStore = useGlobalStore()
const router = useRouter()
const { t } = useI18n()

const isDeleting = ref<boolean>(false)

const formData = reactive<{ id: number | null; name: string }>({
  id: null,
  name: '',
})

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'DeleteCompanyModal',
)

const companyName = computed<string>(
  () => companyStore.selectedCompany?.name ?? '',
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    sameAsName: helpers.withMessage(
      t('validation.company_name_not_same'),
      sameAs(companyName.value),
    ),
  },
}))

const v$ = useVuelidate(rules, formData)

function setInitialData(): void {
  formData.id = companyStore.selectedCompany?.id ?? null
  formData.name = ''
}

async function submitDelete(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isDeleting.value = true
  try {
    await companyStore.deleteCompany(formData)

    closeModal()

    const remaining = companyStore.companies.filter(
      (c) => c.id !== formData.id,
    )
    if (remaining.length) {
      companyStore.setSelectedCompany(remaining[0])
    }

    router.push('/admin/dashboard')
    globalStore.setIsAppLoaded(false)
    await globalStore.bootstrap()
  } catch {
    // Error handled
  } finally {
    isDeleting.value = false
  }
}

function closeModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    formData.id = null
    formData.name = ''
    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal :show="modalActive" @close="closeModal" @open="setInitialData">
    <div class="px-6 pt-6">
      <h6 class="font-medium text-lg text-heading">
        {{ $t('settings.company_info.are_you_absolutely_sure') }}
      </h6>
      <p class="mt-2 text-sm text-muted" style="max-width: 680px">
        {{ $t('settings.company_info.delete_company_modal_desc', { company: companyName }) }}
      </p>
    </div>

    <form @submit.prevent="submitDelete">
      <div class="p-4 sm:p-6 space-y-4">
        <BaseInputGroup
          :label="$t('settings.company_info.delete_company_modal_label', { company: companyName })"
          :error="v$.name.$error ? String(v$.name.$errors[0]?.$message) : undefined"
          required
        >
          <BaseInput
            v-model="formData.name"
            :invalid="v$.name.$error"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>
      </div>

      <div class="z-0 flex justify-end p-4 bg-surface-secondary border-t border-line-default">
        <BaseButton
          class="mr-3 text-sm"
          variant="primary-outline"
          type="button"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isDeleting"
          :disabled="isDeleting"
          variant="danger"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isDeleting"
              name="TrashIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('general.delete') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
