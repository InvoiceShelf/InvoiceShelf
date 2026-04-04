<script setup lang="ts">
import type { Address, Country } from '../../types/domain'

interface DisplayAddress {
  address_street_1?: string | null
  address_street_2?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
  country?: Pick<Country, 'name'> | null
}

interface Props {
  address: DisplayAddress
}

defineProps<Props>()
</script>

<template>
  <div
    v-if="address"
    class="text-sm font-bold leading-5 text-heading non-italic space-y-1"
  >
    <p v-if="address?.address_street_1">{{ address?.address_street_1 }},</p>

    <p v-if="address?.address_street_2">{{ address?.address_street_2 }},</p>

    <p v-if="address?.city">{{ address?.city }},</p>

    <p v-if="address?.state">{{ address?.state }},</p>

    <p v-if="address?.country?.name">{{ address?.country?.name }},</p>

    <p v-if="address?.zip">{{ address?.zip }}.</p>
  </div>
</template>
