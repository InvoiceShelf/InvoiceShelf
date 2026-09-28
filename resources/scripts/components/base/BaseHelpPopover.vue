<template>
  <PopoverRoot v-model:open="open">
    <PopoverTrigger as-child>
      <button
        type="button"
        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted transition-colors hover:bg-hover hover:text-heading focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
        :aria-label="$t('general.about_page', { page: title })"
      >
        <BaseIcon
          name="InformationCircleIcon"
          class="h-5 w-5"
          aria-hidden="true"
        />
      </button>
    </PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        side="bottom"
        align="start"
        :side-offset="8"
        :collision-padding="16"
        :aria-labelledby="`${id}-title`"
        :aria-describedby="`${id}-description`"
        class="z-50 max-h-[var(--reka-popover-content-available-height)] w-80 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-xl border border-line-default bg-surface p-4 text-start shadow-lg focus:outline-hidden"
      >
        <div class="flex items-start justify-between gap-3">
          <h2
            :id="`${id}-title`"
            class="pt-1 text-sm font-semibold text-heading"
          >
            {{ title }}
          </h2>
          <PopoverClose as-child>
            <button
              type="button"
              class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted hover:bg-hover hover:text-heading focus-visible:outline-2 focus-visible:outline-primary-500"
              :aria-label="$t('general.close')"
            >
              <BaseIcon name="XMarkIcon" class="h-4 w-4" aria-hidden="true" />
            </button>
          </PopoverClose>
        </div>
        <p
          :id="`${id}-description`"
          class="mt-2 whitespace-pre-line text-sm leading-6 text-body"
        >
          {{ text }}
        </p>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
<script setup lang="ts">
import { ref, useId, watch } from 'vue'
import {
  PopoverClose,
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'reka-ui'
const props = defineProps<{ title: string; text: string }>()
const id = useId()
const open = ref(false)
watch(
  () => [props.title, props.text],
  () => {
    open.value = false
  },
)
</script>
