<template>
  <section class="px-5 py-5 border glass rounded-xl md:px-7 md:py-6">
    <!-- Basic Info -->
    <BaseHeading>
      {{ $t('customers.basic_info') }}
    </BaseHeading>

    <BaseDescriptionList>
      <BaseDescriptionListItem
        v-if="selectedViewCustomer.name"
        :content-loading="contentLoading"
        :label="$t('customers.display_name')"
        :value="selectedViewCustomer?.name"
      />

      <BaseDescriptionListItem
        v-if="selectedViewCustomer.contact_name"
        :content-loading="contentLoading"
        :label="$t('customers.primary_contact_name')"
        :value="selectedViewCustomer?.contact_name"
      />
      <BaseDescriptionListItem
        v-if="selectedViewCustomer.email"
        :content-loading="contentLoading"
        :label="$t('customers.email')"
        :value="selectedViewCustomer?.email"
      />
      <BaseDescriptionListItem
        :content-loading="contentLoading"
        :label="$t('wizard.currency')"
        :value="
          selectedViewCustomer?.currency
            ? `${selectedViewCustomer?.currency?.code} (${selectedViewCustomer?.currency?.symbol})`
            : ''
        "
      />

      <BaseDescriptionListItem
        v-if="selectedViewCustomer.phone"
        :content-loading="contentLoading"
        :label="$t('customers.phone_number')"
        :value="selectedViewCustomer?.phone"
      />
      <BaseDescriptionListItem
        v-if="selectedViewCustomer.website"
        :content-loading="contentLoading"
        :label="$t('customers.website')"
        :value="selectedViewCustomer?.website"
      />
    </BaseDescriptionList>

    <!-- Address -->
    <template v-if="selectedViewCustomer.billing || selectedViewCustomer.shipping">
      <BaseHeading class="pt-6 mt-6 border-t border-line-light">
        {{ $t('customers.address') }}
      </BaseHeading>

      <BaseDescriptionList>
        <BaseDescriptionListItem
          v-if="selectedViewCustomer.billing"
          :content-loading="contentLoading"
          :label="$t('customers.billing_address')"
        >
          <BaseCustomerAddressDisplay :address="selectedViewCustomer.billing" />
        </BaseDescriptionListItem>

        <BaseDescriptionListItem
          v-if="selectedViewCustomer.shipping"
          :content-loading="contentLoading"
          :label="$t('customers.shipping_address')"
        >
          <BaseCustomerAddressDisplay :address="selectedViewCustomer.shipping" />
        </BaseDescriptionListItem>
      </BaseDescriptionList>
    </template>

    <!-- Custom Fields -->
    <template v-if="customerCustomFields.length > 0">
      <BaseHeading class="pt-6 mt-6 border-t border-line-light">
        {{ $t('settings.custom_fields.title') }}
      </BaseHeading>

      <BaseDescriptionList>
        <BaseDescriptionListItem
          v-for="(field, index) in customerCustomFields"
          :key="index"
          :content-loading="contentLoading"
          :label="field.custom_field.label"
        >
          <template v-if="field.type === 'Switch'">
            {{ field.default_answer === 1 ? $t('general.yes') : $t('general.no') }}
          </template>
          <template v-else>
            {{ field.default_answer }}
          </template>
        </BaseDescriptionListItem>
      </BaseDescriptionList>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useCustomerStore } from '../store'

const customerStore = useCustomerStore()

const selectedViewCustomer = computed(() => customerStore.selectedViewCustomer)

const contentLoading = computed(() => customerStore.isFetchingViewData)

const customerCustomFields = computed(() => {
  if (selectedViewCustomer?.value?.fields) {
    return selectedViewCustomer?.value?.fields
  }
  return []
})
</script>
