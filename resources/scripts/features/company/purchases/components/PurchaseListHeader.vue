<template>
  <BasePageHeader
    :title="title"
    :help="active ? $t(purchaseHelpKeys[active.kind]) : ''"
    :help-title="helpTitle"
  >
    <template v-if="showSwitcher" #title-suffix>
      <BaseViewSwitcher
        :model-value="active?.key || ''"
        :primary-value="purchaseViews[section][0].key"
        :label="title"
        :options="options"
        @update:model-value="selectView"
      />
    </template>
    <BaseBreadcrumb>
      <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
      <BaseBreadcrumbItem :title="$t('purchases.title')" to="#" active />
    </BaseBreadcrumb>
    <template #actions><slot name="actions" /></template>
  </BasePageHeader>
</template>
<script setup lang="ts">
import { computed, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/scripts/stores/user.store'
import BaseViewSwitcher from '@/scripts/components/base/BaseViewSwitcher.vue'
import {
  purchaseViews,
  purchaseHelpKeys,
  type PurchaseSection,
} from '../navigation'
const { t } = useI18n()
const props = defineProps<{ section: PurchaseSection }>()
const route = useRoute(),
  router = useRouter(),
  user = useUserStore()
const title = computed(() =>
  t(
    props.section === 'expenses'
      ? 'expenses.title'
      : `purchases.${props.section}`,
  ),
)
const views = computed(() =>
  purchaseViews[props.section].filter((view) =>
    user.hasAbilities(view.ability),
  ),
)
const active = computed(
  () =>
    views.value.find((view) => view.key === route.query.view) || views.value[0],
)
const options = computed(() =>
  views.value.map((view) => ({
    value: view.key,
    label: t(view.label),
    icon: view.icon,
  })),
)
const showSwitcher = computed(
  () =>
    views.value.length > 1 ||
    (active.value && active.value.key !== purchaseViews[props.section][0].key),
)
const helpTitle = computed(() =>
  t(
    active.value?.kind === 'expenses'
      ? 'expenses.title'
      : `purchases.${active.value?.kind || props.section}`,
  ),
)
async function selectView(value: string) {
  const view = views.value.find((view) => view.key === value)
  if (!view || view.key === active.value?.key) return
  await router.push({
    path: `/admin/${props.section}`,
    query: {
      ...(view.kind !== 'expenses' && route.query.supplier_id
        ? { supplier_id: route.query.supplier_id }
        : {}),
      view: view.key,
    },
  })
  // The list is remounted for its new kind; return keyboard focus to its selector.
  await nextTick()
  document
    .querySelector<HTMLElement>('[data-view-switcher] button')
    ?.focus({ preventScroll: true })
}
</script>
