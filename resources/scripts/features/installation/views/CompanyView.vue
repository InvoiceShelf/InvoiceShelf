<template>
  <BaseWizardStep
    :title="$t('wizard.company_info')"
    :description="$t('wizard.company_info_desc')"
  >
    <form @submit.prevent="next">
      <div class="grid grid-cols-1 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup :label="$t('settings.company_info.company_logo')">
          <BaseFileUploader
            base64
            :preview-image="previewLogo"
            @change="onFileInputChange"
            @remove="onFileInputRemove"
          />
        </BaseInputGroup>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup
          :label="$t('wizard.company_name')"
          :error="v$.name.$error ? String(v$.name.$errors[0]?.$message) : undefined"
          required
        >
          <BaseInput
            v-model.trim="companyForm.name"
            :invalid="v$.name.$error"
            type="text"
            name="name"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('wizard.country')"
          :error="v$.country_id.$error ? String(v$.country_id.$errors[0]?.$message) : undefined"
          :content-loading="isFetchingInitialData"
          required
        >
          <BaseMultiselect
            v-model="companyForm.address.country_id"
            label="name"
            :invalid="v$.country_id.$error"
            :options="countries"
            value-prop="id"
            :can-deselect="false"
            :can-clear="false"
            :content-loading="isFetchingInitialData"
            :placeholder="$t('general.select_country')"
            searchable
            track-by="name"
          />
        </BaseInputGroup>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup :label="$t('wizard.state')">
          <BaseInput v-model="companyForm.address.state" name="state" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('wizard.city')">
          <BaseInput v-model="companyForm.address.city" name="city" type="text" />
        </BaseInputGroup>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2">
        <div>
          <BaseInputGroup
            :label="$t('wizard.address')"
            :error="v$.address_street_1.$error ? String(v$.address_street_1.$errors[0]?.$message) : undefined"
          >
            <BaseTextarea
              v-model.trim="companyForm.address.address_street_1"
              :invalid="v$.address_street_1.$error"
              :placeholder="$t('general.street_1')"
              name="billing_street1"
              rows="2"
              @input="v$.address_street_1.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup class="mt-1 lg:mt-2 md:mt-2">
            <BaseTextarea
              v-model="companyForm.address.address_street_2"
              :placeholder="$t('general.street_2')"
              name="billing_street2"
              rows="2"
            />
          </BaseInputGroup>
        </div>

        <div>
          <BaseInputGroup :label="$t('wizard.zip_code')">
            <BaseInput v-model.trim="companyForm.address.zip" type="text" name="zip" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('wizard.phone')" class="mt-4">
            <BaseInput v-model.trim="companyForm.address.phone" type="text" name="phone" />
          </BaseInputGroup>
        </div>

        <BaseInputGroup :label="$t('settings.company_info.tax_id')">
          <BaseInput v-model.trim="companyForm.tax_id" type="text" name="tax_id" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.vat_id')">
          <BaseInput v-model.trim="companyForm.vat_id" type="text" name="vat_id" />
        </BaseInputGroup>
      </div>

      <BaseButton :loading="isSaving" :disabled="isSaving" class="mt-4">
        <template #left="slotProps">
          <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('wizard.save_cont') }}
      </BaseButton>
    </form>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { required, maxLength, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { installClient } from '../../../api/install-client'
import { API } from '../../../api/endpoints'
import type { Country } from '../../../types/domain/customer'
import { useInstallationFeedback } from '../use-installation-feedback'

interface CompanyAddress {
  address_street_1: string
  address_street_2: string
  website: string
  country_id: number | null
  state: string
  city: string
  phone: string
  zip: string
}

interface CompanyFormData {
  name: string | null
  tax_id: string | null
  vat_id: string | null
  address: CompanyAddress
}

const router = useRouter()
const { t } = useI18n()
const { showRequestError } = useInstallationFeedback()

const isFetchingInitialData = ref<boolean>(false)
const isSaving = ref<boolean>(false)
const previewLogo = ref<string | null>(null)
const logoFileBlob = ref<string | null>(null)
const logoFileName = ref<string | null>(null)
const countries = ref<Country[]>([])

const companyForm = reactive<CompanyFormData>({
  name: null,
  tax_id: null,
  vat_id: null,
  address: {
    address_street_1: '',
    address_street_2: '',
    website: '',
    country_id: null,
    state: '',
    city: '',
    phone: '',
    zip: '',
  },
})

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  country_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  address_street_1: {
    maxLength: helpers.withMessage(
      t('validation.address_maxlength', { count: 255 }),
      maxLength(255),
    ),
  },
}))

const validationState = computed(() => ({
  name: companyForm.name,
  country_id: companyForm.address.country_id,
  address_street_1: companyForm.address.address_street_1,
}))

const v$ = useVuelidate(rules, validationState)

onMounted(async () => {
  isFetchingInitialData.value = true
  try {
    const { data } = await installClient.get(API.COUNTRIES)
    countries.value = data.data ?? data
    // Default to US
    const us = countries.value.find((c) => c.code === 'US')
    if (us) companyForm.address.country_id = us.id
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isFetchingInitialData.value = false
  }
})

function onFileInputChange(
  _fileName: string,
  file: string,
  _fileCount: number,
  fileList: { name: string },
): void {
  logoFileName.value = fileList.name
  logoFileBlob.value = file
}

function onFileInputRemove(): void {
  logoFileBlob.value = null
}

async function next(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  try {
    await installClient.put(API.COMPANY, companyForm)

    if (logoFileBlob.value) {
      const logoData = new FormData()
      logoData.append(
        'company_logo',
        JSON.stringify({
          name: logoFileName.value,
          data: logoFileBlob.value,
        }),
      )
      await installClient.post(API.COMPANY_UPLOAD_LOGO, logoData)
    }

    await router.push({ name: 'installation.preferences' })
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isSaving.value = false
  }
}
</script>
