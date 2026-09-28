<template>
  <CategoryModal
    :show="context.active.value === 'category'"
    :title="$t('purchases.new_category')"
    @saved="context.saved"
    @close="context.close"
  />
  <PaymentModeModal
    :show="context.active.value === 'method'"
    :title="$t('purchases.new_payment_method')"
    @saved="context.saved"
    @close="context.close"
  />
  <TaxTypeModal
    :show="context.active.value === 'tax'"
    :title="$t('purchases.new_purchase_tax')"
    :data="{ transaction_type: 'purchases' }"
    lock-transaction-type
    @saved="context.saved"
    @close="context.close"
  />
</template>
<script setup lang="ts">
import CategoryModal from '../../settings/components/CategoryModal.vue'
import PaymentModeModal from '../../settings/components/PaymentModeModal.vue'
import TaxTypeModal from '../../settings/components/TaxTypeModal.vue'
import type { PurchaseLookups } from '../composables/use-purchase-lookups'
defineProps<{ context: PurchaseLookups }>()
</script>
