<template>
  <div>
    <BaseContactPicker
      ref="picker"
      :label="$t('purchases.supplier')"
      :placeholder="$t('purchases.select_supplier')"
      :selected="contact"
      :choices="choices"
      :loading="searching"
      :content-loading="loading"
      :error="error || fieldError"
      :required="required"
      :disabled="disabled"
      :can-browse="canBrowse"
      :can-create="user.hasAbilities('create-supplier')"
      :can-edit="user.hasAbilities('edit-supplier')"
      :create-label="$t('purchases.new_supplier')"
      :edit-label="$t('purchases.edit_supplier')"
      :empty-text="$t('purchases.no_matching_suppliers')"
      @search="search"
      @select="select"
      @clear="clear"
      @create="create"
      @edit="edit"
    />
    <SupplierModal
      :show="modalOpen"
      :supplier="editing"
      @close="modalOpen = false"
      @saved="saved"
    />
  </div>
</template>
<script setup lang="ts">
import { computed, inject, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/scripts/stores/user.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type {
  PurchaseRecord,
  SupplierSnapshot,
} from '@/scripts/types/domain/purchase'
import BaseContactPicker, {
  type ContactChoice,
} from '@/scripts/components/base/BaseContactPicker.vue'
import { purchaseErrors } from '../composables/use-purchase-form'
import { purchaseError } from '../helpers'
import SupplierModal from './SupplierModal.vue'
const model = defineModel<number | null>({ required: true })
const props = withDefaults(
  defineProps<{
    disabled?: boolean
    required?: boolean
    supplier?: PurchaseRecord | null
    snapshot?: SupplierSnapshot | null
  }>(),
  { disabled: false, required: true, supplier: null, snapshot: null },
)
const emit = defineEmits<{
  selected: [supplier: PurchaseRecord]
  updated: [supplier: PurchaseRecord]
  cleared: []
}>()
const user = useUserStore(),
  company = useCompanyStore(),
  { t } = useI18n(),
  fields = inject(purchaseErrors, undefined)
const current = ref<PurchaseRecord | null>(null),
  rows = ref<PurchaseRecord[]>([]),
  loading = ref(false),
  searching = ref(false),
  error = ref(''),
  modalOpen = ref(false),
  editing = ref<PurchaseRecord | null>(null)
const picker = ref<InstanceType<typeof BaseContactPicker> | null>(null)
const canBrowse = computed(() => user.hasAbilities('view-supplier'))
const fieldError = computed(() => fields?.value.supplier_id?.[0] || '')
const shown = computed(() =>
  props.snapshot && props.disabled && model.value
    ? { ...current.value, ...props.snapshot, id: model.value }
    : current.value,
)
const contact = computed<ContactChoice | null>(() => {
  if (!shown.value || !model.value) return null
  const record = shown.value,
    address = record.addresses?.find((address) =>
      Object.values(address).some(Boolean),
    )
  const lines = address
    ? [
        address.address_street_1,
        address.address_street_2,
        [address.city, address.state, address.zip].filter(Boolean).join(', '),
      ].filter((line): line is string => !!line)
    : []
  return {
    id: model.value,
    name: record.name || '',
    subtitle: [record.contact_name, record.email, record.phone]
      .filter(Boolean)
      .join(' · '),
    addresses: lines.length ? [{ label: t('purchases.address'), lines }] : [],
  }
})
const choices = computed(() =>
  rows.value
    .filter((row) => row.enabled !== false)
    .map((row) => ({
      id: row.id,
      name: row.name || '',
      subtitle: row.contact_name || row.email || '',
    })),
)
let request = 0,
  searchRequest = 0,
  timer: ReturnType<typeof setTimeout> | undefined
watch(
  () => company.selectedCompany?.id,
  () => {
    request++
    searchRequest++
    clearTimeout(timer)
    current.value = null
    rows.value = []
    loading.value = false
    searching.value = false
    modalOpen.value = false
  },
)
watch(
  () => [
    model.value,
    props.supplier,
    props.snapshot,
    company.selectedCompany?.id,
  ],
  async () => {
    const token = ++request
    error.value = ''
    loading.value = false
    if (!model.value) {
      current.value = null
      loading.value = false
      return
    }
    if (props.supplier?.id === model.value) {
      current.value = props.supplier
      loading.value = false
      return
    }
    if (current.value?.id === model.value) return
    if (props.snapshot && props.disabled) {
      current.value = { id: model.value, ...props.snapshot }
      return
    }
    if (!canBrowse.value) {
      current.value = null
      return
    }
    current.value = null
    loading.value = true
    try {
      const record = await purchaseService.get('suppliers', model.value)
      if (token === request) current.value = record
    } catch (e) {
      if (token === request) error.value = purchaseError(e)
    } finally {
      if (token === request) loading.value = false
    }
  },
  { immediate: true },
)
function search(query: string) {
  if (!canBrowse.value) return
  clearTimeout(timer)
  error.value = ''
  const token = ++searchRequest
  searching.value = true
  timer = setTimeout(async () => {
    try {
      const result = await purchaseService.suppliers(query)
      if (token === searchRequest) rows.value = result
    } catch (e) {
      if (token === searchRequest) error.value = purchaseError(e)
    } finally {
      if (token === searchRequest) searching.value = false
    }
  }, 250)
}
function select(id: number) {
  const record = rows.value.find((row) => row.id === id)
  if (!record) return
  current.value = record
  model.value = id
  emit('selected', record)
}
function clear() {
  request++
  current.value = null
  model.value = null
  emit('cleared')
}
function create() {
  editing.value = null
  modalOpen.value = true
}
function edit() {
  editing.value = current.value
  modalOpen.value = true
}
function saved(record: PurchaseRecord) {
  const changed = model.value !== record.id
  current.value = record
  model.value = record.id
  if (changed) emit('selected', record)
  else emit('updated', record)
}
onUnmounted(() => {
  request++
  searchRequest++
  clearTimeout(timer)
})
</script>
