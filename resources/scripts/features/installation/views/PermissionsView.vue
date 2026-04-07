<template>
  <BaseWizardStep
    :title="$t('wizard.permissions.permissions')"
    :description="$t('wizard.permissions.permission_desc')"
  >
    <!-- Placeholders -->
    <BaseContentPlaceholders v-if="isFetchingInitialData">
      <div
        v-for="n in 3"
        :key="n"
        class="grid grid-flow-row grid-cols-3 lg:gap-24 sm:gap-4 border border-line-default"
      >
        <BaseContentPlaceholdersText :lines="1" class="col-span-4 p-3" />
      </div>
      <BaseContentPlaceholdersBox
        :rounded="true"
        class="mt-10"
        style="width: 96px; height: 42px"
      />
    </BaseContentPlaceholders>

    <div v-else class="relative">
      <div
        v-for="(permission, index) in permissions"
        :key="index"
        class="border border-line-default"
      >
        <div class="grid grid-flow-row grid-cols-3 lg:gap-24 sm:gap-4">
          <div class="col-span-2 p-3">{{ permission.folder }}</div>
          <div class="p-3 text-right">
            <span
              v-if="permission.isSet"
              class="inline-block w-4 h-4 ml-3 mr-2 rounded-full bg-green-500"
            />
            <span
              v-else
              class="inline-block w-4 h-4 ml-3 mr-2 rounded-full bg-red-500"
            />
            <span>{{ permission.permission }}</span>
          </div>
        </div>
      </div>

      <BaseButton
        v-show="!isFetchingInitialData"
        class="mt-10"
        :loading="isSaving"
        :disabled="isSaving"
        @click="next"
      >
        <template #left="slotProps">
          <BaseIcon name="ArrowRightIcon" :class="slotProps.class" />
        </template>
        {{ $t('wizard.continue') }}
      </BaseButton>
    </div>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { client } from '../../../api/client'

interface Permission {
  folder: string
  permission: string
  isSet: boolean
}

interface Emits {
  (e: 'next'): void
}

const emit = defineEmits<Emits>()

const isFetchingInitialData = ref<boolean>(false)
const isSaving = ref<boolean>(false)
const permissions = ref<Permission[]>([])

onMounted(() => {
  getPermissions()
})

async function getPermissions(): Promise<void> {
  isFetchingInitialData.value = true
  try {
    const { data } = await client.get('/api/v1/installation/permissions')
    permissions.value = data.permissions?.permissions ?? []
  } finally {
    isFetchingInitialData.value = false
  }
}

function next(): void {
  isSaving.value = true
  emit('next')
  isSaving.value = false
}
</script>
