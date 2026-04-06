<script setup lang="ts">
import { computed, ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { required, minLength, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { useCompanyStore } from '@v2/stores/company.store'
import { useGlobalStore } from '@v2/stores/global.store'

const router = useRouter()
const modalStore = useModalStore()
const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const previewLogo = ref<string | null>(null)
const companyLogoFileBlob = ref<string | null>(null)
const companyLogoName = ref<string | null>(null)

const newCompanyForm = reactive<{
  name: string
  currency: string | number
  address: { country_id: number | null }
}>({
  name: '',
  currency: '',
  address: {
    country_id: null,
  },
})

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'CompanyModal',
)

const rules = computed(() => ({
  newCompanyForm: {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
      minLength: helpers.withMessage(
        t('validation.name_min_length', { count: 3 }),
        minLength(3),
      ),
    },
    address: {
      country_id: {
        required: helpers.withMessage(t('validation.required'), required),
      },
    },
    currency: {
      required: helpers.withMessage(t('validation.required'), required),
    },
  },
}))

const v$ = useVuelidate(rules, { newCompanyForm })

async function getInitials(): Promise<void> {
  isFetchingInitialData.value = true
  await globalStore.fetchCurrencies()
  await globalStore.fetchCountries()

  newCompanyForm.currency = companyStore.selectedCompanyCurrency?.id ?? ''
  newCompanyForm.address.country_id =
    (companyStore.selectedCompany as Record<string, unknown>)?.address
      ? ((companyStore.selectedCompany as Record<string, unknown>).address as Record<string, unknown>)?.country_id as number | null
      : null

  isFetchingInitialData.value = false
}

function onFileInputChange(fileName: string, file: string): void {
  companyLogoName.value = fileName
  companyLogoFileBlob.value = file
}

function onFileInputRemove(): void {
  companyLogoName.value = null
  companyLogoFileBlob.value = null
}

async function submitCompanyData(): Promise<void> {
  v$.value.newCompanyForm.$touch()
  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true
  try {
    const res = await companyStore.addNewCompany(newCompanyForm as never)
    if (res.data) {
      companyStore.setSelectedCompany(res.data)

      if (companyLogoFileBlob.value) {
        const logoData = new FormData()
        logoData.append(
          'company_logo',
          JSON.stringify({
            name: companyLogoName.value,
            data: companyLogoFileBlob.value,
          }),
        )
        await companyStore.updateCompanyLogo(logoData)
      }

      globalStore.setIsAppLoaded(false)
      await globalStore.bootstrap()
      closeCompanyModal()
      router.push('/admin/dashboard')
    }
  } finally {
    isSaving.value = false
  }
}

function resetNewCompanyForm(): void {
  newCompanyForm.name = ''
  newCompanyForm.currency = ''
  newCompanyForm.address.country_id = null
  v$.value.$reset()
}

function closeCompanyModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetNewCompanyForm()
  }, 300)
}
</script>

<template>
  <BaseModal :show="modalActive" @close="closeCompanyModal" @open="getInitials">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeCompanyModal"
        />
      </div>
    </template>

    <form action="" @submit.prevent="submitCompanyData">
      <div class="p-4 mb-16 sm:p-6 space-y-4">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('settings.company_info.company_logo')"
          >
            <BaseContentPlaceholders v-if="isFetchingInitialData">
              <BaseContentPlaceholdersBox :rounded="true" class="w-full h-24" />
            </BaseContentPlaceholders>
            <div v-else class="flex flex-col items-center">
              <BaseFileUploader
                :preview-image="previewLogo"
                base64
                @remove="onFileInputRemove"
                @change="onFileInputChange"
              />
            </div>
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.company_info.company_name')"
            :error="
              v$.newCompanyForm.name.$error &&
              v$.newCompanyForm.name.$errors[0].$message
            "
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseInput
              v-model="newCompanyForm.name"
              :invalid="v$.newCompanyForm.name.$error"
              :content-loading="isFetchingInitialData"
              @input="v$.newCompanyForm.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('settings.company_info.country')"
            :error="
              v$.newCompanyForm.address.country_id.$error &&
              v$.newCompanyForm.address.country_id.$errors[0].$message
            "
            required
          >
            <BaseMultiselect
              v-model="newCompanyForm.address.country_id"
              :content-loading="isFetchingInitialData"
              label="name"
              :invalid="v$.newCompanyForm.address.country_id.$error"
              :options="globalStore.countries"
              value-prop="id"
              :can-deselect="true"
              :can-clear="false"
              searchable
              track-by="name"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('wizard.currency')"
            :error="
              v$.newCompanyForm.currency.$error &&
              v$.newCompanyForm.currency.$errors[0].$message
            "
            :content-loading="isFetchingInitialData"
            :help-text="$t('wizard.currency_set_alert')"
            required
          >
            <BaseMultiselect
              v-model="newCompanyForm.currency"
              :content-loading="isFetchingInitialData"
              :options="globalStore.currencies"
              label="name"
              value-prop="id"
              :searchable="true"
              track-by="name"
              :placeholder="$t('settings.currencies.select_currency')"
              :invalid="v$.newCompanyForm.currency.$error"
              class="w-full"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div class="z-0 flex justify-end p-4 bg-surface-secondary border-t border-line-default">
        <BaseButton
          class="mr-3 text-sm"
          variant="primary-outline"
          type="button"
          @click="closeCompanyModal"
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
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
