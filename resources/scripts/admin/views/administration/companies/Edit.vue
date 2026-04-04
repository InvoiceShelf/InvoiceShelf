<template>
  <BasePage v-if="!isLoading">
    <BasePageHeader :title="$t('administration.companies.edit_company')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('administration.companies.title')"
          to="/admin/administration/companies"
        />
        <BaseBreadcrumbItem
          :title="$t('administration.companies.edit_company')"
          to="#"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <form @submit.prevent="submitForm">
      <BaseCard class="mt-6">
        <BaseInputGrid class="mt-5">
          <BaseInputGroup
            :label="$t('administration.companies.company_name')"
            :error="v$.name.$error && v$.name.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="formData.name"
              :invalid="v$.name.$error"
              @blur="v$.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('administration.companies.owner')"
            :error="v$.owner_id.$error && v$.owner_id.$errors[0].$message"
            required
          >
            <BaseMultiselect
              v-model="formData.owner_id"
              label="name"
              :invalid="v$.owner_id.$error"
              :options="searchUsers"
              value-prop="id"
              :can-deselect="false"
              :can-clear="false"
              searchable
              :filter-results="false"
              :min-chars="0"
              :resolve-on-load="true"
              :delay="300"
              object
              track-by="name"
              @select="onOwnerSelect"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.company_info.tax_id')">
            <BaseInput v-model="formData.tax_id" type="text" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.company_info.vat_id')">
            <BaseInput v-model="formData.vat_id" type="text" />
          </BaseInputGroup>
        </BaseInputGrid>

        <BaseDivider class="my-6" />

        <h3 class="text-lg font-medium text-heading mb-4">
          {{ $t('administration.companies.address') }}
        </h3>

        <BaseInputGrid>
          <BaseInputGroup :label="$t('settings.company_info.phone')">
            <BaseInput v-model="formData.address.phone" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.company_info.country')">
            <BaseMultiselect
              v-model="formData.address.country_id"
              label="name"
              :options="globalStore.countries"
              value-prop="id"
              :can-deselect="true"
              :can-clear="false"
              searchable
              track-by="name"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.company_info.state')">
            <BaseInput v-model="formData.address.state" type="text" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.company_info.city')">
            <BaseInput v-model="formData.address.city" type="text" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.company_info.zip')">
            <BaseInput v-model="formData.address.zip" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.company_info.address')">
            <BaseTextarea
              v-model="formData.address.address_street_1"
              rows="2"
            />
          </BaseInputGroup>
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
          {{ $t('general.save') }}
        </BaseButton>
      </BaseCard>
    </form>
  </BasePage>

  <BaseGlobalLoader v-else />
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useAdministrationStore } from '@/scripts/admin/stores/administration'
import { required, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import http from '@/scripts/http'

const route = useRoute()
const router = useRouter()
const globalStore = useGlobalStore()
const administrationStore = useAdministrationStore()
const { t } = useI18n()

let isLoading = ref(true)
let isSaving = ref(false)

const formData = reactive({
  name: '',
  owner_id: null,
  vat_id: '',
  tax_id: '',
  address: {
    address_street_1: '',
    address_street_2: '',
    country_id: null,
    state: '',
    city: '',
    phone: '',
    zip: '',
  },
})

const rules = computed(() => {
  return {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    owner_id: {
      required: helpers.withMessage(t('validation.required'), required),
    },
  }
})

const v$ = useVuelidate(
  rules,
  computed(() => formData)
)

onMounted(async () => {
  await globalStore.fetchCountries()
  const response = await administrationStore.fetchCompany(route.params.id)
  const company = response.data.data

  formData.name = company.name
  formData.vat_id = company.vat_id || ''
  formData.tax_id = company.tax_id || ''

  if (company.owner) {
    formData.owner_id = {
      id: company.owner.id,
      name: company.owner.name,
      email: company.owner.email,
    }
  }

  if (company.address) {
    formData.address.address_street_1 = company.address.address_street_1 || ''
    formData.address.address_street_2 = company.address.address_street_2 || ''
    formData.address.country_id = company.address.country_id
    formData.address.state = company.address.state || ''
    formData.address.city = company.address.city || ''
    formData.address.phone = company.address.phone || ''
    formData.address.zip = company.address.zip || ''
  }

  isLoading.value = false
})

async function searchUsers(query) {
  const response = await http.get('/api/v1/search', {
    params: {
      search: query,
      type: 'User',
    },
  })

  return response.data.users.map((user) => ({
    id: user.id,
    name: `${user.name} (${user.email})`,
    email: user.email,
  }))
}

function onOwnerSelect(option) {
  formData.owner_id = option
}

async function submitForm() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  const data = {
    name: formData.name,
    owner_id: formData.owner_id?.id || formData.owner_id,
    vat_id: formData.vat_id,
    tax_id: formData.tax_id,
    address: formData.address,
  }

  await administrationStore.updateCompany(route.params.id, data)

  isSaving.value = false

  router.push({ name: 'admin.companies.index' })
}
</script>
