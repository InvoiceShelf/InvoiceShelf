<template>
  <div :class="mobileViewRow() ? 'grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3' : 'flex flex-wrap items-end justify-between gap-3 md:flex-nowrap md:gap-6'">
    <div :class="mobileViewRow() ? 'col-span-2' : 'flex-1'" class="flex min-w-0 flex-col">
      <div v-if="title || $slots.leading" class="flex items-center min-w-0 gap-3">
        <slot name="leading" />
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <div class="flex min-w-0 items-center gap-1">
              <h1 v-if="title" class="min-w-0 font-semibold text-start break-words text-title text-heading">
                {{ title }}
              </h1>
              <BaseHelpPopover v-if="help" :title="helpTitle || title" :text="help" />
            </div>
            <slot v-if="!mobileViewRow()" name="title-suffix" />
          </div>
          <p v-if="subtitle" class="mt-0.5 text-sm truncate text-muted">{{ subtitle }}</p>
        </div>
      </div>
      <slot />
    </div>

    <div v-if="mobileViewRow()" class="col-start-1 row-start-2 min-w-0">
      <slot name="title-suffix" />
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
      :class="[deciding ? 'invisible' : '', mobileViewRow() ? 'col-start-2 row-start-2' : '']"
      class="flex flex-wrap items-center justify-end gap-2 ms-auto shrink-0 md:gap-3 *:ms-0"
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
import BaseHelpPopover from '../base/BaseHelpPopover.vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'

interface Props {
  title?: string
  /** One quiet line under the title: an email, a date, a contact */
  subtitle?: string
  /** Optional explanation revealed by the title's info button. */
  help?: string
  helpTitle?: string
  /** Phones only: 'auto' picks the title row or the action bar from the actions themselves */
  phoneActions?: 'auto' | 'inline' | 'bar'
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  subtitle: '',
  help: '',
  helpTitle: '',
  phoneActions: 'auto',
})

const slots = useSlots()
const { isPhone } = useBreakpoints()
// View selection gets its own control row on phones, next to the list actions.
function mobileViewRow(): boolean {
  return isPhone.value && !!slots['title-suffix']
}

provide('pageHeaderCompact', isPhone)

const actionsEl = ref<HTMLElement | null>(null)
const allIcons = ref<boolean>(true)
const deciding = ref<boolean>(false)

// Only the company shell has a bottom bar; elsewhere (the customer portal,
// say) actions stay on the title row
const hasBar = ref<boolean>(false)

const placement = computed<'inline' | 'bar'>(() => {
  if (!isPhone.value || !slots.actions || !hasBar.value || props.phoneActions === 'inline') {
    return 'inline'
  }

  if (props.phoneActions === 'bar') {
    return 'bar'
  }

  return allIcons.value ? 'inline' : 'bar'
})

// Render the actions on the title row, hidden, and look at what they are.
async function decide(): Promise<void> {
  hasBar.value = document.getElementById('app-action-bar') !== null

  if (!isPhone.value || !hasBar.value || props.phoneActions !== 'auto' || !slots.actions) {
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
