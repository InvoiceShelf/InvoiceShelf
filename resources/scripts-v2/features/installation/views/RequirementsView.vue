<template>
  <BaseWizardStep
    :title="$t('wizard.req.system_req')"
    :description="$t('wizard.req.system_req_desc')"
  >
    <div class="w-full">
      <div class="mb-6">
        <div
          v-if="phpSupportInfo"
          class="grid grid-flow-row grid-cols-3 p-3 border border-line-default lg:gap-24 sm:gap-4"
        >
          <div class="col-span-2 text-sm">
            {{ $t('wizard.req.php_req_version', { version: phpSupportInfo.minimum }) }}
          </div>
          <div class="text-right">
            {{ phpSupportInfo.current }}
            <span
              v-if="phpSupportInfo.supported"
              class="inline-block w-4 h-4 ml-3 mr-2 bg-green-500 rounded-full"
            />
            <span
              v-else
              class="inline-block w-4 h-4 ml-3 mr-2 bg-red-500 rounded-full"
            />
          </div>
        </div>

        <div v-if="requirements">
          <div
            v-for="(fulfilled, name) in requirements"
            :key="name"
            class="grid grid-flow-row grid-cols-3 p-3 border border-line-default lg:gap-24 sm:gap-4"
          >
            <div class="col-span-2 text-sm">{{ name }}</div>
            <div class="text-right">
              <span
                v-if="fulfilled"
                class="inline-block w-4 h-4 ml-3 mr-2 bg-green-500 rounded-full"
              />
              <span
                v-else
                class="inline-block w-4 h-4 ml-3 mr-2 bg-red-500 rounded-full"
              />
            </div>
          </div>
        </div>
      </div>

      <BaseButton v-if="hasNext" @click="next">
        {{ $t('wizard.continue') }}
        <template #left="slotProps">
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
import { client } from '../../../api/client'

interface PhpSupportInfo {
  minimum: string
  current: string
  supported: boolean
}

interface Emits {
  (e: 'next'): void
}

const emit = defineEmits<Emits>()

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
    const { data } = await client.get('/api/v1/installation/requirements')
    requirements.value = data?.requirements?.requirements?.php ?? null
    phpSupportInfo.value = data?.phpSupportInfo ?? null
  } finally {
    isSaving.value = false
  }
}

function next(): void {
  isSaving.value = true
  emit('next')
  isSaving.value = false
}
</script>
