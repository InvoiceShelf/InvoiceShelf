<template>
  <BaseCard container-class="p-4 md:p-5">
    <h2 class="mb-4 font-semibold text-section text-heading">
      {{ $t('purchases.history') }}
    </h2>
    <ol class="space-y-4">
      <li
        v-for="activity in record.activities"
        :key="activity.id"
        class="flex gap-3 text-sm text-body"
      >
        <span
          class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500"
          aria-hidden="true"
        />
        <div>
          <p>
            {{ $t(`purchases.activity_${activity.action}`)
            }}<span v-if="activity.details?.actor_name" class="ms-2 text-muted"
              >· {{ activity.details.actor_name }}</span
            >
          </p>
          <time class="text-xs text-muted">{{
            new Date(activity.created_at).toLocaleString()
          }}</time>
          <p
            v-if="
              typeof activity.details?.before_amount === 'number' &&
              typeof activity.details?.after_amount === 'number'
            "
          >
            <BaseFormatMoney
              :amount="activity.details.before_amount"
              :currency="record.currency"
            />
            →
            <BaseFormatMoney
              :amount="activity.details.after_amount"
              :currency="record.currency"
            />
          </p>
        </div>
      </li>
    </ol>
  </BaseCard>
</template>
<script setup lang="ts">
import type { PurchaseRecord } from '@/scripts/types/domain/purchase'
defineProps<{ record: PurchaseRecord }>()
</script>
