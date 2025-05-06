<template>
  <div
    v-if="store[storeProp].tax_included"
    class="flex items-center justify-between w-full"
  >
    <BaseContentPlaceholders v-if="isLoading">
      <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
    </BaseContentPlaceholders>
    <label
      v-else
      class="text-sm font-semibold leading-5 text-gray-500 uppercase"
    >
      {{ $t('estimates.net_total') }}
    </label>

    <BaseContentPlaceholders v-if="isLoading">
      <BaseContentPlaceholdersText :lines="1" class="w-16 h-5" />
    </BaseContentPlaceholders>

    <label
      v-else
      class="flex items-center justify-center m-0 text-lg text-black uppercase "
    >
      <BaseFormatMoney
        :amount="store.getNetTotal"
        :currency="currency"
      />
    </label>
  </div>
</template>
<script setup>
import BaseContentPlaceholdersText from '@/scripts/components/base/BaseContentPlaceholdersText.vue'
import BaseContentPlaceholders from '@/scripts/components/base/BaseContentPlaceholders.vue'
import BaseFormatMoney from '@/scripts/components/base/BaseFormatMoney.vue'

const props = defineProps({
  store: {
    type: Object,
    default: null,
  },
  storeProp: {
    type: String,
    default: '',
  },
  currency: {
    type: [Object, String],
    default: '',
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
})
</script>
