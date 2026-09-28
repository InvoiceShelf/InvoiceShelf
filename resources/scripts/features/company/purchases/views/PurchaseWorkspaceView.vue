<template>
  <ExpenseIndexView v-if="active?.kind === 'expenses'" />
  <PurchasesIndexView
    v-else-if="active"
    :key="`${section}-${active.key}`"
    :section="section"
    :kind="active.kind"
  />
</template>
<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/scripts/stores/user.store'
import ExpenseIndexView from '../../expenses/views/ExpenseIndexView.vue'
import PurchasesIndexView from './PurchasesIndexView.vue'
import { purchaseViews, type PurchaseSection } from '../navigation'
const props = defineProps<{ section: PurchaseSection }>()
const route = useRoute(),
  router = useRouter(),
  user = useUserStore()
const views = computed(() =>
  purchaseViews[props.section].filter((view) =>
    user.hasAbilities(view.ability),
  ),
)
const active = computed(() =>
  views.value.find(
    (view) =>
      view.key === (route.query.view || purchaseViews[props.section][0].key),
  ),
)
watch(
  () => [props.section, route.query.view, views.value],
  () => {
    if (!active.value && views.value.length)
      router.replace({
        path: route.path,
        query: { ...route.query, view: views.value[0].key },
      })
  },
  { immediate: true },
)
</script>
