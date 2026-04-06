<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import {
  diskService,
  type CreateDiskPayload,
  type Disk,
  type DiskDriverValue,
} from '@v2/api/services/disk.service'
import {
  getErrorTranslationKey,
  handleApiError,
} from '@v2/utils/error-handling'

interface DiskField {
  key: string
  labelKey: string
  placeholder?: string
}

interface DiskDriverOption {
  name: string
  value: DiskDriverValue
}

interface DiskForm {
  name: string
  driver: DiskDriverValue
  set_as_default: boolean
  credentials: Record<string, string>
}

const DRIVER_FIELDS: Record<DiskDriverValue, DiskField[]> = {
  local: [
    {
      key: 'root',
      labelKey: 'settings.disk.local_root',
      placeholder: 'Ex. /user/root/',
    },
  ],
  s3: [
    {
      key: 'root',
      labelKey: 'settings.disk.aws_root',
      placeholder: 'Ex. /user/root/',
    },
    {
      key: 'key',
      labelKey: 'settings.disk.aws_key',
      placeholder: 'Ex. KEIS4S39SERSDS',
    },
    {
      key: 'secret',
      labelKey: 'settings.disk.aws_secret',
      placeholder: 'Ex. ********',
    },
    {
      key: 'region',
      labelKey: 'settings.disk.aws_region',
      placeholder: 'Ex. us-west',
    },
    {
      key: 'bucket',
      labelKey: 'settings.disk.aws_bucket',
      placeholder: 'Ex. AppName',
    },
  ],
  s3compat: [
    {
      key: 'endpoint',
      labelKey: 'settings.disk.s3_endpoint',
      placeholder: 'Ex. https://s3.example.com',
    },
    {
      key: 'key',
      labelKey: 'settings.disk.s3_key',
      placeholder: 'Ex. KEIS4S39SERSDS',
    },
    {
      key: 'secret',
      labelKey: 'settings.disk.s3_secret',
      placeholder: 'Ex. ********',
    },
    {
      key: 'region',
      labelKey: 'settings.disk.s3_region',
      placeholder: 'Ex. us-west',
    },
    {
      key: 'bucket',
      labelKey: 'settings.disk.s3_bucket',
      placeholder: 'Ex. AppName',
    },
    {
      key: 'root',
      labelKey: 'settings.disk.s3_root',
      placeholder: 'Ex. /user/root/',
    },
  ],
  doSpaces: [
    {
      key: 'key',
      labelKey: 'settings.disk.do_spaces_key',
      placeholder: 'Ex. KEIS4S39SERSDS',
    },
    {
      key: 'secret',
      labelKey: 'settings.disk.do_spaces_secret',
      placeholder: 'Ex. ********',
    },
    {
      key: 'region',
      labelKey: 'settings.disk.do_spaces_region',
      placeholder: 'Ex. nyc3',
    },
    {
      key: 'bucket',
      labelKey: 'settings.disk.do_spaces_bucket',
      placeholder: 'Ex. AppName',
    },
    {
      key: 'endpoint',
      labelKey: 'settings.disk.do_spaces_endpoint',
      placeholder: 'Ex. https://nyc3.digitaloceanspaces.com',
    },
    {
      key: 'root',
      labelKey: 'settings.disk.do_spaces_root',
      placeholder: 'Ex. /user/root/',
    },
  ],
  dropbox: [
    {
      key: 'token',
      labelKey: 'settings.disk.dropbox_token',
    },
    {
      key: 'key',
      labelKey: 'settings.disk.dropbox_key',
      placeholder: 'Ex. KEIS4S39SERSDS',
    },
    {
      key: 'secret',
      labelKey: 'settings.disk.dropbox_secret',
      placeholder: 'Ex. ********',
    },
    {
      key: 'app',
      labelKey: 'settings.disk.dropbox_app',
    },
    {
      key: 'root',
      labelKey: 'settings.disk.dropbox_root',
      placeholder: 'Ex. /user/root/',
    },
  ],
}

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref(false)
const isFetchingInitialData = ref(false)
const driverOptions = ref<DiskDriverOption[]>([])
const currentDisk = ref<Disk | null>(null)

const form = reactive<DiskForm>({
  name: '',
  driver: 'local',
  set_as_default: false,
  credentials: {},
})

const modalActive = computed<boolean>(() => {
  return modalStore.active && modalStore.componentName === 'AdminFileDiskModal'
})

const currentFields = computed<DiskField[]>(() => {
  return DRIVER_FIELDS[form.driver] ?? []
})

const defaultSwitchDisabled = computed<boolean>(() => {
  return Boolean(currentDisk.value?.set_as_default)
})

const rules = computed(() => {
  const credentialRules = currentFields.value.reduce<
    Record<string, { required: ReturnType<typeof helpers.withMessage> }>
  >((ruleSet, field) => {
    ruleSet[field.key] = {
      required: helpers.withMessage(t('validation.required'), required),
    }

    return ruleSet
  }, {})

  return {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    driver: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    credentials: credentialRules,
  }
})

const v$ = useVuelidate(rules, form)

async function setInitialData(): Promise<void> {
  resetForm()
  isFetchingInitialData.value = true

  try {
    const response = await diskService.getDrivers()
    driverOptions.value = response.drivers

    if (isDisk(modalStore.data)) {
      currentDisk.value = modalStore.data
      form.name = currentDisk.value.name
      form.driver = currentDisk.value.driver
      form.set_as_default = currentDisk.value.set_as_default
      form.credentials = normalizeDiskCredentials(
        currentDisk.value.credentials,
        currentDisk.value.driver
      )
    } else {
      currentDisk.value = null
      form.driver = resolveInitialDriver(response.drivers, response.default)
      form.credentials = await loadDriverCredentials(form.driver)
    }
  } catch (error: unknown) {
    showApiError(error)
  } finally {
    isFetchingInitialData.value = false
  }
}

async function handleDriverChange(value: DiskDriverValue): Promise<void> {
  v$.value.driver.$touch()
  form.driver = value

  const existingName = form.name
  const existingDefaultState = form.set_as_default

  form.credentials = await loadDriverCredentials(value)
  form.name = existingName
  form.set_as_default = existingDefaultState
}

async function saveDisk(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  try {
    const payload: CreateDiskPayload = {
      name: form.name.trim(),
      driver: form.driver,
      credentials: { ...form.credentials },
      set_as_default: form.set_as_default,
    }

    if (currentDisk.value) {
      await diskService.update(currentDisk.value.id, payload)
      notificationStore.showNotification({
        type: 'success',
        message: t('settings.disk.success_update'),
      })
    } else {
      await diskService.create(payload)
      notificationStore.showNotification({
        type: 'success',
        message: t('settings.disk.success_create'),
      })
    }

    modalStore.refreshData?.()
    closeModal()
  } catch (error: unknown) {
    showApiError(error)
  } finally {
    isSaving.value = false
  }
}

async function loadDriverCredentials(
  driver: DiskDriverValue
): Promise<Record<string, string>> {
  if (currentDisk.value && currentDisk.value.driver === driver) {
    return normalizeDiskCredentials(currentDisk.value.credentials, driver)
  }

  const defaults = await diskService.get(driver)
  return normalizeDiskCredentials(defaults, driver)
}

function resolveInitialDriver(
  drivers: DiskDriverOption[],
  defaultDriver: string
): DiskDriverValue {
  const matchedDriver = drivers.find((driver) => driver.value === defaultDriver)
  return matchedDriver?.value ?? drivers[0]?.value ?? 'local'
}

function normalizeDiskCredentials(
  credentials: Disk['credentials'] | Record<string, string>,
  driver: DiskDriverValue
): Record<string, string> {
  const emptyCredentials = createEmptyCredentials(driver)

  if (!credentials) {
    return emptyCredentials
  }

  if (typeof credentials === 'string') {
    try {
      const parsedCredentials = JSON.parse(credentials) as unknown

      if (typeof parsedCredentials === 'string') {
        return {
          ...emptyCredentials,
          root: parsedCredentials,
        }
      }

      if (parsedCredentials && typeof parsedCredentials === 'object') {
        return {
          ...emptyCredentials,
          ...stringifyRecord(parsedCredentials as Record<string, unknown>),
        }
      }
    } catch {
      return {
        ...emptyCredentials,
        root: credentials,
      }
    }
  }

  return {
    ...emptyCredentials,
    ...stringifyRecord(credentials as Record<string, unknown>),
  }
}

function createEmptyCredentials(driver: DiskDriverValue): Record<string, string> {
  return currentFieldsFor(driver).reduce<Record<string, string>>(
    (credentialSet, field) => {
      credentialSet[field.key] = ''
      return credentialSet
    },
    {}
  )
}

function currentFieldsFor(driver: DiskDriverValue): DiskField[] {
  return DRIVER_FIELDS[driver] ?? []
}

function stringifyRecord(
  value: Record<string, unknown>
): Record<string, string> {
  return Object.entries(value).reduce<Record<string, string>>(
    (record, [key, entry]) => {
      record[key] = entry == null ? '' : String(entry)
      return record
    },
    {}
  )
}

function touchCredential(key: string): void {
  const credentialField = (
    v$.value.credentials as Record<
      string,
      { $touch: () => void }
    >
  )[key]

  credentialField?.$touch()
}

function credentialError(key: string): string {
  const credentialField = (
    v$.value.credentials as Record<
      string,
      { $error: boolean; $errors: Array<{ $message: string }> }
    >
  )[key]

  if (!credentialField?.$error) {
    return ''
  }

  return credentialField.$errors[0]?.$message ?? ''
}

function isCredentialInvalid(key: string): boolean {
  const credentialField = (
    v$.value.credentials as Record<string, { $error: boolean }>
  )[key]

  return Boolean(credentialField?.$error)
}

function showApiError(error: unknown): void {
  const normalizedError = handleApiError(error)
  const translationKey = getErrorTranslationKey(normalizedError.message)

  notificationStore.showNotification({
    type: 'error',
    message: translationKey ? t(translationKey) : normalizedError.message,
  })
}

function resetForm(): void {
  form.name = ''
  form.driver = 'local'
  form.set_as_default = false
  form.credentials = {}
  currentDisk.value = null
  v$.value.$reset()
}

function closeModal(): void {
  modalStore.closeModal()

  setTimeout(() => {
    resetForm()
    driverOptions.value = []
  }, 300)
}

function isDisk(value: unknown): value is Disk {
  return Boolean(
    value &&
      typeof value === 'object' &&
      'id' in value &&
      'driver' in value &&
      'name' in value
  )
}
</script>

<template>
  <BaseModal :show="modalActive" @close="closeModal" @open="setInitialData">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <form @submit.prevent="saveDisk">
      <div class="p-4 md:p-6">
        <BaseInputGrid>
          <BaseInputGroup
            :label="$t('settings.disk.name')"
            :error="v$.name.$error && v$.name.$errors[0]?.$message"
            required
          >
            <BaseInput
              v-model.trim="form.name"
              :invalid="v$.name.$error"
              @input="v$.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.disk.driver')"
            :error="v$.driver.$error && v$.driver.$errors[0]?.$message"
            required
          >
            <BaseMultiselect
              v-model="form.driver"
              :options="driverOptions"
              :content-loading="isFetchingInitialData"
              :can-deselect="false"
              :invalid="v$.driver.$error"
              label="name"
              track-by="value"
              value-prop="value"
              searchable
              @update:model-value="handleDriverChange"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-for="field in currentFields"
            :key="field.key"
            :label="$t(field.labelKey)"
            :error="credentialError(field.key)"
            required
          >
            <BaseInput
              v-model.trim="form.credentials[field.key]"
              :invalid="isCredentialInvalid(field.key)"
              :placeholder="field.placeholder"
              @input="touchCredential(field.key)"
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <div class="mt-6 flex items-center">
          <div class="relative flex items-center w-12">
            <BaseSwitch
              v-model="form.set_as_default"
              :disabled="defaultSwitchDisabled"
              class="flex"
            />
          </div>

          <div class="ml-4">
            <p class="mb-1 text-base leading-snug text-heading">
              {{ $t('settings.disk.is_default') }}
            </p>
          </div>
        </div>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          type="button"
          variant="primary-outline"
          class="mr-3"
          @click="closeModal"
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
          {{ currentDisk ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
