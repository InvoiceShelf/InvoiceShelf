<template>
  <div class="flex flex-wrap items-end justify-between gap-3 md:flex-nowrap md:gap-6">
    <div class="flex flex-col min-w-0 grow">
      <div v-if="title || $slots.leading" class="flex items-center min-w-0 gap-3">
        <slot name="leading" />
        <div class="min-w-0">
          <h1 v-if="title" class="font-semibold text-left break-words text-title text-heading">
            {{ title }}
          </h1>
          <p v-if="subtitle" class="mt-0.5 text-sm truncate text-muted">{{ subtitle }}</p>
        </div>
      </div>
      <slot />
    </div>

    <!--
      Where the actions go on a phone:
      - on the title's row when every one of them is an icon (a list page's
        filter and "+", which BaseButton shrinks to icons in here);
      - otherwise in the action bar at the bottom of the screen, labels kept.
      Wider screens always keep them on the title's row.
    -->
    <div
      v-if="$slots.actions && placement === 'inline'"
      ref="actionsEl"
      :class="deciding ? 'invisible' : ''"
      class="flex flex-wrap items-center justify-end gap-2 ml-auto shrink-0 md:gap-3 *:ml-0"
    >
      <slot name="actions" />
    </div>

    <BaseActionBar v-else-if="$slots.actions && placement === 'bar'">
      <slot name="actions" />
    </BaseActionBar>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, provide, ref, useSlots, watch } from 'vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'

interface Props {
  title?: string
  /** One quiet line under the title: an email, a date, a contact */
  subtitle?: string
  /** Phones only: 'auto' picks the title row or the action bar from the actions themselves */
  phoneActions?: 'auto' | 'inline' | 'bar'
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  subtitle: '',
  phoneActions: 'auto',
})

const slots = useSlots()
const { isPhone } = useBreakpoints()

provide('pageHeaderCompact', isPhone)

const actionsEl = ref<HTMLElement | null>(null)
const allIcons = ref<boolean>(true)
const deciding = ref<boolean>(false)

const placement = computed<'inline' | 'bar'>(() => {
  if (!isPhone.value || !slots.actions || props.phoneActions === 'inline') {
    return 'inline'
  }

  if (props.phoneActions === 'bar') {
    return 'bar'
  }

  return allIcons.value ? 'inline' : 'bar'
})

// Render the actions on the title row, hidden, and look at what they are.
async function decide(): Promise<void> {
  if (!isPhone.value || props.phoneActions !== 'auto' || !slots.actions) {
    deciding.value = false
    return
  }

  allIcons.value = true
  deciding.value = true
  await nextTick()

  const shown = Array.from(actionsEl.value?.children ?? []).filter(
    (child) => getComputedStyle(child).display !== 'none',
  )

  allIcons.value = shown.every(
    (child) => child.matches('[data-icon-only]') || child.querySelector('[data-icon-only]') !== null,
  )
  deciding.value = false
}

onMounted(decide)
watch(isPhone, decide)
</script>
