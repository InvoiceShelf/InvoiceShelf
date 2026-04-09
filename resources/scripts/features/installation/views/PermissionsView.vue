<template>
  <BaseWizardStep
    :title="$t('wizard.permissions.permissions')"
    :description="$t('wizard.permissions.permission_desc')"
  >
    <!-- Placeholders -->
    <div
      v-if="isFetchingInitialData"
      class="w-full overflow-hidden rounded-lg border border-line-default divide-y divide-line-default"
    >
      <div
        v-for="n in 3"
        :key="n"
        class="flex items-center justify-between px-4 py-3"
      >
        <BaseContentPlaceholders>
          <BaseContentPlaceholdersText :lines="1" class="w-32" />
        </BaseContentPlaceholders>
        <div class="h-6 w-6 rounded-full bg-surface-tertiary animate-pulse" />
      </div>
    </div>

    <div
      v-else-if="permissions.length"
      class="w-full overflow-hidden rounded-lg border border-line-default divide-y divide-line-default"
    >
      <div
        v-for="(permission, index) in permissions"
        :key="index"
        class="flex items-center justify-between px-4 py-3 hover:bg-hover transition-colors"
      >
        <span class="text-sm text-body font-mono">{{ permission.folder }}</span>
        <span class="flex items-center gap-2 text-sm text-muted">
          <span class="font-medium">{{ permission.permission }}</span>
          <RequirementBadge :ok="permission.isSet" />
        </span>
      </div>
    </div>

    <div class="mt-8 flex justify-end">
      <BaseButton
        v-show="!isFetchingInitialData"
        :loading="isSaving"
        :disabled="isSaving"
        @click="next"
      >
        {{ $t('wizard.continue') }}
        <template #right="slotProps">
          <BaseIcon name="ArrowRightIcon" :class="slotProps.class" />
        </template>
      </BaseButton>
    </div>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { installClient } from '../../../api/install-client'
import RequirementBadge from '../components/RequirementBadge.vue'
import { useInstallationFeedback } from '../use-installation-feedback'

interface Permission {
  folder: string
  permission: string
  isSet: boolean
}

const router = useRouter()
const { showRequestError } = useInstallationFeedback()

const isFetchingInitialData = ref<boolean>(false)
const isSaving = ref<boolean>(false)
const permissions = ref<Permission[]>([])

onMounted(() => {
  getPermissions()
})

async function getPermissions(): Promise<void> {
  isFetchingInitialData.value = true
  try {
    const { data } = await installClient.get('/api/v1/installation/permissions')
    permissions.value = data.permissions?.permissions ?? []
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isFetchingInitialData.value = false
  }
}

async function next(): Promise<void> {
  isSaving.value = true
  try {
    await router.push({ name: 'installation.database' })
  } finally {
    isSaving.value = false
  }
}
</script>
