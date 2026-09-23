<template>
  <!--
    A page's own actions, pinned to the bottom edge on phones where they take
    the tab bar's place. Wider screens keep the same actions in the page
    header, so nothing renders there. Each action takes the width its label
    needs plus a share of what is left, so a long label never wraps; a child
    marked data-bar-info (a running total, say) keeps its own width.
  -->
  <span ref="anchor" hidden />

  <Teleport v-if="isPhone" defer to="#app-action-bar">
    <!--
      Catches clicks from the submit buttons inside; they are real buttons, so
      a keyboard fires them too
    -->
    <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
    <div
      class="
        flex items-center gap-2 px-4 pt-3 border-t glass-bar border-(--glass-edge) safe-drawer
        *:min-w-0 *:flex-auto [&>*:has([data-overflow])]:flex-none [&>[data-bar-info]]:flex-none
        [&_button]:whitespace-nowrap [&>*>button]:w-full [&>a>:is(button,span)]:w-full [&_[data-overflow]]:w-11
      "
      @click="submitOwnerForm"
    >
      <slot />
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, provide, ref, watch } from 'vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useGlobalStore } from '@/scripts/stores/global.store'

const { isPhone } = useBreakpoints()
const globalStore = useGlobalStore()

// Where the bar sits in the page, before its contents move to the screen edge
const anchor = ref<HTMLElement | null>(null)

// Buttons in the bar keep their labels, even when they come from a page header,
// and dropdown triggers stretch to their share of the bar
provide('pageHeaderCompact', ref(false))
provide('inActionBar', true)

/**
 * The bar is moved out of the page, so a submit button inside it no longer
 * belongs to the form it was written in. Submit that form on its behalf.
 */
function submitOwnerForm(event: MouseEvent): void {
  const button = (event.target as Element | null)?.closest<HTMLButtonElement>('button[type="submit"]')

  if (!button || button.form || button.disabled) {
    return
  }

  const form = anchor.value?.closest('form')

  if (form) {
    event.preventDefault()
    form.requestSubmit()
  }
}

let release: (() => void) | null = null

watch(
  isPhone,
  (phone) => {
    if (phone && !release) {
      release = globalStore.registerActionBar()
    } else if (!phone && release) {
      release()
      release = null
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  release?.()
  release = null
})
</script>
