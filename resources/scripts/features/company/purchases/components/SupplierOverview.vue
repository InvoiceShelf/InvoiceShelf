<template>
  <div
    v-for="balance in record.balances"
    :key="balance.currency_id"
    class="space-y-2"
  >
    <h2 class="text-sm font-medium text-muted">
      {{ balance.currency.code }}
    </h2>
    <BaseStatStrip :columns="3">
      <BaseStat
        v-for="field in ['due', 'advances', 'credits'] as const"
        :key="field"
        :label="$t(`purchases.${field}`)"
        :emphasis="field === 'due'"
        ><BaseFormatMoney
          :amount="balance[field]"
          :currency="balance.currency"
      /></BaseStat>
    </BaseStatStrip>
  </div>
  <div class="grid items-start gap-5 lg:grid-cols-3">
    <BaseCard container-class="p-4 md:p-5">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="font-semibold text-section text-heading">
          {{ $t('purchases.supplier') }}
        </h2>
        <PurchaseStatus kind="suppliers" :record="record" />
      </div>
      <dl class="space-y-4 text-sm">
        <div
          v-for="field in [
            'contact_name',
            'email',
            'phone',
            'website',
            'tax_id',
          ] as const"
          v-show="record[field]"
          :key="field"
        >
          <dt class="text-muted">{{ $t(`purchases.${field}`) }}</dt>
          <dd class="mt-1 break-words text-heading">
            {{ record[field] }}
          </dd>
        </div>
        <div>
          <dt class="text-muted">
            {{ $t('purchases.payment_terms') }}
          </dt>
          <dd class="mt-1 text-heading">
            {{ record.payment_terms }}
          </dd>
        </div>
      </dl>
      <PurchaseCustomFieldValues
        :fields="record.fields"
        class="mt-4"
      />
      <div
        v-for="(address, index) in (record.addresses || []).filter(
          (address) => Object.values(address).some(Boolean),
        )"
        :key="index"
        class="mt-5 border-t border-line-light pt-4 text-sm text-body"
      >
        <h3 class="mb-1 text-muted">
          {{ $t('purchases.address') }} {{ index + 1 }}
        </h3>
        {{
          [
            address.address_street_1,
            address.address_street_2,
            address.city,
            address.state,
            address.zip,
          ]
            .filter(Boolean)
            .join(', ')
        }}
      </div>
      <p
        v-if="record.notes"
        class="mt-5 whitespace-pre-wrap text-sm text-body"
      >
        {{ record.notes }}
      </p>
    </BaseCard>
    <BaseCard class="lg:col-span-2" container-class="p-4 md:p-5">
      <h2 class="mb-3 font-semibold text-section text-heading">
        {{ $t('purchases.transactions') }}
      </h2>
      <router-link
        v-for="target in supplierLinks"
        :key="target.key"
        :to="target.to"
        class="flex items-center justify-between border-b border-line-light py-4 text-sm font-medium text-heading hover:text-primary-600"
        >{{ $t(target.label)
        }}<BaseIcon
          name="ChevronRightIcon"
          class="h-4 w-4 text-muted"
      /></router-link>
    </BaseCard>
  </div>
</template>
<script setup lang="ts">
import PurchaseCustomFieldValues from './PurchaseCustomFieldValues.vue'
import PurchaseStatus from './PurchaseStatus.vue'
import { purchaseParent } from '../navigation'
import { entityAbility } from '../helpers'

import { computed } from 'vue'
import { useUserStore } from '@/scripts/stores/user.store'
import type {
  PurchaseKind,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'

const props = defineProps<{ record: PurchaseRecord }>()
const user = useUserStore()

const supplierLinks = computed(() =>
  (
    [
      'bills',
      'supplier-payments',
      'supplier-credits',
      'supplier-refunds',
    ] as PurchaseKind[]
  )
    .filter((kind) => user.hasAbilities(`view-${entityAbility(kind)}`))
    .map((kind) => {
      const parent = purchaseParent(kind)
      return {
        key: kind,
        label: `purchases.${kind}`,
        to: {
          ...parent,
          query: { ...parent.query, supplier_id: props.record.id },
        },
      }
    }),
)
</script>
