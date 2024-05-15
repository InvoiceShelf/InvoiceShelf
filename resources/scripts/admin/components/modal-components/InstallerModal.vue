<template>
  <BaseModal
    :show="modalActive"
    @close="closeInstallerModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}

        <BaseIcon
          name="XIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeInstallerModal"
        />
      </div>
    </template>
    <form action="" @submit.prevent="submitInstallerData">
      <div class="px-6 pb-3">
        <BaseTabGroup>
          <BaseTab :title="$t('installers.basic_info')" class="!mt-2">
            <BaseInputGrid layout="one-column">
              <BaseInputGroup
                :label="$t('installers.display_name')"
                required
                :error="v$.name.$error && v$.name.$errors[0].$message"
              >
                <BaseInput
                  v-model.trim="installerStore.currentInstaller.name"
                  type="text"
                  name="name"
                  class="mt-1 md:mt-0"
                  :invalid="v$.name.$error"
                  @input="v$.name.$touch()"
                />
              </BaseInputGroup>

              <BaseInputGroup
                :label="$t('settings.currencies.currency')"
                required
                :error="
                  v$.currency_id.$error && v$.currency_id.$errors[0].$message
                "
              >
                <BaseMultiselect
                  v-model="installerStore.currentInstaller.currency_id"
                  :options="globalStore.currencies"
                  value-prop="id"
                  searchable
                  :placeholder="$t('installers.select_currency')"
                  :max-height="200"
                  class="mt-1 md:mt-0"
                  track-by="name"
                  :invalid="v$.currency_id.$error"
                  label="name"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.primary_contact_name')">
                <BaseInput
                  v-model="installerStore.currentInstaller.contact_name"
                  type="text"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>
              <BaseInputGroup
                :label="$t('login.email')"
                :error="v$.email.$error && v$.email.$errors[0].$message"
              >
                <BaseInput
                  v-model.trim="installerStore.currentInstaller.email"
                  type="text"
                  name="email"
                  class="mt-1 md:mt-0"
                  :invalid="v$.email.$error"
                  @input="v$.email.$touch()"
                />
              </BaseInputGroup>

              <BaseInputGroup
                :label="$t('installers.prefix')"
                :error="v$.prefix.$error && v$.prefix.$errors[0].$message"
                :content-loading="isFetchingInitialData"
              >
                <BaseInput
                  v-model="installerStore.currentInstaller.prefix"
                  :content-loading="isFetchingInitialData"
                  type="text"
                  name="name"
                  class=""
                  :invalid="v$.prefix.$error"
                  @input="v$.prefix.$touch()"
                />
              </BaseInputGroup>

              <BaseInputGrid>
                <BaseInputGroup :label="$t('installers.phone')">
                  <BaseInput
                    v-model.trim="installerStore.currentInstaller.phone"
                    type="text"
                    name="phone"
                    class="mt-1 md:mt-0"
                  />
                </BaseInputGroup>

                <BaseInputGroup
                  :label="$t('installers.website')"
                  :error="v$.website.$error && v$.website.$errors[0].$message"
                >
                  <BaseInput
                    v-model="installerStore.currentInstaller.website"
                    type="url"
                    class="mt-1 md:mt-0"
                    :invalid="v$.website.$error"
                    @input="v$.website.$touch()"
                  />
                </BaseInputGroup>
              </BaseInputGrid>
            </BaseInputGrid>
          </BaseTab>

          <BaseTab :title="$t('installers.portal_access')">
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
                :error="v$.password.$error && v$.password.$errors[0].$message"
                :label="$t('installers.password')"
              >
                <BaseInput
                  v-model.trim="installerStore.currentInstaller.password"
                  :content-loading="isFetchingInitialData"
                  :type="isShowPassword ? 'text' : 'password'"
                  name="password"
                  :invalid="v$.password.$error"
                  @input="v$.password.$touch()"
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
                  v$.confirm_password.$error &&
                  v$.confirm_password.$errors[0].$message
                "
                :content-loading="isFetchingInitialData"
                label="Confirm Password"
              >
                <BaseInput
                  v-model.trim="installerStore.currentInstaller.confirm_password"
                  :content-loading="isFetchingInitialData"
                  :type="isShowConfirmPassword ? 'text' : 'password'"
                  name="confirm_password"
                  :invalid="v$.confirm_password.$error"
                  @input="v$.confirm_password.$touch()"
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
          </BaseTab>

          <BaseTab :title="$t('installers.billing_address')" class="!mt-2">
            <BaseInputGrid layout="one-column">
              <BaseInputGroup :label="$t('installers.name')">
                <BaseInput
                  v-model="installerStore.currentInstaller.billing.name"
                  type="text"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.country')">
                <BaseMultiselect
                  v-model="installerStore.currentInstaller.billing.country_id"
                  :options="globalStore.countries"
                  searchable
                  :show-labels="false"
                  :placeholder="$t('general.select_country')"
                  :allow-empty="false"
                  track-by="name"
                  class="mt-1 md:mt-0"
                  label="name"
                  value-prop="id"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.state')">
                <BaseInput
                  v-model="installerStore.currentInstaller.billing.state"
                  type="text"
                  name="billingState"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.city')">
                <BaseInput
                  v-model="installerStore.currentInstaller.billing.city"
                  type="text"
                  name="billingCity"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>

              <BaseInputGroup
                :label="$t('installers.address')"
                :error="
                  v$.billing.address_street_1.$error &&
                  v$.billing.address_street_1.$errors[0].$message
                "
              >
                <BaseTextarea
                  v-model="
                    installerStore.currentInstaller.billing.address_street_1
                  "
                  :placeholder="$t('general.street_1')"
                  rows="2"
                  cols="50"
                  class="mt-1 md:mt-0"
                  :invalid="v$.billing.address_street_1.$error"
                  @input="v$.billing.address_street_1.$touch()"
                />
              </BaseInputGroup>
            </BaseInputGrid>

            <BaseInputGrid layout="one-column">
              <BaseInputGroup
                :error="
                  v$.billing.address_street_2.$error &&
                  v$.billing.address_street_2.$errors[0].$message
                "
              >
                <BaseTextarea
                  v-model="
                    installerStore.currentInstaller.billing.address_street_2
                  "
                  :placeholder="$t('general.street_2')"
                  rows="2"
                  cols="50"
                  :invalid="v$.billing.address_street_2.$error"
                  @input="v$.billing.address_street_2.$touch()"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.phone')">
                <BaseInput
                  v-model.trim="installerStore.currentInstaller.billing.phone"
                  type="text"
                  name="phone"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.zip_code')">
                <BaseInput
                  v-model="installerStore.currentInstaller.billing.zip"
                  type="text"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>
            </BaseInputGrid>
          </BaseTab>

          <BaseTab :title="$t('installers.shipping_address')" class="!mt-2">
            <div class="grid md:grid-cols-12">
              <div class="flex justify-end col-span-12">
                <BaseButton
                  variant="primary"
                  type="button"
                  size="xs"
                  @click="copyAddress(true)"
                >
                  {{ $t('installers.copy_billing_address') }}
                </BaseButton>
              </div>
            </div>

            <BaseInputGrid layout="one-column">
              <BaseInputGroup :label="$t('installers.name')">
                <BaseInput
                  v-model="installerStore.currentInstaller.shipping.name"
                  type="text"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.country')">
                <BaseMultiselect
                  v-model="installerStore.currentInstaller.shipping.country_id"
                  :options="globalStore.countries"
                  :searchable="true"
                  :show-labels="false"
                  :allow-empty="false"
                  :placeholder="$t('general.select_country')"
                  track-by="name"
                  class="mt-1 md:mt-0"
                  label="name"
                  value-prop="id"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.state')">
                <BaseInput
                  v-model="installerStore.currentInstaller.shipping.state"
                  type="text"
                  name="shippingState"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.city')">
                <BaseInput
                  v-model="installerStore.currentInstaller.shipping.city"
                  type="text"
                  name="shippingCity"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>

              <BaseInputGroup
                :label="$t('installers.address')"
                :error="
                  v$.shipping.address_street_1.$error &&
                  v$.shipping.address_street_1.$errors[0].$message
                "
              >
                <BaseTextarea
                  v-model="
                    installerStore.currentInstaller.shipping.address_street_1
                  "
                  :placeholder="$t('general.street_1')"
                  rows="2"
                  cols="50"
                  class="mt-1 md:mt-0"
                  :invalid="v$.shipping.address_street_1.$error"
                  @input="v$.shipping.address_street_1.$touch()"
                />
              </BaseInputGroup>
            </BaseInputGrid>

            <BaseInputGrid layout="one-column">
              <BaseInputGroup
                :error="
                  v$.shipping.address_street_2.$error &&
                  v$.shipping.address_street_2.$errors[0].$message
                "
              >
                <BaseTextarea
                  v-model="
                    installerStore.currentInstaller.shipping.address_street_2
                  "
                  :placeholder="$t('general.street_2')"
                  rows="2"
                  cols="50"
                  :invalid="v$.shipping.address_street_1.$error"
                  @input="v$.shipping.address_street_2.$touch()"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.phone')">
                <BaseInput
                  v-model.trim="installerStore.currentInstaller.shipping.phone"
                  type="text"
                  name="phone"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>

              <BaseInputGroup :label="$t('installers.zip_code')">
                <BaseInput
                  v-model="installerStore.currentInstaller.shipping.zip"
                  type="text"
                  class="mt-1 md:mt-0"
                />
              </BaseInputGroup>
            </BaseInputGrid>
          </BaseTab>
        </BaseTabGroup>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid"
      >
        <BaseButton
          class="mr-3 text-sm"
          type="button"
          variant="primary-outline"
          @click="closeInstallerModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton :loading="isLoading" variant="primary" type="submit">
          <template #left="slotProps">
            <BaseIcon
              v-if="!isLoading"
              name="SaveIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

import {
  required,
  minLength,
  maxLength,
  email,
  alpha,
  url,
  helpers,
  requiredIf,
  sameAs,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

import { useModalStore } from '@/scripts/stores/modal'
import { useEstimateStore } from '@/scripts/admin/stores/estimate'
import { useInstallerStore } from '@/scripts/admin/stores/installer'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import CopyInputField from '@/scripts/admin/components/CopyInputField.vue'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useRecurringInvoiceStore } from '@/scripts/admin/stores/recurring-invoice'

const recurringInvoiceStore = useRecurringInvoiceStore()
const modalStore = useModalStore()
const estimateStore = useEstimateStore()
const installerStore = useInstallerStore()
const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const invoiceStore = useInvoiceStore()
const notificationStore = useNotificationStore()

let isFetchingInitialData = ref(false)

const { t } = useI18n()
const route = useRoute()
const isEdit = ref(false)
const isLoading = ref(false)
let isShowPassword = ref(false)
let isShowConfirmPassword = ref(false)

const modalActive = computed(
  () => modalStore.active && modalStore.componentName === 'InstallerModal'
)

const rules = computed(() => {
  return {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
      minLength: helpers.withMessage(
        t('validation.name_min_length', { count: 3 }),
        minLength(3)
      ),
    },
    currency_id: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    password: {
      required: helpers.withMessage(
        t('validation.required'),
        requiredIf(
          installerStore.currentInstaller.enable_portal == true &&
            !installerStore.currentInstaller.password_added
        )
      ),
      minLength: helpers.withMessage(
        t('validation.password_min_length', { count: 8 }),
        minLength(8)
      ),
    },
    confirm_password: {
      sameAsPassword: helpers.withMessage(
        t('validation.password_incorrect'),
        sameAs(installerStore.currentInstaller.password)
      ),
    },
    email: {
      required: helpers.withMessage(
        t('validation.required'),
        requiredIf(installerStore.currentInstaller.enable_portal == true)
      ),
      email: helpers.withMessage(t('validation.email_incorrect'), email),
    },
    prefix: {
      minLength: helpers.withMessage(
        t('validation.name_min_length', { count: 3 }),
        minLength(3)
      ),
    },
    website: {
      url: helpers.withMessage(t('validation.invalid_url'), url),
    },

    billing: {
      address_street_1: {
        maxLength: helpers.withMessage(
          t('validation.address_maxlength', { count: 255 }),
          maxLength(255)
        ),
      },
      address_street_2: {
        maxLength: helpers.withMessage(
          t('validation.address_maxlength', { count: 255 }),
          maxLength(255)
        ),
      },
    },

    shipping: {
      address_street_1: {
        maxLength: helpers.withMessage(
          t('validation.address_maxlength', { count: 255 }),
          maxLength(255)
        ),
      },
      address_street_2: {
        maxLength: helpers.withMessage(
          t('validation.address_maxlength', { count: 255 }),
          maxLength(255)
        ),
      },
    },
  }
})

const v$ = useVuelidate(
  rules,
  computed(() => installerStore.currentInstaller)
)

const getInstallerPortalUrl = computed(() => {
  return `${window.location.origin}/${companyStore.selectedCompany.slug}/installer/login`
})

function copyAddress() {
  installerStore.copyAddress()
}

async function setInitialData() {
  if (!installerStore.isEdit) {
    installerStore.currentInstaller.currency_id =
      companyStore.selectedCompanyCurrency.id
  }
}

async function submitInstallerData() {
  v$.value.$touch()

  if (v$.value.$invalid && installerStore.currentInstaller.email === '') {
    notificationStore.showNotification({
      type: 'error',
      message: t('settings.notification.please_enter_email'),
    })
  }

  if (v$.value.$invalid) {
    return true
  }

  isLoading.value = true

  let data = {
    ...installerStore.currentInstaller,
  }

  try {
    let response = null
    if (installerStore.isEdit) {
      response = await installerStore.updateInstaller(data)
    } else {
      response = await installerStore.addInstaller(data)
    }

    if (response.data) {
      isLoading.value = false
      // Automatically create newly created installer
      if (route.name === 'invoices.create' || route.name === 'invoices.edit') {
        invoiceStore.selectInstaller(response.data.data.id)
      }
      if (
        route.name === 'estimates.create' ||
        route.name === 'estimates.edit'
      ) {
        estimateStore.selectInstaller(response.data.data.id)
      }
      if (
        route.name === 'recurring-invoices.create' ||
        route.name === 'recurring-invoices.edit'
      ) {
        recurringInvoiceStore.selectInstaller(response.data.data.id)
      }
      closeInstallerModal()
    }
  } catch (err) {
    console.error(err)
    isLoading.value = false
  }
}

function closeInstallerModal() {
  modalStore.closeModal()
  setTimeout(() => {
    installerStore.resetCurrentInstaller()
    v$.value.$reset()
  }, 300)
}
</script>
