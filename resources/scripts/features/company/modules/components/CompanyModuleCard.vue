<template>
  <div
    class="
      flex flex-col p-6 rounded-lg border border-line-default bg-surface-secondary
      shadow-sm hover:shadow-md transition-shadow
    "
  >
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="
          shrink-0 h-10 w-10 rounded-lg bg-primary-50 text-primary-600
          flex items-center justify-center
        ">
          <BaseIcon :name="iconName" class="h-5 w-5" />
        </div>
        <div>
          <h3 class="text-base font-semibold text-heading">{{ data.display_name }}</h3>
          <p class="text-xs text-muted">{{ $t('modules.version') }} {{ data.version }}</p>
        </div>
      </div>
    </div>

    <div class="mt-6 flex items-center justify-end">
      <BaseButton
        v-if="data.has_settings"
        size="sm"
        variant="primary-outline"
        @click="emit('open-settings', data)"
      >
        <template #left="slotProps">
          <BaseIcon name="CogIcon" :class="slotProps.class" />
        </template>
        {{ $t('modules.settings.open') }}
      </BaseButton>
      <span v-else class="text-xs text-subtle italic">
        {{ $t('modules.settings.none') }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CompanyModuleSummary } from '../store'

interface Props {
  data: CompanyModuleSummary
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'open-settings', module: CompanyModuleSummary): void
}>()

const iconName = computed<string>(() => {
  return props.data.menu?.icon ?? 'PuzzlePieceIcon'
})
</script>
