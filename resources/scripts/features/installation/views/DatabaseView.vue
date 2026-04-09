<template>
  <BaseWizardStep
    :title="$t('wizard.database.database')"
    :description="$t('wizard.database.desc')"
  >
    <form @submit.prevent="next">
      <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup
          :label="$t('wizard.database.connection')"
          required
        >
          <BaseMultiselect
            v-model="databaseData.database_connection"
            :options="databaseDrivers"
            label="label"
            value-prop="value"
            :can-deselect="false"
            :can-clear="false"
            @update:model-value="onChangeDriver"
          />
        </BaseInputGroup>
      </div>

      <!-- MySQL / PostgreSQL fields -->
      <template v-if="databaseData.database_connection !== 'sqlite'">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
          <BaseInputGroup :label="$t('wizard.database.hostname')" required>
            <BaseInput v-model="databaseData.database_hostname" type="text" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('wizard.database.port')" required>
            <BaseInput v-model="databaseData.database_port" type="text" />
          </BaseInputGroup>
        </div>

        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
          <BaseInputGroup :label="$t('wizard.database.db_name')" required>
            <BaseInput v-model="databaseData.database_name" type="text" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('wizard.database.username')" required>
            <BaseInput v-model="databaseData.database_username" type="text" />
          </BaseInputGroup>
        </div>

        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2">
          <BaseInputGroup :label="$t('wizard.database.password')">
            <BaseInput v-model="databaseData.database_password" type="password" />
          </BaseInputGroup>
        </div>
      </template>

      <!-- SQLite fields -->
      <template v-else>
        <div class="mb-6">
          <BaseInputGroup
            :label="$t('wizard.database.db_path')"
            help-text="Absolute path or path relative to the project root. Defaults to storage/app/database.sqlite."
            required
          >
            <BaseInput v-model="databaseData.database_name" type="text" />
          </BaseInputGroup>
        </div>
      </template>

      <div class="mb-6">
        <BaseCheckbox
          v-model="databaseData.database_overwrite"
          :label="$t('wizard.database.overwrite')"
        />
      </div>

      <BaseButton :loading="isSaving" :disabled="isSaving" class="mt-4">
        <template #left="slotProps">
          <BaseIcon name="ArrowRightIcon" :class="slotProps.class" />
        </template>
        {{ $t('wizard.continue') }}
      </BaseButton>
    </form>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { installClient } from '../../../api/install-client'
import { useDialogStore } from '../../../stores/dialog.store'
import { useInstallationFeedback } from '../use-installation-feedback'

interface DatabaseConfig {
  database_connection: string
  database_hostname: string
  database_port: string
  database_name: string | null
  database_username: string | null
  database_password: string | null
  database_overwrite: boolean
  app_url: string
  app_locale: string | null
}

interface DatabaseDriverOption {
  label: string
  value: string
}

const router = useRouter()
const { t } = useI18n()
const dialogStore = useDialogStore()
const { isSuccessfulResponse, showRequestError, showResponseError } = useInstallationFeedback()

const isSaving = ref<boolean>(false)

const databaseDrivers = ref<DatabaseDriverOption[]>([
  { label: 'MySQL', value: 'mysql' },
  { label: 'PostgreSQL', value: 'pgsql' },
  { label: 'SQLite', value: 'sqlite' },
])

const databaseData = reactive<DatabaseConfig>({
  database_connection: 'mysql',
  database_hostname: '127.0.0.1',
  database_port: '3306',
  database_name: null,
  database_username: null,
  database_password: null,
  database_overwrite: false,
  app_url: window.location.origin,
  app_locale: null,
})

onMounted(() => {
  getDatabaseConfig()
})

async function getDatabaseConfig(connection?: string): Promise<void> {
  const params: Record<string, string> = {}
  if (connection) params.connection = connection

  try {
    const { data } = await installClient.get('/api/v1/installation/database/config', { params })

    if (!isSuccessfulResponse(data)) {
      showResponseError(data)
      return
    }

    databaseData.database_connection = data.config.database_connection

    if (data.config.database_connection === 'sqlite') {
      databaseData.database_name = data.config.database_name
    } else {
      databaseData.database_name = null
      if (data.config.database_host) {
        databaseData.database_hostname = data.config.database_host
      }
      if (data.config.database_port) {
        databaseData.database_port = data.config.database_port
      }
    }
  } catch (error: unknown) {
    showRequestError(error)
  }
}

function onChangeDriver(connection: string): void {
  getDatabaseConfig(connection)
}

async function next(): Promise<void> {
  if (databaseData.database_overwrite) {
    const confirmed = await dialogStore.openDialog({
      title: t('general.are_you_sure'),
      message: t('wizard.database.overwrite_confirm_desc'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })

    if (!confirmed) {
      return
    }
  }

  isSaving.value = true

  try {
    const { data: res } = await installClient.post(
      '/api/v1/installation/database/config',
      databaseData,
    )

    if (!isSuccessfulResponse(res)) {
      showResponseError(res)
      return
    }

    const { data: finishResponse } = await installClient.post('/api/v1/installation/finish')

    if (!isSuccessfulResponse(finishResponse)) {
      showResponseError(finishResponse)
      return
    }

    await router.push({ name: 'installation.domain' })
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isSaving.value = false
  }
}
</script>
