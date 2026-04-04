<template>
  <BaseWizardStep
    :title="$t('wizard.database.database')"
    :description="$t('wizard.database.desc')"
    step-container="w-full p-8 mb-8 bg-surface border border-line-default border-solid rounded md:w-full"
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
        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2">
          <BaseInputGroup :label="$t('wizard.database.db_name')">
            <BaseInput v-model="databaseData.database_name" type="text" disabled />
          </BaseInputGroup>
        </div>
      </template>

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
import { client } from '../../../api/client'

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

interface Emits {
  (e: 'next', step: number): void
}

interface DatabaseDriverOption {
  label: string
  value: string
}

const emit = defineEmits<Emits>()
const { t } = useI18n()

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

  const { data } = await client.get('/api/v1/installation/database/config', { params })

  if (data.success) {
    databaseData.database_connection = data.config.database_connection
  }

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
}

function onChangeDriver(connection: string): void {
  getDatabaseConfig(connection)
}

async function next(): Promise<void> {
  isSaving.value = true

  try {
    const { data: res } = await client.post(
      '/api/v1/installation/database/config',
      databaseData,
    )

    if (res.success) {
      await client.post('/api/v1/installation/finish')
      emit('next', 3)
    }
  } finally {
    isSaving.value = false
  }
}
</script>
