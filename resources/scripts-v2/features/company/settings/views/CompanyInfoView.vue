<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useGlobalStore } from '../../../../stores/global.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useModalStore } from '../../../../stores/modal.store'

interface CompanyFormData {
  name: string | null
  logo: string | null
  tax_id: string | null
  vat_id: string | null
  address: {
    address_street_1: string
    address_street_2: string
    website: string
    country_id: number | null
    state: string
    city: string
    phone: string
    zip: string
  }
}

interface FilePreview {
  image: string
}

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const modalStore = useModalStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)

const companyForm = reactive<CompanyFormData>({
  name: companyStore.selectedCompany?.name ?? null,
  logo: companyStore.selectedCompany?.logo ?? null,
  tax_id: companyStore.selectedCompany?.tax_id ?? null,
  vat_id: companyStore.selectedCompany?.vat_id ?? null,
  address: {
    address_street_1: (companyStore.selectedCompany?.address as Record<string, string>)?.address_street_1 ?? '',
    address_street_2: (companyStore.selectedCompany?.address as Record<string, string>)?.address_street_2 ?? '',
    website: (companyStore.selectedCompany?.address as Record<string, string>)?.website ?? '',
    country_id: (companyStore.selectedCompany?.address as Record<string, number | null>)?.country_id ?? null,
    state: (companyStore.selectedCompany?.address as Record<string, string>)?.state ?? '',
    city: (companyStore.selectedCompany?.address as Record<string, string>)?.city ?? '',
    phone: (companyStore.selectedCompany?.address as Record<string, string>)?.phone ?? '',
    zip: (companyStore.selectedCompany?.address as Record<string, string>)?.zip ?? '',
  },
})

const previewLogo = ref<FilePreview[]>([])
const logoFileBlob = ref<string | null>(null)
const logoFileName = ref<string | null>(null)
const isCompanyLogoRemoved = ref<boolean>(false)

if (companyForm.logo) {
  previewLogo.value.push({ image: companyForm.logo })
}

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(t('validation.name_min_length'), minLength(3)),
  },
  address: {
    country_id: {
      required: helpers.withMessage(t('validation.required'), required),
    },
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => companyForm)
)

globalStore.fetchCountries()

function onFileInputChange(
  _fileName: string,
  file: string,
  _fileCount: number,
  fileList: { name: string }
): void {
  logoFileName.value = fileList.name
  logoFileBlob.value = file
}

function onFileInputRemove(): void {
  logoFileBlob.value = null
  isCompanyLogoRemoved.value = true
}

async function updateCompanyData(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  const res = await companyStore.updateCompany({
    name: companyForm.name ?? '',
    tax_id: companyForm.tax_id,
    vat_id: companyForm.vat_id,
    address: companyForm.address,
  })

  if (res.data) {
    if (logoFileBlob.value || isCompanyLogoRemoved.value) {
      const logoData = new FormData()

      if (logoFileBlob.value) {
        logoData.append(
          'company_logo',
          JSON.stringify({
            name: logoFileName.value,
            data: logoFileBlob.value,
          })
        )
      }
      logoData.append('is_company_logo_removed', String(isCompanyLogoRemoved.value))

      await companyStore.updateCompanyLogo(logoData)
      logoFileBlob.value = null
      isCompanyLogoRemoved.value = false
    }
  }

  isSaving.value = false
}
</script>

<template>
  <form @submit.prevent="updateCompanyData">
    <BaseSettingCard
      :title="$t('settings.company_info.company_info')"
      :description="$t('settings.company_info.section_description')"
    >
      <BaseInputGrid class="mt-5">
        <BaseInputGroup :label="$t('settings.company_info.company_logo')">
          <BaseFileUploader
            v-model="previewLogo"
            base64
            @change="onFileInputChange"
            @remove="onFileInputRemove"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseInputGrid class="mt-5">
        <BaseInputGroup
          :label="$t('settings.company_info.company_name')"
          :error="v$.name.$error && v$.name.$errors[0]?.$message"
          required
        >
          <BaseInput
            v-model="companyForm.name"
            :invalid="v$.name.$error"
            @blur="v$.name.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.phone')">
          <BaseInput v-model="companyForm.address.phone" />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.company_info.country')"
          :error="
            v$.address.country_id.$error &&
            v$.address.country_id.$errors[0]?.$message
          "
          required
        >
          <BaseMultiselect
            v-model="companyForm.address.country_id"
            label="name"
            :invalid="v$.address.country_id.$error"
            :options="globalStore.countries"
            value-prop="id"
            :can-deselect="true"
            :can-clear="false"
            searchable
            track-by="name"
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.state')">
          <BaseInput v-model="companyForm.address.state" name="state" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.city')">
          <BaseInput v-model="companyForm.address.city" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.company_info.zip')">
          <BaseInput v-model="companyForm.address.zip" />
        </BaseInputGroup>

        <div>
          <BaseInputGroup :label="$t('settings.company_info.address')">
            <BaseTextarea v-model="companyForm.address.address_street_1" rows="2" />
          </BaseInputGroup>
          <BaseTextarea
            v-model="companyForm.address.address_street_2"
            rows="2"
            class="mt-2"
          />
        </div>

        <div class="space-y-6">
          <BaseInputGroup :label="$t('settings.company_info.tax_id')">
            <BaseInput v-model="companyForm.tax_id" type="text" />
          </BaseInputGroup>
          <BaseInputGroup :label="$t('settings.company_info.vat_id')">
            <BaseInput v-model="companyForm.vat_id" type="text" />
          </BaseInputGroup>
        </div>
      </BaseInputGrid>

      <BaseButton
        :loading="isSaving"
        :disabled="isSaving"
        type="submit"
        class="mt-6"
      >
        <template #left="slotProps">
          <BaseIcon
            v-if="!isSaving"
            :class="slotProps.class"
            name="ArrowDownOnSquareIcon"
          />
        </template>
        {{ $t('settings.company_info.save') }}
      </BaseButton>

    </BaseSettingCard>
  </form>
</template>
