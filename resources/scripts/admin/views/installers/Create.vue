<template>
  <BasePage>
    <form @submit.prevent="submitInstallerData">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />

          <BaseBreadcrumbItem
            :title="$t('installers.installer', 2)"
            to="/admin/installers"
          />

          <BaseBreadcrumb-item :title="pageTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <div class="flex items-center justify-end">
            <BaseButton type="submit" :loading="isSaving" :disabled="isSaving">
              <template #left="slotProps">
                <BaseIcon name="SaveIcon" :class="slotProps.class" />
              </template>
              {{
                isEdit
                  ? $t('installers.update_installer')
                  : $t('installers.save_installer')
              }}
            </BaseButton>
          </div>
        </template>
      </BasePageHeader>

      <BaseCard class="mt-5">
        <!-- Basic Info -->
        <div class="grid grid-cols-5 gap-4 mb-8">
          <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
            {{ $t('installers.basic_info') }}
          </h6>

          <BaseInputGrid class="col-span-5 lg:col-span-4">
            <BaseInputGroup
              :label="$t('installers.display_name')"
              required
              :error="
                v$.currentInstaller.name.$error &&
                v$.currentInstaller.name.$errors[0].$message
              "
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model="installerStore.currentInstaller.name"
                :content-loading="isFetchingInitialData"
                type="text"
                name="name"
                class=""
                :invalid="v$.currentInstaller.name.$error"
                @input="v$.currentInstaller.name.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('installers.primary_contact_name')"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model.trim="installerStore.currentInstaller.contact_name"
                :content-loading="isFetchingInitialData"
                type="text"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :error="
                v$.currentInstaller.email.$error &&
                v$.currentInstaller.email.$errors[0].$message
              "
              :content-loading="isFetchingInitialData"
              :label="$t('installers.email')"
            >
              <BaseInput
                v-model.trim="installerStore.currentInstaller.email"
                :content-loading="isFetchingInitialData"
                type="text"
                name="email"
                :invalid="v$.currentInstaller.email.$error"
                @input="v$.currentInstaller.email.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('installers.phone')"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model.trim="installerStore.currentInstaller.phone"
                :content-loading="isFetchingInitialData"
                type="text"
                name="phone"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('installers.primary_currency')"
              :content-loading="isFetchingInitialData"
              :error="
                v$.currentInstaller.currency_id.$error &&
                v$.currentInstaller.currency_id.$errors[0].$message
              "
              required
            >
              <BaseMultiselect
                v-model="installerStore.currentInstaller.currency_id"
                value-prop="id"
                label="name"
                track-by="name"
                :content-loading="isFetchingInitialData"
                :options="globalStore.currencies"
                searchable
                :can-deselect="false"
                :placeholder="$t('installers.select_currency')"
                :invalid="v$.currentInstaller.currency_id.$error"
                class="w-full"
              >
              </BaseMultiselect>
            </BaseInputGroup>

            <BaseInputGroup
              :error="
                v$.currentInstaller.website.$error &&
                v$.currentInstaller.website.$errors[0].$message
              "
              :label="$t('installers.website')"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model="installerStore.currentInstaller.website"
                :content-loading="isFetchingInitialData"
                type="url"
                @input="v$.currentInstaller.website.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('installers.prefix')"
              :error="
                v$.currentInstaller.prefix.$error &&
                v$.currentInstaller.prefix.$errors[0].$message
              "
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model="installerStore.currentInstaller.prefix"
                :content-loading="isFetchingInitialData"
                type="text"
                name="name"
                class=""
                :invalid="v$.currentInstaller.prefix.$error"
                @input="v$.currentInstaller.prefix.$touch()"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>

        <BaseDivider class="mb-5 md:mb-8" />

        <!-- Portal Access-->

        <!-- <div class="grid grid-cols-5 gap-4 mb-8">
          <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
            {{ $t('installers.portal_access') }}
          </h6>

          <BaseInputGrid class="col-span-5 lg:col-span-4">
            <div class="md:col-span-2">
              <p class="text-sm text-gray-500">
                {{ $t('installers.portal_access_text') }}
              </p>

              <BaseSwitch
                v-model="installerStore.currentInstaller.enable_portal"
                class="mt-1 flex"
              />
            </div>

            <BaseInputGroup
              v-if="installerStore.currentInstaller.enable_portal"
              :content-loading="isFetchingInitialData"
              :label="$t('installers.portal_access_url')"
              class="md:col-span-2"
              :help-text="$t('installers.portal_access_url_help')"
            >
              <CopyInputField :token="getInstallerPortalUrl" />
            </BaseInputGroup>

            <BaseInputGroup
              v-if="installerStore.currentInstaller.enable_portal"
              :content-loading="isFetchingInitialData"
              :error="
                v$.currentInstaller.password.$error &&
                v$.currentInstaller.password.$errors[0].$message
              "
              :label="$t('installers.password')"
            >
              <BaseInput
                v-model.trim="installerStore.currentInstaller.password"
                :content-loading="isFetchingInitialData"
                :type="isShowPassword ? 'text' : 'password'"
                name="password"
                :invalid="v$.currentInstaller.password.$error"
                @input="v$.currentInstaller.password.$touch()"
              >
                <template #right>
                  <BaseIcon
                    v-if="isShowPassword"
                    name="EyeOffIcon"
                    class="w-5 h-5 mr-1 text-gray-500 cursor-pointer"
                    @click="isShowPassword = !isShowPassword"
                  />
                  <BaseIcon
                    v-else
                    name="EyeIcon"
                    class="w-5 h-5 mr-1 text-gray-500 cursor-pointer"
                    @click="isShowPassword = !isShowPassword"
                  /> </template
              ></BaseInput>
            </BaseInputGroup>

            <BaseInputGroup
              v-if="installerStore.currentInstaller.enable_portal"
              :error="
                v$.currentInstaller.confirm_password.$error &&
                v$.currentInstaller.confirm_password.$errors[0].$message
              "
              :content-loading="isFetchingInitialData"
              label="Confirm Password"
            >
              <BaseInput
                v-model.trim="installerStore.currentInstaller.confirm_password"
                :content-loading="isFetchingInitialData"
                :type="isShowConfirmPassword ? 'text' : 'password'"
                name="confirm_password"
                :invalid="v$.currentInstaller.confirm_password.$error"
                @input="v$.currentInstaller.confirm_password.$touch()"
              >
                <template #right>
                  <BaseIcon
                    v-if="isShowConfirmPassword"
                    name="EyeOffIcon"
                    class="w-5 h-5 mr-1 text-gray-500 cursor-pointer"
                    @click="isShowConfirmPassword = !isShowConfirmPassword"
                  />
                  <BaseIcon
                    v-else
                    name="EyeIcon"
                    class="w-5 h-5 mr-1 text-gray-500 cursor-pointer"
                    @click="isShowConfirmPassword = !isShowConfirmPassword"
                  /> </template
              ></BaseInput>
            </BaseInputGroup>
          </BaseInputGrid>
        </div> -->

        <BaseDivider class="mb-5 md:mb-8" />

        <!-- Billing Address   -->
        <div class="grid grid-cols-5 gap-4 mb-8">
          <h6 class="col-span-5 text-lg font-semibold text-left lg:col-span-1">
            {{ $t('installers.billing_address') }}
          </h6>

          <BaseInputGrid
            v-if="installerStore.currentInstaller.billing"
            class="col-span-5 lg:col-span-4"
          >
            <BaseInputGroup
              :label="$t('installers.name')"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model.trim="installerStore.currentInstaller.billing.name"
                :content-loading="isFetchingInitialData"
                type="text"
                class="w-full"
                name="address_name"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('installers.country')"
              :content-loading="isFetchingInitialData"
            >
              <BaseMultiselect
                v-model="installerStore.currentInstaller.billing.country_id"
                value-prop="id"
                label="name"
                track-by="name"
                resolve-on-load
                searchable
                :content-loading="isFetchingInitialData"
                :options="globalStore.countries"
                :placeholder="$t('general.select_country')"
                class="w-full"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('installers.state')"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model="installerStore.currentInstaller.billing.state"
                :content-loading="isFetchingInitialData"
                name="billing.state"
                type="text"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :content-loading="isFetchingInitialData"
              :label="$t('installers.city')"
            >
              <BaseInput
                v-model="installerStore.currentInstaller.billing.city"
                :content-loading="isFetchingInitialData"
                name="billing.city"
                type="text"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('installers.address')"
              :error="
                (v$.currentInstaller.billing.address_street_1.$error &&
                  v$.currentInstaller.billing.address_street_1.$errors[0]
                    .$message) ||
                (v$.currentInstaller.billing.address_street_2.$error &&
                  v$.currentInstaller.billing.address_street_2.$errors[0]
                    .$message)
              "
              :content-loading="isFetchingInitialData"
            >
              <BaseTextarea
                v-model.trim="
                  installerStore.currentInstaller.billing.address_street_1
                "
                :content-loading="isFetchingInitialData"
                :placeholder="$t('general.street_1')"
                type="text"
                name="billing_street1"
                :container-class="`mt-3`"
                @input="v$.currentInstaller.billing.address_street_1.$touch()"
              />

              <BaseTextarea
                v-model.trim="
                  installerStore.currentInstaller.billing.address_street_2
                "
                :content-loading="isFetchingInitialData"
                :placeholder="$t('general.street_2')"
                type="text"
                class="mt-3"
                name="billing_street2"
                :container-class="`mt-3`"
                @input="v$.currentInstaller.billing.address_street_2.$touch()"
              />
            </BaseInputGroup>

            <div class="space-y-6">
              <BaseInputGroup
                :content-loading="isFetchingInitialData"
                :label="$t('installers.phone')"
                class="text-left"
              >
                <BaseInput
                  v-model.trim="installerStore.currentInstaller.billing.phone"
                  :content-loading="isFetchingInitialData"
                  type="text"
                  name="phone"
                />
              </BaseInputGroup>

              <BaseInputGroup
                :label="$t('installers.zip_code')"
                :content-loading="isFetchingInitialData"
                class="mt-2 text-left"
              >
                <BaseInput
                  v-model.trim="installerStore.currentInstaller.billing.zip"
                  :content-loading="isFetchingInitialData"
                  type="text"
                  name="zip"
                />
              </BaseInputGroup>
            </div>
          </BaseInputGrid>
        </div>

        <BaseDivider class="mb-5 md:mb-8" />

        <BaseDivider
          v-if="customFieldStore.customFields.length > 0"
          class="mb-5 md:mb-8"
        />

        <!-- Installer Custom Fields -->
        <div class="grid grid-cols-5 gap-2 mb-8">
          <h6
            v-if="customFieldStore.customFields.length > 0"
            class="col-span-5 text-lg font-semibold text-left lg:col-span-1"
          >
            {{ $t('settings.custom_fields.title') }}
          </h6>

          <div class="col-span-5 lg:col-span-4">
            <InstallerCustomFields
              type="Installer"
              :store="installerStore"
              store-prop="currentInstaller"
              :is-edit="isEdit"
              :is-loading="isLoadingContent"
              :custom-field-scope="customFieldValidationScope"
            />
          </div>
        </div>
      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  required,
  minLength,
  url,
  maxLength,
  helpers,
  email,
  sameAs,
  requiredIf,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useInstallerStore } from '@/scripts/admin/stores/installer'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'
import InstallerCustomFields from '@/scripts/admin/components/custom-fields/CreateCustomFields.vue'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import CopyInputField from '@/scripts/admin/components/CopyInputField.vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const installerStore = useInstallerStore()
const customFieldStore = useCustomFieldStore()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()

const customFieldValidationScope = 'customFields'

const { t } = useI18n()

const router = useRouter()
const route = useRoute()

let isFetchingInitialData = ref(false)
let isShowPassword = ref(false)
let isShowConfirmPassword = ref(false)

let active = ref(false)
const isSaving = ref(false)

const isEdit = computed(() => route.name === 'installers.edit')

let isLoadingContent = computed(() => installerStore.isFetchingInitialSettings)

const pageTitle = computed(() =>
  isEdit.value ? t('installers.edit_installer') : t('installers.new_installer'),
)

const rules = computed(() => {
  return {
    currentInstaller: {
      name: {
        required: helpers.withMessage(t('validation.required'), required),
        minLength: helpers.withMessage(
          t('validation.name_min_length', { count: 3 }),
          minLength(3),
        ),
      },
      prefix: {
        minLength: helpers.withMessage(
          t('validation.name_min_length', { count: 3 }),
          minLength(3),
        ),
      },
      currency_id: {
        required: helpers.withMessage(t('validation.required'), required),
      },

      email: {
        required: helpers.withMessage(
          t('validation.required'),
          requiredIf(installerStore.currentInstaller.enable_portal == true),
        ),
        email: helpers.withMessage(t('validation.email_incorrect'), email),
      },
      password: {
        required: helpers.withMessage(
          t('validation.required'),
          requiredIf(
            installerStore.currentInstaller.enable_portal == true &&
              !installerStore.currentInstaller.password_added,
          ),
        ),
        minLength: helpers.withMessage(
          t('validation.password_min_length', { count: 8 }),
          minLength(8),
        ),
      },
      confirm_password: {
        sameAsPassword: helpers.withMessage(
          t('validation.password_incorrect'),
          sameAs(installerStore.currentInstaller.password),
        ),
      },

      website: {
        url: helpers.withMessage(t('validation.invalid_url'), url),
      },
      billing: {
        address_street_1: {
          maxLength: helpers.withMessage(
            t('validation.address_maxlength', { count: 255 }),
            maxLength(255),
          ),
        },

        address_street_2: {
          maxLength: helpers.withMessage(
            t('validation.address_maxlength', { count: 255 }),
            maxLength(255),
          ),
        },
      },

      shipping: {
        address_street_1: {
          maxLength: helpers.withMessage(
            t('validation.address_maxlength', { count: 255 }),
            maxLength(255),
          ),
        },

        address_street_2: {
          maxLength: helpers.withMessage(
            t('validation.address_maxlength', { count: 255 }),
            maxLength(255),
          ),
        },
      },
    },
  }
})

const getInstallerPortalUrl = computed(() => {
  return `${window.location.origin}/${companyStore.selectedCompany.slug}/installer/login`
})

const v$ = useVuelidate(rules, installerStore, {
  $scope: customFieldValidationScope,
})

installerStore.resetCurrentInstaller()

installerStore.fetchInstallerInitialSettings(isEdit.value)

async function submitInstallerData() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return true
  }

  isSaving.value = true

  let data = {
    ...installerStore.currentInstaller,
  }

  let response = null

  try {
    const action = isEdit.value
      ? installerStore.updateInstaller
      : installerStore.addInstaller
    response = await action(data)
  } catch (err) {
    isSaving.value = false
    return
  }

  router.push(`/admin/installers/${response.data.data.id}/view`)
}
</script>
