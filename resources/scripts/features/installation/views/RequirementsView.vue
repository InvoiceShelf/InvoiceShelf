<template>
  <BaseWizardStep
    :title="$t('wizard.req.system_req')"
    :description="$t('wizard.req.system_req_desc')"
  >
    <div
      v-if="phpSupportInfo || requirements"
      class="w-full overflow-hidden rounded-lg border border-line-default divide-y divide-line-default"
    >
      <!-- PHP version row — first so it's visually grouped with the extension list -->
      <div
        v-if="phpSupportInfo"
        class="flex items-center justify-between px-4 py-3 hover:bg-hover transition-colors"
      >
        <span class="text-sm text-body">
          {{ $t('wizard.req.php_req_version', { version: phpSupportInfo.minimum }) }}
        </span>
        <span class="flex items-center gap-2 text-sm font-medium text-body">
          <span class="text-muted">{{ phpSupportInfo.current }}</span>
          <RequirementBadge :ok="phpSupportInfo.supported" />
        </span>
      </div>

      <!-- Extension rows -->
      <div
        v-for="(fulfilled, name) in requirements"
        :key="name"
        class="flex items-center justify-between px-4 py-3 hover:bg-hover transition-colors"
      >
        <span class="text-sm text-body font-mono">{{ name }}</span>
        <RequirementBadge :ok="fulfilled" />
      </div>
    </div>

    <div class="mt-8 flex justify-end">
      <BaseButton v-if="hasNext" @click="next">
        {{ $t('wizard.continue') }}
        <template #right="slotProps">
          <BaseIcon name="ArrowRightIcon" :class="slotProps.class" />
        </template>
      </BaseButton>

      <BaseButton
        v-if="!requirements"
        :loading="isSaving"
        :disabled="isSaving"
        @click="getRequirements"
      >
        {{ $t('wizard.req.check_req') }}
      </BaseButton>
    </div>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { installClient } from '../../../api/install-client'
import RequirementBadge from '../components/RequirementBadge.vue'
import { useInstallationFeedback } from '../use-installation-feedback'

interface PhpSupportInfo {
  minimum: string
  current: string
  supported: boolean
}

const router = useRouter()
const { showRequestError } = useInstallationFeedback()

const requirements = ref<Record<string, boolean> | null>(null)
const phpSupportInfo = ref<PhpSupportInfo | null>(null)
const isSaving = ref<boolean>(false)

const hasNext = computed<boolean>(() => {
  if (!requirements.value || !phpSupportInfo.value) return false
  const allMet = Object.values(requirements.value).every((v) => v)
  return allMet && phpSupportInfo.value.supported
})

onMounted(() => {
  getRequirements()
})

async function getRequirements(): Promise<void> {
  isSaving.value = true
  try {
    const { data } = await installClient.get('/api/v1/installation/requirements')
    requirements.value = data?.requirements?.requirements?.php ?? null
    phpSupportInfo.value = data?.phpSupportInfo ?? null
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isSaving.value = false
  }
}

async function next(): Promise<void> {
  isSaving.value = true
  try {
    await router.push({ name: 'installation.permissions' })
  } finally {
    isSaving.value = false
  }
}
</script>
