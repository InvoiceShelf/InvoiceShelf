<template>
  <BasePage>
    <BasePageHeader
      :help="
        kind === 'recurring-costs'
          ? $t(recurringLabel(record?.mode, 'help'))
          : $t(purchaseHelpKeys[kind])
      "
      phone-actions="bar"
      :title="record?.number || record?.name || $t(`purchases.${kind}`)"
      :subtitle="
        kind === 'suppliers'
          ? record?.contact_name || record?.email || ''
          : record?.supplier?.name || ''
      "
    >
      <template v-if="kind === 'suppliers'" #leading>
        <span
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-btn-primary text-base font-semibold text-on-primary"
          aria-hidden="true"
          >{{ initials }}</span
        >
      </template>
      <PurchaseBreadcrumb
        :kind="kind"
        :mode="record?.mode || (route.query.mode as string | undefined)"
      />
      <template v-if="record && !loading && hasActions" #actions>
        <BaseDropdown
          v-if="kind === 'suppliers' && supplierActions.length"
          position="bottom-end"
          width-class="w-56"
        >
          <template #activator
            ><span
              class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-btn-primary px-3.5 text-sm font-medium text-on-primary hover:bg-btn-primary-hover md:h-9"
              ><BaseIcon name="PlusIcon" class="h-4 w-4" />{{
                $t('customers.new_transaction')
              }}</span
            ></template
          >
          <BaseDropdownItem
            v-for="target in supplierActions"
            :key="target"
            :to="{
              path: `/admin/${target}/create`,
              query: { supplier_id: id },
            }"
            >{{ $t(purchaseCreateLabels[target]) }}</BaseDropdownItem
          >
        </BaseDropdown>
        <BaseButton
          v-if="kind === 'bills' && record.status === 'DRAFT' && canEdit"
          :loading="busy"
          @click="act('open')"
          >{{ $t('purchases.open_bill') }}</BaseButton
        >
        <BaseButton
          v-if="
            kind === 'bills' &&
            record.status === 'OPEN' &&
            (record.due_amount || 0) > 0 &&
            user.hasAbilities('create-supplier-payment')
          "
          @click="
            router.push({
              path: '/admin/supplier-payments/create',
              query: { bill_id: id },
            })
          "
          >{{ $t('purchases.new_payment') }}</BaseButton
        >
        <BaseButton
          v-if="
            kind === 'recurring-costs' &&
            canEdit &&
            record.status !== 'COMPLETED'
          "
          :variant="record.status === 'ACTIVE' ? 'white' : 'primary'"
          :loading="busy"
          :disabled="busy"
          @click="act(record.status === 'ACTIVE' ? 'pause' : 'resume')"
          ><template #left="slotProps"
            ><BaseIcon
              :name="record.status === 'ACTIVE' ? 'PauseIcon' : 'PlayIcon'"
              :class="slotProps.class" /></template
          >{{
            $t(
              record.status === 'ACTIVE' ? 'purchases.pause' : 'purchases.resume',
            )
          }}</BaseButton
        >
        <BaseButton
          v-if="canAllocate && record.status === 'OPEN'"
          @click="editingAllocations = !editingAllocations"
          >{{ $t('purchases.manage_allocations') }}</BaseButton
        >
        <BaseDropdown
          v-if="hasSecondaryActions"
          position="bottom-end"
          width-class="w-56"
        >
          <template #activator
            ><span
              class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-line-default bg-surface px-3 text-sm font-medium text-body hover:bg-hover md:h-9"
              ><BaseIcon
                name="EllipsisHorizontalIcon"
                class="h-5 w-5"
              /><span>{{ $t('navigation.more') }}</span></span
            ></template
          >
          <BaseDropdownItem
            v-if="editable && record.status !== 'VOID'"
            :to="{
              path: `/admin/${kind}/${id}/edit`,
              query: record.mode ? { mode: record.mode } : {},
            }"
            ><BaseIcon
              name="PencilSquareIcon"
              class="me-3 h-5 w-5 text-muted"
            />{{ $t('purchases.edit') }}</BaseDropdownItem
          >
          <BaseDropdownItem
            v-if="
              kind === 'bills' &&
              record.status === 'OPEN' &&
              user.hasAbilities('create-supplier-credit')
            "
            :to="{
              path: '/admin/supplier-credits/create',
              query: { bill_id: id },
            }"
            >{{ $t('purchases.new_credit') }}</BaseDropdownItem
          >
          <BaseDropdownItem
            v-if="refundable"
            :to="{
              path: '/admin/supplier-refunds/create',
              query: {
                [kind === 'supplier-payments' ? 'payment_id' : 'credit_id']: id,
              },
            }"
            >{{ $t('purchases.new_refund') }}</BaseDropdownItem
          >
          <BaseDropdownItem v-if="canDelete" @click="removeSchedule"
            ><BaseIcon name="TrashIcon" class="me-3 h-5 w-5 text-danger" />{{
              $t('general.delete')
            }}</BaseDropdownItem
          >
          <BaseDropdownItem
            v-if="canVoid && record.status !== 'VOID'"
            @click="showVoid = !showVoid"
            ><BaseIcon name="XCircleIcon" class="me-3 h-5 w-5 text-danger" />{{
              $t('purchases.void')
            }}</BaseDropdownItem
          >
        </BaseDropdown>
      </template>
    </BasePageHeader>
    <p v-if="error" role="alert" class="text-sm text-danger">{{ error }}</p>
    <p
      v-if="route.query.attachment_error"
      role="alert"
      class="text-sm text-danger"
    >
      {{ $t('purchases.attachment_error') }}
    </p>
    <BaseContentPlaceholders v-if="loading"
      ><BaseContentPlaceholdersBox :rounded="true" class="h-48 w-full"
    /></BaseContentPlaceholders>
    <template v-else-if="record">
      <BaseCard v-if="showVoid" container-class="p-4 md:p-5">
        <form class="space-y-4" @submit.prevent="act('void')">
          <p class="text-sm text-body">{{ $t('purchases.void_help') }}</p>
          <BaseInputGroup :label="$t('purchases.void_reason')" required
            ><BaseTextarea v-model="voidReason" required maxlength="2000"
          /></BaseInputGroup>
          <div class="flex gap-3">
            <BaseButton type="submit" :disabled="busy" :loading="busy">{{
              $t('purchases.confirm_void')
            }}</BaseButton
            ><BaseButton variant="white" @click="showVoid = false">{{
              $t('general.cancel')
            }}</BaseButton>
          </div>
        </form>
      </BaseCard>
      <SupplierOverview v-if="kind === 'suppliers'" :record="record" />
      <RecurringCostOverview
        v-else-if="kind === 'recurring-costs'"
        :record="record"
      />
      <template v-else>
        <BaseStatStrip
          v-if="record.amount !== undefined || record.total !== undefined"
          :columns="
            kind === 'bills' || record.available_amount !== undefined ? 3 : 2
          "
        >
          <BaseStat :label="$t('purchases.amount')" emphasis
            ><BaseFormatMoney
              :amount="record.total ?? record.amount ?? 0"
              :currency="record.currency"
          /></BaseStat>
          <BaseStat v-if="kind === 'bills'" :label="$t('purchases.due')"
            ><BaseFormatMoney
              :amount="record.status === 'VOID' ? 0 : record.due_amount || 0"
              :currency="record.currency"
          /></BaseStat>
          <BaseStat
            v-if="record.available_amount !== undefined"
            :label="$t('purchases.available')"
            ><BaseFormatMoney
              :amount="record.available_amount"
              :currency="record.currency"
          /></BaseStat>
          <BaseStat :label="$t('purchases.status')"
            ><PurchaseStatus :kind="kind" :record="record"
          /></BaseStat>
        </BaseStatStrip>
        <div class="grid items-start gap-5 xl:grid-cols-3">
          <div class="space-y-5 xl:col-span-2">
            <BaseCard container-class="p-4 md:p-5">
              <dl class="grid gap-5 text-sm sm:grid-cols-2">
                <div v-if="record.supplier">
                  <dt class="text-muted">{{ $t('purchases.supplier') }}</dt>
                  <dd class="mt-1">
                    <router-link
                      :to="`/admin/suppliers/${record.supplier.id}/view`"
                      class="font-medium text-primary-600"
                      >{{ record.supplier.name }}</router-link
                    >
                  </dd>
                </div>
                <div v-if="record.document_date || record.payment_date">
                  <dt class="text-muted">{{ $t('purchases.date') }}</dt>
                  <dd class="mt-1 text-heading">
                    <PurchaseDate
                      :value="record.document_date || record.payment_date"
                    />
                  </dd>
                </div>
                <div v-if="record.due_date">
                  <dt class="text-muted">{{ $t('purchases.due_date') }}</dt>
                  <dd class="mt-1 text-heading">
                    <PurchaseDate :value="record.due_date" />
                  </dd>
                </div>
                <div v-if="record.reference">
                  <dt class="text-muted">
                    {{ $t('purchases.supplier_reference') }}
                  </dt>
                  <dd class="mt-1 break-words text-heading">
                    {{ record.reference }}
                  </dd>
                </div>
                <div v-if="record.source_bill_id || record.source_expense_id">
                  <dt class="text-muted">
                    {{ $t('purchases.source_record') }}
                  </dt>
                  <dd class="mt-1">
                    <router-link
                      :to="
                        record.source_bill_id
                          ? `/admin/bills/${record.source_bill_id}/view`
                          : `/admin/expenses/${record.source_expense_id}/edit`
                      "
                      class="text-primary-600"
                      >{{ $t('purchases.view_source') }}</router-link
                    >
                  </dd>
                </div>
                <div
                  v-if="record.supplier_payment_id || record.supplier_credit_id"
                >
                  <dt class="text-muted">
                    {{ $t('purchases.source_record') }}
                  </dt>
                  <dd class="mt-1">
                    <router-link
                      :to="`/admin/${record.supplier_payment_id ? 'supplier-payments' : 'supplier-credits'}/${record.supplier_payment_id || record.supplier_credit_id}/view`"
                      class="text-primary-600"
                      >{{ $t('purchases.view_source') }}</router-link
                    >
                  </dd>
                </div>
              </dl>
              <PurchaseCustomFieldValues
                v-if="kind === 'bills'"
                :fields="record.fields"
                class="mt-5"
              />
              <p
                v-if="record.notes"
                class="mt-5 whitespace-pre-wrap text-sm text-body"
              >
                {{ record.notes }}
              </p>
              <p v-if="record.void_reason" class="mt-4 text-sm text-muted">
                {{ $t('purchases.void_reason') }}: {{ record.void_reason }}
              </p>
            </BaseCard>
            <BaseTable
              v-if="record.items?.length"
              :data="record.items"
              :columns="itemColumns"
              :caption="$t('purchases.line_items')"
            >
              <template #cell-description="{ row }"
                ><span class="block whitespace-normal break-words">{{
                  row.data.description
                }}</span></template
              >
              <template #cell-quantity="{ row }"
                ><span class="md:hidden">{{ $t('purchases.quantity') }}: </span
                >{{ row.data.quantity }}</template
              >
              <template #cell-price="{ row }"
                ><BaseFormatMoney
                  :amount="row.data.price"
                  :currency="record.currency"
              /></template>
              <template #cell-tax="{ row }"
                ><BaseFormatMoney
                  :amount="row.data.tax || 0"
                  :currency="record.currency"
              /></template>
              <template #cell-total="{ row }"
                ><BaseFormatMoney
                  :amount="row.data.total || 0"
                  :currency="record.currency"
              /></template>
            </BaseTable>
            <BaseCard v-if="editingAllocations" container-class="p-4 md:p-5"
              ><h2 class="mb-4 font-semibold text-section text-heading">
                {{ $t('purchases.apply_to_bills') }}
              </h2>
              <AllocationEditor
                :record="record"
                :kind="kind"
                @saved="allocationsSaved"
            /></BaseCard>
            <PurchaseSettlements :kind="kind" :record="record" />
          </div>
          <div class="space-y-5">
            <BaseCard v-if="record.credits?.length" container-class="p-4 md:p-5"
              ><h2 class="mb-3 font-semibold text-section text-heading">
                {{ $t('purchases.supplier-credits') }}
              </h2>
              <div
                v-for="credit in record.credits"
                :key="credit.id"
                class="flex justify-between gap-3 py-3 text-sm"
              >
                <router-link
                  :to="`/admin/supplier-credits/${credit.id}/view`"
                  class="text-primary-600"
                  >{{ credit.number }}</router-link
                ><BaseFormatMoney
                  :amount="credit.amount || 0"
                  :currency="record.currency"
                /></div
            ></BaseCard>
            <BaseCard v-if="record.refunds?.length" container-class="p-4 md:p-5"
              ><h2 class="mb-3 font-semibold text-section text-heading">
                {{ $t('purchases.supplier-refunds') }}
              </h2>
              <div
                v-for="refund in record.refunds"
                :key="refund.id"
                class="flex justify-between gap-3 py-3 text-sm"
              >
                <router-link
                  :to="`/admin/supplier-refunds/${refund.id}/view`"
                  class="text-primary-600"
                  >{{ refund.number }} ·
                  {{ $t(`purchases.state_${refund.status}`) }}</router-link
                ><BaseFormatMoney
                  :amount="refund.amount || 0"
                  :currency="record.currency"
                /></div
            ></BaseCard>
            <PurchaseDocumentFiles
              v-if="kind === 'bills' || kind === 'supplier-credits'"
              :kind="kind"
              :record="record"
              :can-edit="canEdit"
              @changed="load"
              @error="error = $event"
            />
          </div>
        </div>
      </template>
    </template>
  </BasePage>
</template>
<script setup lang="ts">
import PurchaseCustomFieldValues from '../components/PurchaseCustomFieldValues.vue'
import PurchaseDate from '../components/PurchaseDate.vue'
import PurchaseBreadcrumb from '../components/PurchaseBreadcrumb.vue'
import PurchaseStatus from '../components/PurchaseStatus.vue'
import {
  purchaseCreateLabels,
  purchaseHelpKeys,
  purchaseParent,
  recurringLabel,
} from '../navigation'

import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/scripts/stores/user.store'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type {
  PurchaseKind,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'
import { entityAbility, purchaseError } from '../helpers'
import AllocationEditor from '../components/AllocationEditor.vue'
import PurchaseDocumentFiles from '../components/PurchaseDocumentFiles.vue'
import PurchaseSettlements from '../components/PurchaseSettlements.vue'
import SupplierOverview from '../components/SupplierOverview.vue'
import RecurringCostOverview from '../components/RecurringCostOverview.vue'
import { useDialogStore } from '@/scripts/stores/dialog.store'
const { t } = useI18n()
const itemColumns = computed(() => [
  { key: 'description', label: t('purchases.description'), mobile: 'title' },
  { key: 'quantity', label: t('purchases.quantity'), mobile: 'subtitle' },
  { key: 'price', label: t('purchases.unit_price') },
  { key: 'tax', label: t('purchases.purchase_taxes') },
  { key: 'total', label: t('purchases.amount'), mobile: 'trailing' },
])
const props = defineProps<{ kind: PurchaseKind }>(),
  route = useRoute(),
  router = useRouter(),
  user = useUserStore(),
  dialogStore = useDialogStore()
const id = computed(() => Number(route.params.id)),
  record = ref<PurchaseRecord | null>(null),
  error = ref(''),
  loading = ref(true),
  busy = ref(false),
  showVoid = ref(false),
  voidReason = ref(''),
  editingAllocations = ref(false)
const canEdit = computed(() =>
  user.hasAbilities(`edit-${entityAbility(props.kind)}`),
)
const editable = computed(
  () =>
    ['suppliers', 'bills', 'recurring-costs'].includes(props.kind) &&
    canEdit.value &&
    record.value?.status !== 'VOID',
)
const canAllocate = computed(
  () =>
    ['supplier-payments', 'supplier-credits'].includes(props.kind) &&
    canEdit.value &&
    record.value?.status !== 'VOID',
)
const refundable = computed(
  () =>
    ['supplier-payments', 'supplier-credits'].includes(props.kind) &&
    (record.value?.available_amount ?? 0) > 0 &&
    user.hasAbilities('create-supplier-refund'),
)
const canVoid = computed(
  () =>
    !['suppliers', 'recurring-costs'].includes(props.kind) &&
    user.hasAbilities(`delete-${entityAbility(props.kind)}`),
)
const canDelete = computed(
  () =>
    props.kind === 'recurring-costs' &&
    user.hasAbilities('delete-recurring-cost'),
)
const initials = computed(() =>
  (record.value?.name || '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word[0])
    .join(''),
)
const supplierActions = computed(() =>
  (
    [
      'bills',
      'supplier-payments',
      'supplier-credits',
      'recurring-costs',
    ] as PurchaseKind[]
  ).filter((kind) => user.hasAbilities(`create-${entityAbility(kind)}`)),
)
const hasSecondaryActions = computed(
  () =>
    !!record.value &&
    ((editable.value && record.value.status !== 'VOID') ||
      refundable.value ||
      canDelete.value ||
      (canVoid.value && record.value.status !== 'VOID') ||
      (props.kind === 'bills' &&
        record.value.status === 'OPEN' &&
        user.hasAbilities('create-supplier-credit'))),
)
const hasActions = computed(() => {
  const current = record.value
  if (!current) return false
  return (
    hasSecondaryActions.value ||
    (props.kind === 'suppliers' && supplierActions.value.length > 0) ||
    (props.kind === 'bills' &&
      ((current.status === 'DRAFT' && canEdit.value) ||
        (current.status === 'OPEN' &&
          (current.due_amount || 0) > 0 &&
          user.hasAbilities('create-supplier-payment')))) ||
    (canAllocate.value && current.status === 'OPEN') ||
    (props.kind === 'recurring-costs' &&
      canEdit.value &&
      current.status !== 'COMPLETED')
  )
})
async function allocationsSaved() {
  editingAllocations.value = false
  await load()
}
async function load() {
  loading.value = true
  error.value = ''
  try {
    record.value = await purchaseService.get(props.kind, id.value)
  } catch (e) {
    error.value = purchaseError(e)
  } finally {
    loading.value = false
  }
}
watch(
  () => [props.kind, route.params.id],
  () => {
    editingAllocations.value = false
    showVoid.value = false
    load()
  },
  { immediate: true },
)
async function removeSchedule() {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('purchases.confirm_delete_schedule'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  })
  if (!confirmed || !record.value) return
  busy.value = true
  error.value = ''
  try {
    const mode = record.value.mode
    await purchaseService.remove(props.kind, id.value)
    await router.push(purchaseParent(props.kind, mode))
  } catch (e) {
    error.value = purchaseError(e)
  } finally {
    busy.value = false
  }
}
async function act(action: string) {
  busy.value = true
  error.value = ''
  try {
    await purchaseService.action(
      props.kind,
      id.value,
      action,
      action === 'void' ? voidReason.value : undefined,
    )
    showVoid.value = false
    await load()
  } catch (e) {
    error.value = purchaseError(e)
  } finally {
    busy.value = false
  }
}
</script>
