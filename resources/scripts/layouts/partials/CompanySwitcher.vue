<template>
  <div ref="root" class="relative min-w-0" @focusout="onFocusOut" @keydown.esc="closeAndFocus">
    <button
      ref="trigger"
      type="button"
      :class="triggerClass"
      :aria-expanded="isShow"
      :aria-controls="isShow && !isPhone ? panelId : undefined"
      :aria-label="variant === 'rail' ? label : undefined"
      @click="isShow = !isShow"
    >
      <span
        :class="[
          'flex items-center justify-center overflow-hidden font-semibold rounded-lg shrink-0',
          avatarClass,
          variant === 'appbar' ? 'w-7 h-7 text-xs' : 'w-8 h-8 text-sm',
        ]"
      >
        <BaseIcon
          v-if="companyStore.isAdminMode"
          name="ShieldCheckIcon"
          class="w-4.5 h-4.5"
        />
        <span v-else>{{ initial }}</span>
      </span>

      <template v-if="variant !== 'rail'">
        <span
          :class="[
            variant === 'appbar' ? 'max-w-[50vw]' : 'flex-1 min-w-0',
            tone === 'chrome' ? 'text-chrome-fg' : 'text-heading',
          ]"
          class="text-sm font-semibold text-left truncate"
        >
          {{ label }}
        </span>
        <BaseIcon
          name="ChevronUpDownIcon"
          :class="tone === 'chrome' ? 'text-chrome-muted' : 'text-subtle'"
          class="w-4 h-4 shrink-0"
        />
      </template>
    </button>

    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="-translate-y-1 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-1 opacity-0"
    >
      <div
        v-if="isShow && !isPhone"
        :id="panelId"
        ref="panel"
        :class="[
          'absolute z-50 w-72 max-h-[70vh] overflow-y-auto p-1 border rounded-xl glass-strong',
          variant === 'rail' ? 'left-full top-0 ml-2' : 'left-0 top-full mt-1.5',
        ]"
      >
        <CompanySwitcherList
          @select="changeCompany"
          @admin="enterAdminMode"
          @add="addNewCompany"
        />
      </div>
    </transition>

    <BaseSheet
      v-if="isPhone"
      :show="isShow"
      :title="$t('company_switcher.label')"
      @close="isShow = false"
    >
      <CompanySwitcherList
        @select="changeCompany"
        @admin="enterAdminMode"
        @add="addNewCompany"
      />
    </BaseSheet>

    <!-- Phones mount a second switcher in the app bar; one modal is enough -->
    <CompanyModal v-if="variant !== 'appbar'" />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import CompanyModal from '@/scripts/features/company/settings/components/CompanyModal.vue'
import CompanySwitcherList from './CompanySwitcherList.vue'
import type { Company } from '@/scripts/types/domain/company'

interface Props {
  /** sidebar: full row; rail: the avatar alone; appbar: compact row for the phone app bar */
  variant?: 'sidebar' | 'rail' | 'appbar'
  /** chrome: drawn on the brand-coloured sidebar or app bar */
  tone?: 'surface' | 'chrome'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'sidebar',
  tone: 'surface',
})

const companyStore = useCompanyStore()
const modalStore = useModalStore()
const globalStore = useGlobalStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { isPhone } = useBreakpoints()

const isShow = ref<boolean>(false)
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const panelId = `company-switcher-${useId()}`

// Opening moves focus into the list; Escape or tabbing away closes it
watch(isShow, async (open) => {
  if (open && !isPhone.value) {
    await nextTick()
    panel.value?.querySelector<HTMLElement>('button, a[href]')?.focus()
  }
})

function onFocusOut(event: FocusEvent): void {
  if (!isPhone.value && isShow.value && !root.value?.contains(event.relatedTarget as Node | null)) {
    isShow.value = false
  }
}

function closeAndFocus(): void {
  if (isShow.value && !isPhone.value) {
    isShow.value = false
    trigger.value?.focus()
  }
}

const label = computed<string>(() => {
  if (companyStore.isAdminMode) {
    return t('navigation.administration')
  }

  return companyStore.selectedCompany?.name ?? ''
})

const initial = computed<string>(() => {
  const name = companyStore.selectedCompany?.name ?? ''
  return name ? name.trim().charAt(0).toUpperCase() : ''
})

const triggerClass = computed<string>(() => {
  const hover = props.tone === 'chrome' ? 'hover:bg-chrome-hover' : 'hover:bg-hover'

  switch (props.variant) {
    case 'rail':
      return `flex items-center justify-center w-10 h-10 rounded-lg ${hover} transition-colors`
    case 'appbar':
      return `flex items-center gap-2 px-1.5 py-1 -ml-1.5 rounded-lg ${hover} transition-colors`
    default:
      return `flex items-center w-full gap-2.5 px-2 py-1.5 rounded-lg ${hover} transition-colors`
  }
})

const avatarClass = computed<string>(() => {
  if (props.tone === 'chrome') {
    return 'bg-chrome-accent text-chrome'
  }

  return companyStore.isAdminMode ? 'bg-primary-50 text-primary-600' : 'bg-primary-600 text-on-primary'
})

watch(route, () => {
  isShow.value = false
})

// The sheet renders outside this element, so outside clicks only count for the popover
onClickOutside(root, () => {
  if (!isPhone.value) {
    isShow.value = false
  }
})

function addNewCompany(): void {
  isShow.value = false
  modalStore.openModal({
    title: t('company_switcher.new_company'),
    componentName: 'CompanyModal',
    size: 'sm',
  })
}

async function enterAdminMode(): Promise<void> {
  companyStore.setAdminMode(true)
  isShow.value = false
  router.push('/admin/administration/dashboard')
  globalStore.setIsAppLoaded(false)
  await globalStore.bootstrap()
}

async function changeCompany(company: Company): Promise<void> {
  isShow.value = false
  companyStore.setAdminMode(false)
  companyStore.setSelectedCompany(company)
  router.push('/admin/dashboard')
  globalStore.setIsAppLoaded(false)
  await globalStore.bootstrap()
}
</script>
